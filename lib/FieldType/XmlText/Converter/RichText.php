<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace eZ\Publish\Core\FieldType\XmlText\Converter;

use eZ\Publish\Core\FieldType\XmlText\Converter;
use DOMDocument;
use DOMXPath;
use Psr\Log\LoggerInterface;
use eZ\Publish\Core\FieldType\RichText\Converter\Aggregate;
use eZ\Publish\Core\FieldType\RichText\Converter\Ezxml\ToRichTextPreNormalize;
use eZ\Publish\Core\FieldType\RichText\Converter\Xslt;
use eZ\Publish\Core\FieldType\RichText\Validator;

class RichText implements Converter
{
    /**
     * @var \eZ\Publish\Core\FieldType\RichText\Converter
     */
    private $converter;

    /**
     * @var \eZ\Publish\Core\FieldType\RichText\Validator
     */
    private $validator;

    public function __construct(LoggerInterface $logger = null)
    {
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
    function removeComments(DOMDocument $document)
    {
        $xpath = new DOMXpath($document);
        $nodes = $xpath->query('//comment()');

        for ($i = 0; $i < $nodes->length; ++$i) {
            $nodes->item($i)->parentNode->removeChild($nodes->item($i));
        }
    }

    function reportNonUniqueIds(DOMDocument $document, $contentObjectAttributeId)
    {
        $xpath = new DOMXPath($document);
        $ns = $document->documentElement->namespaceURI;
        $nodes = $xpath->query("//*[contains(@xml:id, 'duplicated_id_')]");
        foreach ($nodes as $node) {
            $id=$node->attributes->getNamedItem('id')->nodeValue;
            // id has format "duplicated_id_foo_bar_idm45226413447104" where "foo_bar" is the duplicated id
            $duplicatedId = substr($id, strlen('duplicated_id_'), strrpos($id, '_') - strlen('duplicated_id_'));
            if ($this->logger !== null) {
                $this->logger->warning("Duplicated id in original ezxmltext for contentobject_attribute.id=$contentObjectAttributeId, automatically generated new id : $duplicatedId --> $id");
            }
        }
    }

    public function convert(DOMDocument $inputDocument, $checkDuplicateIds = false, $contentObjectAttributeId = null)
    {
        $this->removeComments($inputDocument);

        $convertedDocument = $this->converter->convert($inputDocument);
        if ($checkDuplicateIds) {
            $this->reportNonUniqueIds($convertedDocument, $contentObjectAttributeId);
        }

        // Needed by some disabled output escaping (eg. legacy ezxml paragraph <line/> elements)
        $convertedDocumentNormalized = new DOMDocument();
        $convertedDocumentNormalized->loadXML($convertedDocument->saveXML());

        $errors = $this->validator->validate($convertedDocument);

        $result = $convertedDocumentNormalized->saveXML();

        if (!empty($errors) && $this->logger !== null) {
            $this->logger->error(
                "Validation errors when converting xmlstring",
                ['result' => $result, 'errors' => $errors, 'xmlString' => $inputDocument->saveXML()]
            );
        }

        return $result;
    }
}
