<?php
/**
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Command;

use DOMDocument;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use eZ\Publish\Core\FieldType\XmlText\Value;
use eZ\Publish\Core\FieldType\XmlText\Converter\RichText as RichTextConverter;
use eZ\Publish\Core\FieldType\XmlText\Persistence\Legacy\ContentModelGateway as Gateway;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;
use Psr\Log\LogLevel;

class ConvertXmlTextToRichTextCommand extends ContainerAwareCommand
{
    const MAX_OBJECTS_PER_CHILD = 1000;
    const DEFAULT_REPOSITORY_USER = 'admin';

    /**
     * @var \eZ\Publish\Core\FieldType\XmlText\Persistence\Legacy\ContentModelGateway
     */
    private $gateway;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var RichTextConverter
     */
    private $converter;

    /**
     * @var string
     */
    private $exportDir;

    /**
     * @var array
     */
    private $exportDirFilter;

    /**
     * @var array.
     */
    protected $imageContentTypeIdentifiers;

    /**
     * @var array
     */
    protected $processes = [];

    /**
     * @var bool
     */
    protected $hasProgressBar;

    /**
     * @var \Symfony\Component\Console\Helper\ProgressBar
     */
    protected $progressBar;
    /**
     * @var int
     */
    protected $maxConcurrency;

    /**
     * @var string
     */
    private $phpPath;

    /**
     * @var string
     */
    protected $userLogin;

    /**
     * @var string
     */
    protected $kernelCacheDir;

    public function __construct(Gateway $gateway, RichTextConverter $converter, $kernelCacheDir, LoggerInterface $logger)
    {
        parent::__construct();

        $this->gateway = $gateway;
        $this->converter = $converter;
        $this->kernelCacheDir = $kernelCacheDir;
        $this->logger = $logger;
        $this->exportDir = '';
    }

    protected function configure()
    {
        $this
            ->setName('ezxmltext:convert-to-richtext')
            ->setDescription(<<< EOT
Converts XmlText fields from eZ Publish Platform to RichText fields.

== WARNING ==

ALWAYS make sure you have a restorable backup of your database before using this!
EOT
            )
            ->addOption(
                'concurrency',
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of child processes to use when converting fields.',
                1
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run the converter without writing anything to the database'
            )
            ->addOption(
                'disable-duplicate-id-check',
                null,
                InputOption::VALUE_NONE,
                'Disable the check for duplicate html ids in every attribute. This might decrease execution time on large databases'
            )
            ->addOption(
                'disable-id-value-check',
                null,
                InputOption::VALUE_NONE,
                'Disable the check for non-validating id/name values. This might decrease execution time on large databases'
            )
            ->addOption(
                'export-dir',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to store ezxmltext which the conversion tool is not able to convert. You may use the ezxmltext:import-xml tool to fix such problems'
            )
            ->addOption(
                'export-dir-filter',
                null,
                InputOption::VALUE_OPTIONAL,
                'To be used together with --export-dir option. Specify what kind of problems should be exported:' . PHP_EOL .
                 'notice: ezxmltext contains problems which the conversion tool was able to fix automatically and likly do not need manual intervention' . PHP_EOL .
                 'warning: the conversion tool was able to convert the ezxmltext to valid richtext, but data was maybe altered/removed/added in the process. Manual supervision recommended' . PHP_EOL .
                 'error: the ezxmltext text cannot be converted and manual changes are required.',
                sprintf('%s,%s', LogLevel::WARNING, LogLevel::ERROR)
            )
            ->addOption(
                'test-content-object',
                null,
                InputOption::VALUE_OPTIONAL,
                'Test if converting object with the given id succeeds'
            )
            ->addOption(
                'image-content-types',
                null,
                InputOption::VALUE_OPTIONAL,
                'Comma separated list of content type identifiers which are considered as images when converting embedded tags. Default value is image'
            )
            ->addOption(
                'fix-embedded-images-only',
                null,
                InputOption::VALUE_NONE,
                'Use this option to ensure that embedded images in a database are tagget correctly so that the editor will detect them as such.' . PHP_EOL .
                 'This option is needed if you have an existing ezplatform database which was converted with an earlier version of' . PHP_EOL .
                 '\'ezxmltext:convert-to-richtext\' which did not convert embedded images correctly.'
            )
            ->addOption(
                'no-progress',
                null,
                InputOption::VALUE_NONE,
                'Disable the progress bar.'
            )
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_OPTIONAL,
                'eZ Platform username (with Role containing at least Content policies: read, versionread)',
                self::DEFAULT_REPOSITORY_USER
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->baseExecute($input, $output, $dryRun);
        $this->createCustomTagLog();
        if ($dryRun) {
            $output->writeln('Running in dry-run mode. No changes will actually be written to database');
            if ($this->exportDir !== '') {
                $output->writeln("Note: --export-dir option provided, files will be written to $this->exportDir even in dry-run mode" . PHP_EOL);
            }
        }

        $this->hasProgressBar = !$input->getOption('no-progress');

        $testContentId = $input->getOption('test-content-object');
        if ($testContentId !== null && $this->maxConcurrency !== 1) {
            throw new RuntimeException('Multi concurrency is not supported together with the --test-content-object option');
        }

        if ($input->getOption('fix-embedded-images-only')) {
            $output->writeln('Fixing embedded images only. No other changes are done to the database' . PHP_EOL);
            $this->fixEmbeddedImages($dryRun, $testContentId, $output);

            return;
        }

        if ($testContentId === null) {
            $this->convertFieldDefinitions($dryRun, $output);
        } else {
            $dryRun = true;
            $this->convertFields($dryRun, $testContentId, !$input->getOption('disable-duplicate-id-check'), !$input->getOption('disable-id-value-check'), null, null);

            return;
        }

        $this->processFields($dryRun, !$input->getOption('disable-duplicate-id-check'), !$input->getOption('disable-id-value-check'), $output);
        $this->reportCustomTags($input, $output);
        $this->removeCustomTagLog();
    }

    protected function baseExecute(InputInterface $input, OutputInterface $output, &$dryRun)
    {
        $this->userLogin = $input->getOption('user');
        $this->login();

        $dryRun = false;
        if ($input->getOption('dry-run')) {
            $dryRun = true;
        }

        $this->maxConcurrency = (int) $input->getOption('concurrency');
        if ($this->maxConcurrency < 1) {
            throw new RuntimeException('Invalid value for "--concurrency" given');
        }
        if ($input->getOption('fix-embedded-images-only') && $this->maxConcurrency !== 1) {
            throw new RuntimeException('Multi concurrency is not supported together with the --fix-embedded-images-only option');
        }

        if ($input->getOption('export-dir')) {
            $this->exportDir = $input->getOption('export-dir');
            if (!is_dir($this->exportDir)) {
                mkdir($this->exportDir);
            }
            if (!is_writable($this->exportDir)) {
                new RuntimeException("$this->exportDir is not writable");
            }
        }

        if ($input->getOption('export-dir-filter')) {
            $this->exportDirFilter = explode(',', $input->getOption('export-dir-filter'));
            foreach ($this->exportDirFilter as $filter) {
                switch ($filter) {
                    case LogLevel::NOTICE:
                    case LogLevel::WARNING:
                    case LogLevel::ERROR:
                        break;
                    default:
                        new RuntimeException("Unsupported export dir filter: $this->exportDirFilter");
                }
            }
        }

        if ($input->getOption('image-content-types')) {
            $this->imageContentTypeIdentifiers = explode(',', $input->getOption('image-content-types'));
        } else {
            $this->imageContentTypeIdentifiers = ['image'];
        }
        $imageContentTypeIds = $this->gateway->getContentTypeIds($this->imageContentTypeIdentifiers);
        if (\count($imageContentTypeIds) !== \count($this->imageContentTypeIdentifiers)) {
            throw new RuntimeException('Unable to lookup all content type identifiers, not found: ' . implode(',', array_diff($this->imageContentTypeIdentifiers, array_keys($imageContentTypeIds))));
        }
        $this->converter->setImageContentTypes($imageContentTypeIds);
    }

    protected function getCustomTagLogFileName()
    {
        return $this->kernelCacheDir . \DIRECTORY_SEPARATOR . 'customtags.log';
    }

    protected function createCustomTagLog()
    {
        $this->removeCustomTagLog();
        touch($this->getCustomTagLogFileName());
    }

    protected function writeCustomTagLog()
    {
        $customTagLog = $this->converter->getCustomTagLog();
        if (\count($customTagLog[RichTextConverter::INLINE_CUSTOM_TAG]) > 0) {
            file_put_contents(
                $this->getCustomTagLogFileName(),
                RichTextConverter::INLINE_CUSTOM_TAG . ':' . implode(',', $customTagLog[RichTextConverter::INLINE_CUSTOM_TAG]) . PHP_EOL,
                FILE_APPEND
            );
        }
        if (\count($customTagLog[RichTextConverter::BLOCK_CUSTOM_TAG]) > 0) {
            file_put_contents($this->getCustomTagLogFileName(),
                RichTextConverter::BLOCK_CUSTOM_TAG . ':' . implode(',', $customTagLog[RichTextConverter::BLOCK_CUSTOM_TAG]) . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    protected function removeCustomTagLog()
    {
        $filename = $this->getCustomTagLogFileName();
        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    protected function reportCustomTags(InputInterface $input, OutputInterface $output)
    {
        $customTagsFile = file_get_contents($this->getCustomTagLogFileName());
        $separator = PHP_EOL;
        $line = strtok($customTagsFile, $separator);
        $inlines = [];
        $blocks = [];

        while ($line !== false) {
            // line will have format 'inline:customtag1,customtag2'
            $lineSplit = explode(':', $line);
            switch ($lineSplit[0]) {
                case RichTextConverter::INLINE_CUSTOM_TAG:
                    $inlines = array_merge($inlines, explode(',', $lineSplit[1]));
                    break;
                case RichTextConverter::BLOCK_CUSTOM_TAG:
                    $blocks = array_merge($blocks, explode(',', $lineSplit[1]));
                    break;
            }

            $line = strtok($separator);
        }
        $inlines = array_unique($inlines, SORT_LOCALE_STRING);
        $blocks = array_unique($blocks, SORT_LOCALE_STRING);

        $io = new SymfonyStyle($input, $output);
        $io->title('Custom tags overview');
        $io->text('Below are the list of custom tags found during conversion of ezxmltext fields');
        $io->section('Inline custom tags');
        $io->listing($inlines);
        if (\count($inlines) === 0) {
            $io->text('No inline custom tags converted');
        }

        $io->section('Block custom tags');
        $io->listing($blocks);
        if (\count($blocks) === 0) {
            $io->text('No block custom tags converted');
        }
    }

    protected function getContentTypeIds($contentTypeIdentifiers)
    {
        $query = $this->dbal->createQueryBuilder();

        $query->select('c.identifier, c.id')
            ->from('ezcontentclass', 'c')
            ->where(
                $query->expr()->in(
                    'c.identifier',
                    ':contentTypeIdentifiers'
                )
            )
            ->setParameter(':contentTypeIdentifiers', $contentTypeIdentifiers, Connection::PARAM_STR_ARRAY);

        $statement = $query->execute();

        $columns = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($columns as $column) {
            $result[$column['identifier']] = $column['id'];
        }

        return $result;
    }

    protected function login()
    {
        $userService = $this->getContainer()->get('ezpublish.api.service.user');
        $repository = $this->getContainer()->get('ezpublish.api.repository');
        $permissionResolver = $repository->getPermissionResolver();
        $permissionResolver->setCurrentUserReference($userService->loadUserByLogin($this->userLogin));
    }

    protected function fixEmbeddedImages($dryRun, $contentId, OutputInterface $output)
    {
        $count = $this->gateway->getRowCountOfContentObjectAttributes('ezrichtext', $contentId);

        $output->writeln("Found $count field rows to convert.");
        $this->progressBarStart($output, $count);

        $offset = 0;
        $totalCount = 0;
        do {
            $limit = self::MAX_OBJECTS_PER_CHILD;

            $statement = $this->gateway->getFieldRows('ezrichtext', $contentId, $offset, $limit);
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                if (empty($row['data_text'])) {
                    $inputValue = Value::EMPTY_VALUE;
                } else {
                    $inputValue = $row['data_text'];
                }

                try {
                    $xmlDoc = $this->createDocument($inputValue);
                } catch (RuntimeException $e) {
                    $this->logger->info(
                        $e->getMessage(),
                        [
                            'original' => $inputValue,
                        ]
                    );
                    continue;
                }
                $updatedCount = $this->converter->tagEmbeddedImages($xmlDoc, $row['id']);
                if ($updatedCount > 0) {
                    ++$totalCount;
                }
                $converted = $xmlDoc->saveXML();

                if ($updatedCount === 0) {
                    $this->logger->info(
                        "No embedded image(s) in ezrichtext field #{$row['id']} needed to be updated",
                        [
                            'original' => $inputValue,
                        ]
                    );
                } else {
                    $this->updateFieldRow($dryRun, $row['id'], $row['version'], $converted);

                    $this->logger->info(
                        "Updated $updatedCount embded image(s) in ezrichtext field #{$row['id']}",
                        [
                            'original' => $inputValue,
                            'converted' => $converted,
                        ]
                    );
                }
            }
            $this->progressBarAdvance($offset);
            $offset += self::MAX_OBJECTS_PER_CHILD;
        } while ($offset + self::MAX_OBJECTS_PER_CHILD <= $count);
        $this->progressBarFinish();

        $output->writeln(PHP_EOL . 'Updated ezembed tags in $totalCount field(s)');
    }

    protected function convertFieldDefinitions($dryRun, $output)
    {
        $count = $this->gateway->countContentTypeFieldsByFieldType('ezxmltext');
        $output->writeln("Found $count field definiton to convert.");

        $updateQuery = $this->gateway->getContentTypeFieldTypeUpdateQuery('ezxmltext', 'ezrichtext');
        if (!$dryRun) {
            $updateQuery->execute();
        }
        $output->writeln("Converted $count ezxmltext field definitions to ezrichtext");
    }

    protected function updateFieldRow($dryRun, $id, $version, $datatext)
    {
        $updateQuery = $this->gateway->getUpdateFieldRowQuery($id, $version, $datatext);
        if (!$dryRun) {
            $updateQuery->execute();
        }
    }

    protected function waitForAvailableProcessSlot(OutputInterface $output)
    {
        if (!$this->processSlotAvailable()) {
            $this->waitForChild($output);
        }
    }

    protected function processSlotAvailable()
    {
        return \count($this->processes) < $this->maxConcurrency;
    }

    protected function waitForChild(OutputInterface $output)
    {
        $childEnded = false;
        while (!$childEnded) {
            foreach ($this->processes as $pid => $p) {
                $process = $p['process'];

                if (!$process->isRunning()) {
                    $output->write($process->getIncrementalOutput());
                    $output->write($process->getIncrementalErrorOutput());
                    $childEnded = true;
                    $exitStatus = $process->getExitCode();
                    if ($exitStatus !== 0) {
                        throw new RuntimeException(sprintf('Child process (offset=%s, limit=%s) ended with status code %d. Terminating', $p['offset'], $p['limit'], $exitStatus));
                    }
                    unset($this->processes[$pid]);
                    break;
                }
                $output->write($process->getIncrementalOutput());
                $output->write($process->getIncrementalErrorOutput());
            }
            if (!$childEnded) {
                sleep(1);
            }
        }

        return;
    }

    private function createChildProcess($dryRun, $checkDuplicateIds, $checkIdValues, $offset, $limit, OutputInterface $output)
    {
        $arguments = [
            file_exists('bin/console') ? 'bin/console' : 'app/console',
            'ezxmltext:convert-to-richtext-sub-process',
            "--offset=$offset",
            "--limit=$limit",
            '--image-content-types=' . implode(',', $this->imageContentTypeIdentifiers),
            "--user=$this->userLogin",
            '--export-dir-filter=' . implode(',', $this->exportDirFilter),
        ];

        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit !== null) {
            array_unshift($arguments, "memory_limit=$memoryLimit");
            array_unshift($arguments, '-d');
        }

        if ($dryRun) {
            $arguments[] = '--dry-run';
        }
        if (!$checkDuplicateIds) {
            $arguments[] = '--disable-duplicate-id-check';
        }
        if (!$checkIdValues) {
            $arguments[] = '--disable-id-value-check';
        }
        if ($this->exportDir !== '') {
            $arguments[] = "--export-dir=$this->exportDir";
        }
        if ($output->isVerbose()) {
            $arguments[] = '-v';
        } elseif ($output->isVeryVerbose()) {
            $arguments[] = '-vv';
        } elseif ($output->isDebug()) {
            $arguments[] = '-vvv';
        }

        $process = new ProcessBuilder($arguments);
        $process->setTimeout(null);
        $process->setPrefix($this->getPhpPath());
        $p = $process->getProcess();
        $p->start();

        return $p;
    }

    private function getPhpPath()
    {
        if ($this->phpPath) {
            return $this->phpPath;
        }
        $phpFinder = new PhpExecutableFinder();
        $this->phpPath = $phpFinder->find();
        if (!$this->phpPath) {
            throw new \RuntimeException(
                'The php executable could not be found, it\'s needed for executing parable sub processes, so add it to your PATH environment variable and try again'
            );
        }

        return $this->phpPath;
    }

    protected function dumpOnErrors($errors, $dataText, $contentobjectId, $contentobjectAttribute, $version, $languageCode)
    {
        if (($this->exportDir !== '') && (\count($errors) > 0)) {
            $filterMatch = false;
            $filename = $this->exportDir . \DIRECTORY_SEPARATOR . "ezxmltext_${contentobjectId}_${contentobjectAttribute}_${version}_${languageCode}";

            // Write error log
            foreach ($errors as $logLevel => $logErrors) {
                if (!\in_array($logLevel, $this->exportDirFilter)) {
                    continue;
                }
                $fileFlag = $filterMatch ? FILE_APPEND : 0;
                $filterMatch = true;
                foreach ($logErrors as $logError) {
                    $message = $logError['message'];
                    file_put_contents("$filename.log", "$logLevel: $message\n", $fileFlag);
                    if (\array_key_exists('errors', $logError['context'])) {
                        foreach ($logError['context']['errors'] as $contextError) {
                            file_put_contents("$filename.log", "- context : $contextError\n", FILE_APPEND);
                        }
                    }
                }
            }

            // write ezxmltext dump
            if ($filterMatch) {
                $xmlDoc = $this->createDocument($dataText);
                $xmlDoc->formatOutput = true;
                file_put_contents("$filename.xml", $xmlDoc->saveXML());
            }
        }
    }

    protected function convertFields($dryRun, $contentId, $checkDuplicateIds, $checkIdValues, $offset, $limit)
    {
        $statement = $this->gateway->getFieldRows(['ezxmltext', 'ezrichtext'], $contentId, $offset, $limit);
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($row['data_type_string'] === 'ezrichtext') {
                continue;
            }
            if (empty($row['data_text'])) {
                $inputValue = Value::EMPTY_VALUE;
            } else {
                $inputValue = $row['data_text'];
            }

            try {
                $xmlDoc = $this->createDocument($inputValue);
            } catch (RuntimeException $e) {
                $this->logger->info(
                    $e->getMessage(),
                    [
                        'original' => $inputValue,
                    ]
                );
            }
            $converted = $this->converter->convert($xmlDoc, $checkDuplicateIds, $checkIdValues, $row['id']);
            $this->dumpOnErrors($this->converter->getErrors(), $row['data_text'], $row['contentobject_id'], $row['id'], $row['version'], $row['language_code']);

            $this->updateFieldRow($dryRun, $row['id'], $row['version'], $converted);

            $this->logger->info(
                "Converted ezxmltext field #{$row['id']} to richtext",
                [
                    'original' => $inputValue,
                    'converted' => $converted,
                ]
            );
        }
        $this->writeCustomTagLog();
    }

    protected function progressBarStart(OutputInterface $output, $count)
    {
        if ($this->hasProgressBar) {
            $this->progressBar = new ProgressBar($output, $count);
            $this->progressBar->setFormat('very_verbose');
            $this->progressBar->start();
        }
    }

    protected function progressBarAdvance($step)
    {
        if ($this->hasProgressBar) {
            $this->progressBar->advance($step);
        }
    }

    protected function progressBarFinish()
    {
        if ($this->hasProgressBar) {
            $this->progressBar->finish();
        }
    }

    protected function processFields($dryRun, $checkDuplicateIds, $checkIdValues, OutputInterface $output)
    {
        $output->write('Finding total number of ezxmltext attributes to convert...');
        $ezxmltextCount = $this->gateway->getRowCountOfContentObjectAttributes('ezxmltext', null);
        $output->writeln(" Found $ezxmltextCount");
        $output->write('Finding total number of ezxmltext and ezrichtext attributes...');
        $count = $this->gateway->getRowCountOfContentObjectAttributes(['ezxmltext', 'ezrichtext'], null);
        $output->writeln(" Found $count");

        if ($count < self::MAX_OBJECTS_PER_CHILD * $this->maxConcurrency && $this->maxConcurrency > 1) {
            $objectsPerChild = (int) ceil($count / $this->maxConcurrency);
        } else {
            $objectsPerChild = self::MAX_OBJECTS_PER_CHILD;
        }
        $offset = 0;
        $fork = $this->maxConcurrency > 1;
        $this->progressBarStart($output, $count);

        do {
            $limit = $objectsPerChild;
            if ($fork) {
                $processSlotAvailable = $this->processSlotAvailable();
                $this->waitForAvailableProcessSlot($output);
                if (!$processSlotAvailable) {
                    $this->progressBarAdvance($objectsPerChild);
                }
                $process = $this->createChildProcess($dryRun, $checkDuplicateIds, $checkIdValues, $offset, $limit, $output);
                $this->processes[$process->getPid()] = ['offset' => $offset, 'limit' => $limit, 'process' => $process];
            } else {
                $this->convertFields($dryRun, null, $checkDuplicateIds, $checkIdValues, $offset, $limit);
            }
            $offset += $objectsPerChild;
        } while ($offset <= $count);

        while (\count($this->processes) > 0) {
            $this->waitForChild($output);
            $this->progressBarAdvance($objectsPerChild);
        }
        $this->progressBarFinish();
        $output->writeln(PHP_EOL . "Converted $count ezxmltext fields to richtext");
    }

    protected function createDocument($xmlString)
    {
        $document = new DOMDocument();

        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        // In dev mode, symfony may throw Symfony\Component\Debug\Exception\ContextErrorException
        try {
            $result = $document->loadXml($xmlString);
            if ($result === false) {
                throw new RuntimeException('Unable to parse ezxmltext. Invalid XML format');
            }
        } catch (ContextErrorException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode());
        }

        return $document;
    }
}
