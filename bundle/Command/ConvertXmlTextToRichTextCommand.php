<?php
/**
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Command;

use DOMDocument;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use eZ\Publish\Core\FieldType\XmlText\Value;
use eZ\Publish\Core\FieldType\XmlText\Converter\RichText as RichTextConverter;

class ConvertXmlTextToRichTextCommand extends ContainerAwareCommand
{
    /**
     * @var \eZ\Publish\Core\Persistence\Database\DatabaseHandler
     */
    private $db;
    
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(DatabaseHandler $db, LoggerInterface $logger = null)
    {
        parent::__construct();

        $this->db = $db;
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

    function convertFieldDefinitions($dryRun, OutputInterface $output)
    {
        $query = $this->db->createSelectQuery();
        $query->select($query->expr->count('*'));
        $query->from('ezcontentclass_attribute');
        $query->where(
            $query->expr->eq(
                $this->db->quoteIdentifier('data_type_string'),
                $query->bindValue('ezxmltext', null, PDO::PARAM_STR)
            )
        );

        $statement = $query->prepare();
        $statement->execute();
        $count = $statement->fetchColumn();

        $output->writeln("Found $count field definiton to convert.");

        $query = $this->db->createSelectQuery();
        $query->select('*');
        $query->from('ezcontentclass_attribute');
        $query->where(
            $query->expr->eq(
                $this->db->quoteIdentifier('data_type_string'),
                $query->bindValue('ezxmltext', null, PDO::PARAM_STR)
            )
        );

        $statement = $query->prepare();
        $statement->execute();

        $updateQuery = $this->db->createUpdateQuery();
        $updateQuery->update($this->db->quoteIdentifier('ezcontentclass_attribute'));
        $updateQuery->set(
            $this->db->quoteIdentifier('data_type_string'),
            $updateQuery->bindValue('ezrichtext', null, PDO::PARAM_STR)
        );
        // was tagPreset in ezxmltext, unused in RichText
        $updateQuery->set(
            $this->db->quoteIdentifier('data_text2'),
            $updateQuery->bindValue(null, null, PDO::PARAM_STR)
        );
        $updateQuery->where(
            $updateQuery->expr->eq(
                $this->db->quoteIdentifier('data_type_string'),
                $updateQuery->bindValue('ezxmltext', null, PDO::PARAM_STR)
            )
        );

        if (!$dryRun) {
            $updateQuery->prepare()->execute();
        }

        $output->writeln("Converted $count ezxmltext field definitions to ezrichtext");
    }

    function convertFields($dryRun, $contentObjectId, $checkDuplicateIds, OutputInterface $output)
    {
        $converter = new RichTextConverter($this->logger);
        $query = $this->db->createSelectQuery();
        $query->select($query->expr->count('*'));
        $query->from('ezcontentobject_attribute');
        if ($contentObjectId === null) {
            $query->where(
                $query->expr->eq(
                    $this->db->quoteIdentifier('data_type_string'),
                    $query->bindValue('ezxmltext', null, PDO::PARAM_STR)
                )
            );
        } else {
            $query->where(
                $query->expr->eq(
                    $this->db->quoteIdentifier('contentobject_id'),
                    $query->bindValue($contentObjectId, null, PDO::PARAM_STR)
                ),
                $query->expr->eq(
                    $this->db->quoteIdentifier('data_type_string'),
                    $query->bindValue('ezxmltext', null, PDO::PARAM_STR)
                )
            );
        }

        $statement = $query->prepare();
        $statement->execute();
        $count = $statement->fetchColumn();

        $output->writeln("Found $count field rows to convert.");

        $query = $this->db->createSelectQuery();
        $query->select('*');
        $query->from('ezcontentobject_attribute');
        if ($contentObjectId === null) {
            $query->where(
                $query->expr->eq(
                    $this->db->quoteIdentifier('data_type_string'),
                    $query->bindValue('ezxmltext', null, PDO::PARAM_STR)
                )
            );
        } else {
            $query->where(
                $query->expr->eq(
                    $this->db->quoteIdentifier('contentobject_id'),
                    $query->bindValue($contentObjectId, null, PDO::PARAM_STR)
                ),
                $query->expr->eq(
                    $this->db->quoteIdentifier('data_type_string'),
                    $query->bindValue('ezxmltext', null, PDO::PARAM_STR)
                )
            );
        }

        $statement = $query->prepare();
        $statement->execute();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (empty($row['data_text'])) {
                $inputValue = Value::EMPTY_VALUE;
            } else {
                $inputValue = $row['data_text'];
            }

            $converted = $converter->convert($this->createDocument($inputValue), $checkDuplicateIds, $row['id']);

            $updateQuery = $this->db->createUpdateQuery();
            $updateQuery->update($this->db->quoteIdentifier('ezcontentobject_attribute'));
            $updateQuery->set(
                $this->db->quoteIdentifier('data_type_string'),
                $updateQuery->bindValue('ezrichtext', null, PDO::PARAM_STR)
            );
            $updateQuery->set(
                $this->db->quoteIdentifier('data_text'),
                $updateQuery->bindValue($converted, null, PDO::PARAM_STR)
            );
            $updateQuery->where(
                $updateQuery->expr->lAnd(
                    $updateQuery->expr->eq(
                        $this->db->quoteIdentifier('id'),
                        $updateQuery->bindValue($row['id'], null, PDO::PARAM_INT)
                    ),
                    $updateQuery->expr->eq(
                        $this->db->quoteIdentifier('version'),
                        $updateQuery->bindValue($row['version'], null, PDO::PARAM_INT)
                    )
                )
            );
            if (!$dryRun) {
                $updateQuery->prepare()->execute();
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

    function createDocument($xmlString)
    {
        $document = new DOMDocument();

        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        $document->loadXml($xmlString);

        return $document;
    }
}
