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
use DOMNode;
use Psr\Log\LoggerInterface;
use eZ\Publish\Core\FieldType\RichText\Converter\Aggregate;
use eZ\Publish\Core\FieldType\RichText\Converter\Xslt;
use eZ\Publish\Core\FieldType\RichText\Validator;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use Psr\Log\NullLogger;

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

    /**
     * @var []
     */
    private $styleSheets;

    /**
     * Holds the id of the current contentField being converted.
     *
     * @var null|int
     */
    private $currentContentFieldId;

    /**
     * RichText constructor.
     * @param null $apiRepository
     * @param LoggerInterface|null $logger
     */
    public function __construct($apiRepository = null, LoggerInterface $logger = null)
    {
        $this->logger = $logger instanceof LoggerInterface ? $logger : new NullLogger();
        $this->imageContentTypes = [];
        $this->apiRepository = $apiRepository;

        $this->styleSheets = null;
        $this->validator = null;
        $this->converter = null;
    }

    /**
     * @param array|null $customStylesheets
     *    $customStylesheet = [
     *      [
     *        'path'      => (string) Path to .xsl. Required
     *        'priority'  => (int) Priority. Required
     *      ]
     *    ]
     */
    public function setCustomStylesheets(array $customStylesheets = [])
    {
        $this->styleSheets = array_merge_recursive(
            [
                [
                    'path' => __DIR__ . '/../Input/Resources/stylesheets/eZXml2Docbook_core.xsl',
                    'priority' => 99,
                ],
            ],
            $customStylesheets
        );
        $this->converter = null;
    }

    /**
     * @param array $customValidators
     */
    public function setCustomValidators(array $customValidators = [])
    {
        $validators = array_merge(
            [
                './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/schemas/docbook/ezpublish.rng',
            ],
            $customValidators,
            [
                './vendor/ezsystems/ezpublish-kernel/eZ/Publish/Core/FieldType/RichText/Resources/schemas/docbook/docbook.iso.sch.xsl',
            ]
        );
        $this->validator = new Validator($validators);
    }

    protected function getConverter()
    {
        if ($this->styleSheets === null) {
            $this->setCustomStylesheets([]);
        }
        if ($this->validator === null) {
            $this->setCustomValidators([]);
        }
        if ($this->converter === null) {
            $this->converter = new Aggregate(
                [
                    new ToRichTextPreNormalize([new ExpandingToRichText(), new ExpandingList(), new EmbedLinking()]),
                    new Xslt(
                        __DIR__ . '/../Input/Resources/stylesheets/eZXml2Docbook.xsl',
                        $this->styleSheets
                    ),
                ]
            );
        }

        return $this->converter;
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

    protected function reportNonUniqueIds(DOMDocument $document, $contentFieldId)
    {
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query("//*[contains(@xml:id, 'duplicated_id_')]");
        if ($contentFieldId === null) {
            $contentFieldId = '[unknown]';
        }
        foreach ($nodes as $node) {
            $id = $node->attributes->getNamedItem('id')->nodeValue;
            // id has format "duplicated_id_foo_bar_idm45226413447104" where "foo_bar" is the duplicated id
            $duplicatedId = substr($id, strlen('duplicated_id_'), strrpos($id, '_') - strlen('duplicated_id_'));
            if ($this->logger !== null) {
                $this->logger->warning("Duplicated id in original ezxmltext for contentobject_attribute.id=$contentFieldId, automatically generated new id : $duplicatedId --> $id");
            }
        }
    }

    /**
     * @param $id
     * @param bool $isContentId Whatever provided $id is a content id or location id
     * @param $contentFieldId
     * @return bool
     */
    protected function isImageContentType($id, $isContentId, $contentFieldId)
    {
        if ($contentFieldId === null) {
            $contentFieldId = '[unknown]';
        }

        try {
            if ($isContentId) {
                $contentService = $this->apiRepository->getContentService();
                $contentInfo = $contentService->loadContentInfo($id);
            } else {
                $locationService = $this->apiRepository->getLocationService();
                try {
                    $location = $locationService->loadLocation($id);
                } catch (NotFoundException $e) {
                    $this->logger->warning("Unable to find node_id=$id, referred to in embedded tag in contentobject_attribute.id=$contentFieldId.");

                    return false;
                }
                $contentInfo = $location->getContentInfo();
            }
        } catch (NotFoundException $e) {
            $this->logger->warning("Unable to find content_id=$id, referred to in embedded tag in contentobject_attribute.id=$contentFieldId.");

            return false;
        }

        if ($contentInfo === null) {
            return false;
        }

        return in_array($contentInfo->contentTypeId, $this->imageContentTypes);
    }

    /**
     * @param DOMNode $node
     * @param $value
     * @return bool returns true if node was changed
     */
    private function addXhtmlClassValue(DOMNode $node, $value)
    {
        $classAttributes = $node->attributes->getNamedItemNS('http://ez.no/xmlns/ezpublish/docbook/xhtml', 'class');
        if ($classAttributes == null) {
            $node->setAttribute('ezxhtml:class', 'ez-embed-type-image');

            return true;
        }

        $attributes = explode(' ', $classAttributes->nodeValue);

        $key = array_search($value, $attributes);
        if ($key === false) {
            $classAttributes->value = $classAttributes->nodeValue . " $value";

            return true;
        }

        return false;
    }

    /**
     * @param DOMNode $node
     * @param $value
     * @return bool returns true if node was changed
     */
    private function removeXhtmlClassValue(DOMNode $node, $value)
    {
        $classAttributes = $node->attributes->getNamedItemNS('http://ez.no/xmlns/ezpublish/docbook/xhtml', 'class');
        if ($classAttributes == null) {
            return false;
        }

        $attributes = explode(' ', $classAttributes->nodeValue);

        $classNameFound = false;
        $key = array_search($value, $attributes);
        if ($key !== false) {
            unset($attributes[$key]);
            $classNameFound = true;
        }

        if ($classNameFound) {
            if (count($attributes) === 0) {
                $node->removeAttribute('ezxhtml:class');
            } else {
                $classAttributes->value = implode(' ', $attributes);
            }
        }

        return $classNameFound;
    }

    /**
     * Embedded images needs to include an attribute (ezxhtml:class="ez-embed-type-image) in order to be recognized by editor.
     *
     * Before calling this function, make sure you are logged in as admin, or at least have access to all the objects
     * being embedded in the $richtextDocument.
     *
     * @param DOMDocument $richtextDocument
     * @param $contentFieldId
     * @return int Number of ezembed tags which where changed
     */
    public function tagEmbeddedImages(DOMDocument $richtextDocument, $contentFieldId)
    {
        $count = 0;
        $xpath = new DOMXPath($richtextDocument);
        $ns = $richtextDocument->documentElement->namespaceURI;
        $xpath->registerNamespace('doc', $ns);
        $nodes = $xpath->query('//doc:ezembed | //doc:ezembedinline');
        foreach ($nodes as $node) {
            //href is in format : ezcontent://123 or ezlocation://123
            $href = $node->attributes->getNamedItem('href')->nodeValue;
            $isContentId = strpos($href, 'ezcontent') === 0;
            $id = (int) substr($href, strrpos($href, '/') + 1);
            $isImage = $this->isImageContentType($id, $isContentId, $contentFieldId);
            if ($isImage) {
                if ($this->addXhtmlClassValue($node, 'ez-embed-type-image')) {
                    ++$count;
                }
            } else {
                if ($this->removeXhtmlClassValue($node, 'ez-embed-type-image')) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * Check if $inputDocument has any embed|embed-inline tags without node_id or object_id.
     * @param DOMDocument $inputDocument
     */
    protected function checkEmptyEmbedTags(DOMDocument $inputDocument)
    {
        $xpath = new DOMXPath($inputDocument);
        $nodes = $xpath->query('//embed[not(@node_id|@object_id)] | //embed-inline[not(@node_id|@object_id)]');
        if ($nodes->length > 0) {
            $this->logger->warning('Warning: ezxmltext for contentobject_attribute.id=' . $this->currentContentFieldId . 'contains embed or embed-inline tag(s) without node_id or object_id');
        }
    }

    /**
     * Before calling this function, make sure you are logged in as admin, or at least have access to all the objects
     * being embedded in the $inputDocument.
     *
     * @param DOMDocument $inputDocument
     * @param bool $checkDuplicateIds
     * @param null|int $contentFieldId
     * @return string
     */
    public function convert(DOMDocument $inputDocument, $checkDuplicateIds = false, $contentFieldId = null)
    {
        $this->removeComments($inputDocument);

        $this->checkEmptyEmbedTags($inputDocument);
        $convertedDocument = $this->getConverter()->convert($inputDocument);
        if ($checkDuplicateIds) {
            $this->reportNonUniqueIds($convertedDocument, $contentFieldId);
        }

        // Needed by some disabled output escaping (eg. legacy ezxml paragraph <line/> elements)
        $convertedDocumentNormalized = new DOMDocument();
        $convertedDocumentNormalized->loadXML($convertedDocument->saveXML());
        $this->tagEmbeddedImages($convertedDocumentNormalized, $contentFieldId);

        $errors = $this->validator->validate($convertedDocumentNormalized);

        $result = $convertedDocumentNormalized->saveXML();

        if (!empty($errors) && $this->logger !== null) {
            $this->logger->error(
                "Validation errors when converting ezxmltext for contentobject_attribute.id=$contentFieldId",
                ['result' => $result, 'errors' => $errors, 'xmlString' => $inputDocument->saveXML()]
            );
        }

        return $result;
    }
}
