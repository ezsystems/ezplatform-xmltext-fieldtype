<?php
/**
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Command;

use DOMDocument;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use eZ\Publish\Core\FieldType\XmlText\Value;
use eZ\Publish\Core\FieldType\XmlText\Converter\RichText as RichTextConverter;
use Doctrine\DBAL\Connection;

class ConvertXmlTextToRichTextCommand extends ContainerAwareCommand
{
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

    public function __construct(Connection $dbal, RichTextConverter $converter, LoggerInterface $logger = null)
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
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run the converter without writing anything to the database'
            )
            ->addOption(
                'disable-duplicate-id-check',
                null,
                InputOption::VALUE_NONE,
                'Disable the check for duplicate html ids in every attribute. This might increase execution time on large databases'
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
                'Comma separated list of content types which are considered as images when converting embedded tags. Default value is 27'
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
        $this->loginAsAdmin();
        $dryRun = false;
        if ($input->getOption('dry-run')) {
            $output->writeln("Running in dry-run mode. No changes will actually be written to database\n");
            $dryRun = true;
        }

        $testContentObjectId = $input->getOption('test-content-object');

        if ($input->getOption('image-content-types')) {
            $contentTypes = explode(',', $input->getOption('image-content-types'));
        } else {
            $contentTypes = array(27);
        }
        $this->converter->setImageContentTypes($contentTypes);

        if ($input->getOption('fix-embedded-images-only')) {
            $output->writeln("Fixing embedded images only. No other changes are done to the database\n");
            $this->fixEmbeddedImages($dryRun, $testContentObjectId, $output);
            return;
        }

        if ($testContentObjectId === null) {
            $this->convertFieldDefinitions($dryRun, $output);
        } else {
            $dryRun = true;
        }

        $this->convertFields($dryRun, $testContentObjectId, !$input->getOption('disable-duplicate-id-check'), $output);
    }

    protected function loginAsAdmin()
    {
        $userService = $this->getContainer()->get('ezpublish.api.service.user');
        $permissionResolver = $this->getContainer()->get('date_based_publisher.permission_resolver');
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

            $xmlDoc = $this->createDocument($inputValue);
            $count = $this->converter->tagEmbeddedImages($xmlDoc);
            if ($count > 0) {
                ++$totalCount;
            }
            $converted = $xmlDoc->saveXML();

            if ($count === 0) {
                $this->logger->info(
                    "No embedded image(s) in ezrichtext field #{$row['id']} needed to be updated",
                    [
                        'original' => $inputValue
                    ]
                );

            } else {
                $this->updateFieldRow($dryRun, $row['id'], $row['version'], $converted);

                $this->logger->info(
                    "Updated $count embded image(s) in ezrichtext field #{$row['id']}",
                    [
                        'original' => $inputValue,
                        'converted' => $converted
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

    protected function getRowCountOfContentObjectAttributes($datatypeString, $contentObjectId)
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
            ->setParameter(':datatypestring',$datatypeString);

        if ($contentObjectId !== null) {
            $query->andWhere(
                $query->expr()->eq(
                    'a.contentobject_id',
                    ':contentobjectid'
                )
            )
                ->setParameter(':contentobjectid', $contentObjectId);
        }

        $statement = $query->execute();
        return (int) $statement->fetchColumn();
    }

    /**
     * @param $datatypeString
     * @param $contentId
     * @return \Doctrine\DBAL\Driver\Statement|int
     */
    protected function getFieldRows($datatypeString, $contentId)
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
            ->setParameter(':datatypestring',$datatypeString);

        if ($contentId !== null) {
            $query->andWhere(
                $query->expr()->eq(
                    'a.contentobject_id',
                    ':contentobjectid'
                )
            )
                ->setParameter(':contentobjectid', $contentId);
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
            ->setParameters(array(
                ':datatypestring' => 'ezrichtext',
                ':datatext' => $datatext,
                ':id' => $id,
                ':version' => $version
            ));

        if (!$dryRun) {
            $updateQuery->execute();
        }
    }

    protected function convertFields($dryRun, $contentObjectId, $checkDuplicateIds, OutputInterface $output)
    {
        $count = $this->getRowCountOfContentObjectAttributes('ezxmltext', $contentObjectId);

        $output->writeln("Found $count field rows to convert.");

        $statement = $this->getFieldRows('ezxmltext', $contentObjectId);

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (empty($row['data_text'])) {
                $inputValue = Value::EMPTY_VALUE;
            } else {
                $inputValue = $row['data_text'];
            }

            $converted = $this->converter->convert($this->createDocument($inputValue), $checkDuplicateIds, $row['id']);

            $this->updateFieldRow($dryRun, $row['id'], $row['version'], $converted);

            $this->logger->info(
                "Converted ezxmltext field #{$row['id']} to richtext",
                [
                    'original' => $inputValue,
                    'converted' => $converted,
                ]
            );
        }

        $output->writeln("Converted $count ezxmltext fields to richtext");
    }

    protected function createDocument($xmlString)
    {
        $document = new DOMDocument();

        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        $document->loadXml($xmlString);

        return $document;
    }
}
