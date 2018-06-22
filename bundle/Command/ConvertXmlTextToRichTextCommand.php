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
use eZ\Publish\Core\FieldType\XmlText\Value;
use eZ\Publish\Core\FieldType\XmlText\Converter\RichText as RichTextConverter;
use Doctrine\DBAL\Connection;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;

class ConvertXmlTextToRichTextCommand extends ContainerAwareCommand
{
    const MAX_OJBECTS_PER_CHILD = 1000;
    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $dbal;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var RichTextConverter
     */
    private $converter;

    /**
     * @var array.
     */
    protected $imageContentTypeIdentifiers;

    /**
     * @var array
     */
    protected $processes = [];

    /**
     * @var int
     */
    protected $maxConcurrency;

    /**
     * @var string
     */
    private $phpPath;

    public function __construct(Connection $dbal, RichTextConverter $converter, LoggerInterface $logger)
    {
        parent::__construct();

        $this->dbal = $dbal;
        $this->logger = $logger;
        $this->converter = $converter;
    }

    protected function configure()
    {
        $this
            ->setName('ezxmltext:convert-to-richtext')
            ->setDescription(<<< EOT
Converts XmlText fields from eZ Publish Platform to RichText fields.

== WARNING ==

This is a non-finalized work in progress. ALWAYS make sure you have a restorable backup of your database before using it.
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
                "Use this option to ensure that embedded images in a database are tagget correctly so that the editor will detect them as such.\n
                 This option is needed if you have an existing ezplatform database which was converted with an earlier version of\n
                 'ezxmltext:convert-to-richtext' which did not convert embedded images correctly."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->baseExecute($input, $output, $dryRun);
        if ($dryRun) {
            $output->writeln("Running in dry-run mode. No changes will actually be written to database\n");
        }

        $testContentId = $input->getOption('test-content-object');
        if ($testContentId !== null && $this->maxConcurrency !== 1) {
            throw new RuntimeException('Multi concurrency is not supported together with the --test-content-object option');
        }

        if ($input->getOption('fix-embedded-images-only')) {
            $output->writeln("Fixing embedded images only. No other changes are done to the database\n");
            $this->fixEmbeddedImages($dryRun, $testContentId, $output);

            return;
        }

        if ($testContentId === null) {
            $this->convertFieldDefinitions($dryRun, $output);
        } else {
            $dryRun = true;
        }

        $this->processFields($dryRun, $testContentId, !$input->getOption('disable-duplicate-id-check'), !$input->getOption('disable-id-value-check'), $output);
    }

    protected function baseExecute(InputInterface $input, OutputInterface $output, &$dryRun)
    {
        $this->loginAsAdmin();
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

        if ($input->getOption('image-content-types')) {
            $this->imageContentTypeIdentifiers = explode(',', $input->getOption('image-content-types'));
        } else {
            $this->imageContentTypeIdentifiers = ['image'];
        }
        $imageContentTypeIds = $this->getContentTypeIds($this->imageContentTypeIdentifiers);
        if (count($imageContentTypeIds) !== count($this->imageContentTypeIdentifiers)) {
            throw new RuntimeException('Unable to lookup all content type identifiers, found : ' . implode(',', $imageContentTypeIds));
        }
        $this->converter->setImageContentTypes($imageContentTypeIds);
    }

    protected function getContentTypeIds($contentTypeIdentifiers)
    {
        $query = $this->dbal->createQueryBuilder();

        $query->select('c.id')
            ->from('ezcontentclass', 'c')
            ->where(
                $query->expr()->in(
                    'c.identifier',
                    ':contentTypeIdentifiers'
                )
            )
            ->setParameter(':contentTypeIdentifiers', $contentTypeIdentifiers, Connection::PARAM_STR_ARRAY);

        $statement = $query->execute();

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    protected function loginAsAdmin()
    {
        $userService = $this->getContainer()->get('ezpublish.api.service.user');
        $repository = $this->getContainer()->get('ezpublish.api.repository');
        $permissionResolver = $repository->getPermissionResolver();
        $permissionResolver->setCurrentUserReference($userService->loadUserByLogin('admin'));
    }

    protected function fixEmbeddedImages($dryRun, $contentId, OutputInterface $output)
    {
        $count = $this->getRowCountOfContentObjectAttributes('ezrichtext', $contentId);

        $output->writeln("Found $count field rows to convert.");

        $statement = $this->getFieldRows('ezrichtext', $contentId);

        $totalCount = 0;
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
            $count = $this->converter->tagEmbeddedImages($xmlDoc);
            if ($count > 0) {
                ++$totalCount;
            }
            $converted = $xmlDoc->saveXML();

            if ($count === 0) {
                $this->logger->info(
                    "No embedded image(s) in ezrichtext field #{$row['id']} needed to be updated",
                    [
                        'original' => $inputValue,
                    ]
                );
            } else {
                $this->updateFieldRow($dryRun, $row['id'], $row['version'], $converted);

                $this->logger->info(
                    "Updated $count embded image(s) in ezrichtext field #{$row['id']}",
                    [
                        'original' => $inputValue,
                        'converted' => $converted,
                    ]
                );
            }
        }

        $output->writeln("Updated ezembed tags in $totalCount field(s)");
    }

    protected function convertFieldDefinitions($dryRun, OutputInterface $output)
    {
        $query = $this->dbal->createQueryBuilder();
        $query->select('count(a.id)')
            ->from('ezcontentclass_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->setParameter(':datatypestring', 'ezxmltext');

        $statement = $query->execute();
        $count = (int) $statement->fetchColumn();

        $output->writeln("Found $count field definiton to convert.");

        $updateQuery = $this->dbal->createQueryBuilder();
        $updateQuery->update('ezcontentclass_attribute', 'a')
            ->set('a.data_type_string', ':newdatatypestring')
            // was tagPreset in ezxmltext, unused in RichText
            ->set('a.data_text2', ':datatext2')
            ->where(
                $updateQuery->expr()->eq(
                    'a.data_type_string',
                    ':olddatatypestring'
                )
            )
            ->setParameters([
                ':newdatatypestring' => 'ezrichtext',
                ':datatext2' => null,
                ':olddatatypestring' => 'ezxmltext',
            ]);

        if (!$dryRun) {
            $updateQuery->execute();
        }

        $output->writeln("Converted $count ezxmltext field definitions to ezrichtext");
    }

    protected function getRowCountOfContentObjectAttributes($datatypeString, $contentId)
    {
        $query = $this->dbal->createQueryBuilder();
        $query->select('count(a.id)')
            ->from('ezcontentobject_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->setParameter(':datatypestring', $datatypeString);

        if ($contentId !== null) {
            $query->andWhere(
                $query->expr()->eq(
                    'a.contentobject_id',
                    ':contentid'
                )
            )
                ->setParameter(':contentid', $contentId);
        }

        $statement = $query->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * Get the specified field rows.
     * Note that if $contentId !== null, then $offset and $limit will be ignored.
     *
     * @param $datatypeString
     * @param $contentId
     * @param $offset
     * @param $limit
     * @return \Doctrine\DBAL\Driver\Statement|int
     */
    protected function getFieldRows($datatypeString, $contentId, $offset, $limit)
    {
        $query = $this->dbal->createQueryBuilder();
        $query->select('a.*')
            ->from('ezcontentobject_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->orderBy('a.id')
            ->setParameter(':datatypestring', $datatypeString);

        if ($contentId === null) {
            $query->setFirstResult($offset)
                ->setMaxResults($limit);
        } else {
            $query->andWhere(
                $query->expr()->eq(
                    'a.contentobject_id',
                    ':contentid'
                )
            )
                ->setParameter(':contentid', $contentId);
        }

        return $query->execute();
    }

    protected function updateFieldRow($dryRun, $id, $version, $datatext)
    {
        $updateQuery = $this->dbal->createQueryBuilder();
        $updateQuery->update('ezcontentobject_attribute', 'a')
            ->set('a.data_type_string', ':datatypestring')
            ->set('a.data_text', ':datatext')
            ->where(
                $updateQuery->expr()->eq(
                    'a.id',
                    ':id'
                )
            )
            ->andWhere(
                $updateQuery->expr()->eq(
                    'a.version',
                    ':version'
                )
            )
            ->setParameters([
                ':datatypestring' => 'ezrichtext',
                ':datatext' => $datatext,
                ':id' => $id,
                ':version' => $version,
            ]);

        if (!$dryRun) {
            $updateQuery->execute();
        }
    }

    protected function waitForAvailableProcessSlot(OutputInterface $output)
    {
        if (count($this->processes) >= $this->maxConcurrency) {
            $this->waitForChild($output);
        }
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
            sleep(1);
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
        ];
        if ($dryRun) {
            $arguments[] = '--dry-run';
        }
        if (!$checkDuplicateIds) {
            $arguments[] = '--disable-duplicate-id-check';
        }
        if (!$checkIdValues) {
            $arguments[] = '--disable-id-value-check';
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

    protected function convertFields($dryRun, $contentId, $checkDuplicateIds, $checkIdValues, $offset, $limit)
    {
        $statement = $this->getFieldRows('ezxmltext', $contentId, $offset, $limit);
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
            }
            $converted = $this->converter->convert($xmlDoc, $checkDuplicateIds, $checkIdValues, $row['id']);

            $this->updateFieldRow($dryRun, $row['id'], $row['version'], $converted);

            $this->logger->info(
                "Converted ezxmltext field #{$row['id']} to richtext",
                [
                    'original' => $inputValue,
                    'converted' => $converted,
                ]
            );
        }
    }

    protected function processFields($dryRun, $contentId, $checkDuplicateIds, $checkIdValues, OutputInterface $output)
    {
        $count = $this->getRowCountOfContentObjectAttributes('ezxmltext', $contentId);
        $output->writeln("Found $count field rows to convert.");

        $offset = 0;
        $fork = $this->maxConcurrency > 1 && $contentId === null;

        while ($offset + self::MAX_OJBECTS_PER_CHILD <= $count) {
            $limit = self::MAX_OJBECTS_PER_CHILD;
            if ($fork) {
                $this->waitForAvailableProcessSlot($output);
                $process = $this->createChildProcess($dryRun, $checkDuplicateIds, $checkIdValues, $offset, $limit, $output);
                $this->processes[$process->getPid()] = ['offset' => $offset, 'limit' => $limit, 'process' => $process];
            } else {
                $this->convertFields($dryRun, $contentId, $checkDuplicateIds, $checkIdValues, $offset, $limit);
            }
            $offset += self::MAX_OJBECTS_PER_CHILD;
        }

        while (count($this->processes) > 1) {
            $this->waitForChild($output);
        }
        $output->writeln("Converted $count ezxmltext fields to richtext");
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
