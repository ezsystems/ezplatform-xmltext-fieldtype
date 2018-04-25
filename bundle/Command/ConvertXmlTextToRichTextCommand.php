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

    public function __construct(Connection $dbal, LoggerInterface $logger = null)
    {
        parent::__construct();

        $this->dbal = $dbal;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setName('ezxmltext:convert-to-richtext')
            ->setDescription( <<< EOT
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dryRun = false;
        if ($input->getOption('dry-run')) {
            $output->writeln("Running in dry-run mode. No changes will actually be written to database\n");
            $dryRun = true;
        }

        $testContentObjectId = $input->getOption('test-content-object');

        if ($testContentObjectId === null) {
            $this->convertFieldDefinitions($dryRun, $output);
        } else {
            $dryRun = true;
        }
        $this->convertFields($dryRun, $testContentObjectId, !$input->getOption('disable-duplicate-id-check'), $output);
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
            ->setParameter(':datatypestring','ezxmltext');

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
                ':olddatatypestring' => 'ezxmltext'
            ]);

        if (!$dryRun) {
            $updateQuery->execute();
        }

        $output->writeln("Converted $count ezxmltext field definitions to ezrichtext");
    }

    protected function convertFields($dryRun, $contentObjectId, $checkDuplicateIds, OutputInterface $output)
    {
        $converter = new RichTextConverter($this->logger);
        $query = $this->dbal->createQueryBuilder();
        $query->select('count(a.id)')
            ->from('ezcontentobject_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->setParameter(':datatypestring','ezxmltext');

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
        $count = (int) $statement->fetchColumn();

        $output->writeln("Found $count field rows to convert.");

        $query = $this->dbal->createQueryBuilder();
        $query->select('a.*')
            ->from('ezcontentobject_attribute', 'a')
            ->where(
                $query->expr()->eq(
                    'a.data_type_string',
                    ':datatypestring'
                )
            )
            ->setParameter(':datatypestring','ezxmltext');

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

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (empty($row['data_text'])) {
                $inputValue = Value::EMPTY_VALUE;
            } else {
                $inputValue = $row['data_text'];
            }

            $converted = $converter->convert($this->createDocument($inputValue), $checkDuplicateIds, $row['id']);

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
                    ':datatext' => $converted,
                    ':id' => $row['id'],
                    ':version' => $row['version']
                ]);

            if (!$dryRun) {
                $updateQuery->execute();
            }

            $this->logger->info(
                "Converted ezxmltext field #{$row['id']} to richtext",
                [
                    'original' => $inputValue,
                    'converted' => $converted
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
