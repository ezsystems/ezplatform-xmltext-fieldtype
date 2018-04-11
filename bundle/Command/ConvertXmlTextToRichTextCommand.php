<?php
/**
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Command;

use DOMDocument;
use DOMXPath;
use eZ\Publish\Core\FieldType\RichText\Converter;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use eZ\Publish\Core\FieldType\RichText\Converter\Aggregate;
use eZ\Publish\Core\FieldType\XmlText\Converter\Expanding;
use eZ\Publish\Core\FieldType\RichText\Converter\Ezxml\ToRichTextPreNormalize;
use eZ\Publish\Core\FieldType\XmlText\Converter\EmbedLinking;
use eZ\Publish\Core\FieldType\RichText\Converter\Xslt;
use eZ\Publish\Core\FieldType\RichText\Validator;
use eZ\Publish\Core\FieldType\XmlText\Value;

class ConvertXmlTextToRichTextCommand extends ContainerAwareCommand
{
    /**
     * @var \eZ\Publish\Core\Persistence\Database\DatabaseHandler
     */
    private $db;
    
    /**
     * @var \eZ\Publish\Core\FieldType\RichText\Converter
     */
    private $converter;
    
    /**
     * @var \eZ\Publish\Core\FieldType\RichText\Validator
     */
    private $validator;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(DatabaseHandler $db, LoggerInterface $logger = null)
    {
        parent::__construct();

        $this->db = $db;
        $this->logger = $logger;

        $this->converter = new Aggregate(
            array(
                new ToRichTextPreNormalize(new Expanding(), new EmbedLinking()),
                new Xslt(
                    './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/stylesheets/ezxml/docbook/docbook.xsl',
                    array(
                        array(
                            'path' => './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/stylesheets/ezxml/docbook/core.xsl',
                            'priority' => 99,
                        ),
                    )
                ),
            )
        );

        $this->validator = new Validator(
            array(
                './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/schemas/docbook/ezpublish.rng',
                './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/schemas/docbook/docbook.iso.sch.xsl',
            )
        );
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
        $this->convertFields($dryRun, $testContentObjectId, $output);
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

    function convertFields($dryRun, $contentObjectId, OutputInterface $output)
    {
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

            $converted = $this->convert($inputValue);

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

    function removeComments(DOMDocument $document)
    {
        $xpath = new DOMXpath($document);
        $nodes = $xpath->query('//comment()');

        for ($i = 0; $i < $nodes->length; ++$i) {
            $nodes->item($i)->parentNode->removeChild($nodes->item($i));
        }
    }

    function convert($xmlString)
    {
        $inputDocument = $this->createDocument($xmlString);

        $this->removeComments($inputDocument);

        $convertedDocument = $this->converter->convert($inputDocument);

        // Needed by some disabled output escaping (eg. legacy ezxml paragraph <line/> elements)
        $convertedDocumentNormalized = new DOMDocument();
        $convertedDocumentNormalized->loadXML($convertedDocument->saveXML());

        $errors = $this->validator->validate($convertedDocument);

        $result = $convertedDocumentNormalized->saveXML();

        if (!empty($errors)) {
            $this->logger->error(
                "Validation errors when converting xmlstring",
                ['result' => $result, 'errors' => $errors, 'xmlString' => $xmlString]
            );
        }

        return $result;
    }
}
