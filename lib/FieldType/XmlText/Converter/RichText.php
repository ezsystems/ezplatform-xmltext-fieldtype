<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
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
     * @var int[]
     */
    private $imageContentTypes;
    /**
     * @var \eZ\Publish\Core\FieldType\RichText\Validator
     */
    private $validator;

    private $apiRepository;

    public function __construct(LoggerInterface $logger = null, $apiRepository = null, $imageContentTypes = array())
    {
        $this->logger = $logger;
        $this->imageContentTypes = $imageContentTypes;
        $this->apiRepository = $apiRepository;

        $this->converter = new Aggregate(
            [
                new ToRichTextPreNormalize(new Expanding(), new EmbedLinking()),
                new Xslt(
                    './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/stylesheets/ezxml/docbook/docbook.xsl',
                    [
                        [
                            'path' => './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/stylesheets/ezxml/docbook/core.xsl',
                            'priority' => 99,
                        ],
                    ]
                ),
            ]
        );

        $this->validator = new Validator(
            [
                './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/schemas/docbook/ezpublish.rng',
                './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/schemas/docbook/docbook.iso.sch.xsl',
            ]
        );
    }

    /**
     * @param array $imageContentTypes List of ContentType Ids which are considered as images
     */
    public function setImageContentTypes(array $imageContentTypes)
    {
        $this->imageContentTypes = $imageContentTypes;
    }

    protected function removeComments(DOMDocument $document)
    {
        $xpath = new DOMXpath($document);
        $nodes = $xpath->query('//comment()');

        for ($i = 0; $i < $nodes->length; ++$i) {
            $nodes->item($i)->parentNode->removeChild($nodes->item($i));
        }
    }

    protected function reportNonUniqueIds(DOMDocument $document, $contentObjectAttributeId)
    {
        $xpath = new DOMXPath($document);
        $ns = $document->documentElement->namespaceURI;
        $nodes = $xpath->query("//*[contains(@xml:id, 'duplicated_id_')]");
        foreach ($nodes as $node) {
            $id = $node->attributes->getNamedItem('id')->nodeValue;
            // id has format "duplicated_id_foo_bar_idm45226413447104" where "foo_bar" is the duplicated id
            $duplicatedId = substr($id, strlen('duplicated_id_'), strrpos($id, '_') - strlen('duplicated_id_'));
            if ($this->logger !== null) {
                $this->logger->warning("Duplicated id in original ezxmltext for contentobject_attribute.id=$contentObjectAttributeId, automatically generated new id : $duplicatedId --> $id");
            }
        }
    }

    protected function isImageClass($contentId)
    {
        $contentService = $this->apiRepository->getContentService();
        $contentInfo = $contentService->loadContentInfo($contentId);
        return in_array($contentInfo->contentTypeId, $this->imageContentTypes);
    }

    /**
     * Embedded images needs to include an attribute (ezxhtml:class="ez-embed-type-image) in order to be recognized by editor
     *
     * Before calling this function, make sure you are logged in as admin, or at least have access to all the objects
     * being embedded in the $richtextDocument.
     *
     * @param DOMDocument $richtextDocument
     * @return int Number of ezembed tags which where changed
     */
    public function tagEmbeddedImages(DOMDocument $richtextDocument)
    {
        $count = 0;
        $xpath = new DOMXPath($richtextDocument);
        $ns = $richtextDocument->documentElement->namespaceURI;
        $xpath->registerNamespace('doc', $ns);
        $nodes = $xpath->query('//doc:ezembed');
        foreach ($nodes as $node) {
            //href is in format : ezcontent://123
            $href=$node->attributes->getNamedItem('href')->nodeValue;
            $contentId = (int) substr($href, strrpos($href, '/')+1);
            $classAttribute = $node->attributes->getNamedItem('class');
            if ($this->isImageClass($contentId)) {
                if (($classAttribute === null) || (($classAttribute !== null) && ($node->attributes->getNamedItem('class')->nodeValue !== 'ez-embed-type-image'))) {
                    $node->setAttribute('ezxhtml:class', 'ez-embed-type-image');
                    ++$count;
                }
            } else {
                if (($classAttribute !== null) && ($node->attributes->getNamedItem('class')->nodeValue === 'ez-embed-type-image')) {
                    $node->removeAttribute('ezxhtml:class');
                    //$node->setAttribute('ezxhtml:class', 'ez-embed-type-image');
                    ++$count;
                }
            }
        }
        return $count;
    }

    /**
     * Before calling this function, make sure you are logged in as admin, or at least have access to all the objects
     * being embedded in the $inputDocument.
     *
     * @param DOMDocument $inputDocument
     * @param bool $checkDuplicateIds
     * @param null $contentObjectAttributeId
     * @return string
     */
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
        $this->tagEmbeddedImages($convertedDocumentNormalized);

        $errors = $this->validator->validate($convertedDocumentNormalized);

        $result = $convertedDocumentNormalized->saveXML();

        if (!empty($errors) && $this->logger !== null) {
            $this->logger->error(
                "Validation errors when converting ezxmltext for contentobject_attribute.id=$contentObjectAttributeId",
                ['result' => $result, 'errors' => $errors, 'xmlString' => $inputDocument->saveXML()]
            );
        }

        return $result;
    }
}
