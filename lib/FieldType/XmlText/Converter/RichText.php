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
use DOMElement;
use DOMXPath;
use DOMNode;
use Psr\Log\LoggerInterface;
use eZ\Publish\Core\FieldType\RichText\Converter\Aggregate;
use eZ\Publish\Core\FieldType\RichText\Converter\Xslt;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use Psr\Log\NullLogger;
use Psr\Log\LogLevel;
use Symfony\Component\Debug\Exception\ContextErrorException;
use eZ\Publish\API\Repository\Repository;
use EzSystems\EzPlatformRichText\eZ\RichText\Validator\Validator;

class RichText implements Converter
{
    const INLINE_CUSTOM_TAG = 'inline';
    const BLOCK_CUSTOM_TAG = 'block';
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
    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    private $apiRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var []
     */
    private $styleSheets;

    /**
     * @var []
     */
    private $errors;

    /**
     * @var []
     */
    private $customTagsLog;

    /**
     * RichText constructor.
     * @param Repository $apiRepository
     * @param LoggerInterface|null $logger
     * @param Validator $validator
     */
    public function __construct(
        Repository $apiRepository = null,
        LoggerInterface $logger = null,
        Validator $validator = null
    ) {
        $this->validator = $validator;

        $this->logger = $logger instanceof LoggerInterface ? $logger : new NullLogger();
        $this->imageContentTypes = [];
        $this->apiRepository = $apiRepository;

        $this->styleSheets = null;
        $this->converter = null;
        $this->customTagsLog = [self::INLINE_CUSTOM_TAG => [], self::BLOCK_CUSTOM_TAG => []];
    }

    /**
     * @param string $customTagType
     * @param string $customTagName
     */
    protected function logCustomTag($customTagType, $customTagName)
    {
        $this->customTagsLog[$customTagType][] = $customTagName;
    }

    public function getCustomTagLog()
    {
        return $this->customTagsLog;
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

    protected function getConverter()
    {
        if ($this->styleSheets === null) {
            $this->setCustomStylesheets([]);
        }
        if ($this->converter === null) {
            $this->converter = new Aggregate(
                [
                    new ToRichTextPreNormalize([new ExpandingToRichText(), new ExpandingList(), new EmbedLinking(), new TableToRichText()]),
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
            $duplicatedId = substr($id, \strlen('duplicated_id_'), strrpos($id, '_') - \strlen('duplicated_id_'));
            $this->log(LogLevel::WARNING, "Duplicated id in original ezxmltext for contentobject_attribute.id=$contentFieldId, automatically generated new id : $duplicatedId --> $id");
        }
    }

    protected function validateAttributeValues(DOMDocument $document, $contentFieldId)
    {
        $xpath = new DOMXPath($document);
        $whitelist1st = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_';
        $replaceStr1st = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $whitelist = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';
        $replaceStr = '';
        /*
         * We want to pick elements which has id value
         *  #1 not starting with a..z or '_'
         *  #2 not a..z, '0..9', '_' or '-' after 1st character
         * So, no xpath v2 to our disposal...
         * 1st line : we check the 1st char(substring) in id, converts it to 'a' if it in whitelist(translate), then check if it string now starts with 'a'(starts-with), then we invert result(not)
         *   : So we replace first char with 'a' if it is whitelisted, then we select the element if id value does not start with 'a'
         * 2nd line:  now we check remaining(omit 1st char) part of string (substring), removes any character that *is* whitelisted(translate), then check if there are any non-whitelisted characters left(string-lenght)
         * 3rd line: Due to the not() in 1st line, we pick all elements not matching that 1st line. That also includes elements not having a xml:id at all..
         *   : So, we want to make sure we only pick elements which has a xml:id attribute.
         */
        $nodes = $xpath->query("//*[
            (
                not(starts-with(translate(substring(@xml:id, 1, 1), '$whitelist1st', '$replaceStr1st'), 'a')) 
                or string-length(translate(substring(@xml:id, 2), '$whitelist', '$replaceStr')) > 0
            ) and string-length(@xml:id) > 0]");

        if ($contentFieldId === null) {
            $contentFieldId = '[unknown]';
        }
        foreach ($nodes as $node) {
            $orgValue = $node->attributes->getNamedItem('id')->nodeValue;
            $newValue = 'rewrite_' . $node->attributes->getNamedItem('id')->nodeValue;
            $newValue = preg_replace("/[^$whitelist]/", '_', $newValue);
            $node->attributes->getNamedItem('id')->nodeValue = $newValue;
            $this->log(LogLevel::WARNING, "Replaced non-validating id value in richtext for contentobject_attribute.id=$contentFieldId, changed from : $orgValue --> $newValue");
        }
    }

    /**
     * @param $id
     * @param bool $isContentId Whatever provided $id is a content id or location id
     * @param int|null $contentFieldId
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
                    $this->log(LogLevel::WARNING, "Unable to find node_id=$id, referred to in embedded tag in contentobject_attribute.id=$contentFieldId.");

                    return false;
                }
                $contentInfo = $location->getContentInfo();
            }
        } catch (NotFoundException $e) {
            $this->log(LogLevel::WARNING, "Unable to find content_id=$id, referred to in embedded tag in contentobject_attribute.id=$contentFieldId.");

            return false;
        }

        if ($contentInfo === null) {
            return false;
        }

        return \in_array($contentInfo->contentTypeId, $this->imageContentTypes);
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
            if (\count($attributes) === 0) {
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
     * @param int|null $contentFieldId
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
     * @param int|null $contentFieldId
     */
    protected function checkEmptyEmbedTags(DOMDocument $inputDocument, $contentFieldId)
    {
        $xpath = new DOMXPath($inputDocument);
        $nodes = $xpath->query('//embed[not(@node_id|@object_id)] | //embed-inline[not(@node_id|@object_id)]');
        if ($nodes->length > 0) {
            $this->log(LogLevel::WARNING, 'ezxmltext for contentobject_attribute.id=' . $contentFieldId . 'contains embed or embed-inline tag(s) without node_id or object_id');
        }
    }

    /**
     * We need to lookup object_id/node_id for links which refers to object_remote_id or node_object_id.
     *
     * Before calling this function, make sure you are logged in as admin, or at least have access to all the objects
     * being linked to in the $document.

     * @param DOMDocument $document
     * @param int|null $contentFieldId
     */
    protected function fixLinksWithRemoteIds(DOMDocument $document, $contentFieldId)
    {
        $xpath = new DOMXPath($document);

        // Get all link elements except those handled directly by xslt
        $xpathExpression = '//link[not(@url_id) and not(@node_id) and not(@object_id) and not(@anchor_name) and not(@href)]';

        $links = $xpath->query($xpathExpression);

        foreach ($links as $link) {
            if ($link->hasAttribute('object_remote_id')) {
                $remote_id = $link->getAttribute('object_remote_id');
                try {
                    $contentInfo = $this->apiRepository->getContentService()->loadContentInfoByRemoteId($remote_id);
                    $link->setAttribute('object_id', $contentInfo->id);
                } catch (NotFoundException $e) {
                    // The link has to point to somewhere in order to be valid... Pointing to current page
                    $link->setAttribute('href', '#');
                    $this->log(LogLevel::WARNING, "Unable to find content object with remote_id=$remote_id (so rewriting to href=\"#\"), when converting link where contentobject_attribute.id=$contentFieldId.");
                }
                continue;
            }

            if ($link->hasAttribute('node_remote_id')) {
                $remote_id = $link->getAttribute('node_remote_id');
                try {
                    $location = $this->apiRepository->getLocationService()->loadLocationByRemoteId($remote_id);
                    $link->setAttribute('node_id', $location->id);
                } catch (NotFoundException $e) {
                    // The link has to point to somewhere in order to be valid... Pointing to current page
                    $link->setAttribute('href', '#');
                    $this->log(LogLevel::WARNING, "Unable to find node with remote_id=$remote_id (so rewriting to href=\"#\"), when converting link where contentobject_attribute.id=$contentFieldId.");
                }
                continue;
            }
            // The link has to point to somewhere in order to be valid... Pointing to current page
            $link->setAttribute('href', '#');
            $this->log(LogLevel::WARNING, "Unknown linktype detected when converting link where contentobject_attribute.id=$contentFieldId.");
        }
    }

    /**
     * ezxmltext may contain link elements below another link element. This method flattens such structure.
     *
     * @param DOMDocument $document
     * @param int|null $contentFieldId
     */
    protected function flattenLinksInLinks(DOMDocument $document, $contentFieldId)
    {
        $xpath = new DOMXPath($document);

        // Get all link elements which are child of a link
        $xpathExpression = '//link[parent::link]';

        $links = $xpath->query($xpathExpression);

        foreach ($links as $link) {
            // Move link to parent
            $targetElement = $link->parentNode->parentNode;
            $parentLink = $link->parentNode;
            $targetElement->insertBefore($link, $parentLink);

            // We want parent link to be listed first.
            $targetElement->insertBefore($parentLink, $link);

            $this->log(LogLevel::NOTICE, "Found nested links. Flatten links where contentobject_attribute.id=$contentFieldId");
        }
    }

    protected function findParent(DOMElement $element, $parentElementName)
    {
        $parent = $element;
        do {
            $parent = $parent->parentNode;
        } while ($parent !== null && $parent->localName !== $parentElementName);

        return $parent;
    }

    protected function moveEmbedsInHeaders(DOMDocument $document, $contentFieldId)
    {
        $xpath = new DOMXPath($document);

        // Get all embed elements which are child of a header
        $xpathExpression = '//header//embed';

        $embeds = $xpath->query($xpathExpression);

        foreach ($embeds as $embed) {
            $header = $this->findParent($embed, 'header');
            // Move embed before header
            $targetElement = $header->parentNode;
            $targetElement->insertBefore($embed, $header);

            // swap positions of embed and header
            $targetElement->insertBefore($header, $embed);

            $this->log(LogLevel::NOTICE, "Found embed(s) inside header tag. Embed(s) where moved outside header where contentobject_attribute.id=$contentFieldId");
        }
    }

    /**
     * No paragraph elements in ezxmltext should ever have the ez-temporary attribute before start converting.
     * Nevertheless, some legacy databases still might have that....
     * Those needs to be removed as we use ez-temporary for internal housekeeping.
     *
     * @param DOMDocument $document
     * @param int|null $contentFieldId
     */
    protected function removeEzTemporaryAttributes(DOMDocument $document, $contentFieldId)
    {
        $xpath = new DOMXPath($document);

        // Get all paragraphs which has a "ez-temporary" attribute.
        $xpathExpression = '//paragraph[@ez-temporary]';

        $elements = $xpath->query($xpathExpression);

        foreach ($elements as $element) {
            $element->removeAttribute('ez-temporary');
            $this->log(LogLevel::NOTICE, "Found ez-temporary attribute in a ezxmltext paragraphs. Removing such attribute where contentobject_attribute.id=$contentFieldId");
        }
    }

    protected function log($logLevel, $message, $context = [])
    {
        $this->logger->log($logLevel, $message, $context);
        switch ($logLevel) {
            case LogLevel::EMERGENCY:
            case LogLevel::ALERT:
            case LogLevel::CRITICAL:
            case LogLevel::ERROR:
            case LogLevel::WARNING:
            case LogLevel::NOTICE:
            case LogLevel::INFO:
            case LogLevel::DEBUG:
                break;
            default:
                throw new \Exception("Invalid log level: $logLevel");
        }
        $this->errors[$logLevel][] = ['message' => $message, 'context' => $context];
    }

    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param DOMDocument $document
     * @param int|null $contentFieldId
     */
    protected function writeWarningOnNonSupportedCustomTags(DOMDocument $document, $contentFieldId)
    {
        $xpath = new DOMXPath($document);

        $xpathExpression = '//custom';

        $elements = $xpath->query($xpathExpression);

        foreach ($elements as $element) {
            $customTagName = $element->getAttribute('name');
            $parent = $element->parentNode;
            $blockCustomTag = ($parent->localName === 'paragraph' && $parent->hasAttribute('ez-temporary')) || $parent->localName === 'section';

            // These legacy custom tags are not custom tags in richtext
            if (\in_array($customTagName, ['quote', 'underline', 'strike', 'sub', 'sup'])) {
                continue;
            }

            if (!$blockCustomTag) {
                $this->logCustomTag(self::INLINE_CUSTOM_TAG, $customTagName);
            } elseif ($parent->localName === 'section') {
                $this->log(LogLevel::WARNING, "Custom tag '$customTagName' converted to block custom tag. It might have been inline custom tag in legacy DB where contentobject_attribute.id=$contentFieldId");
                $this->logCustomTag(self::BLOCK_CUSTOM_TAG, $customTagName);
            } else {
                $this->logCustomTag(self::BLOCK_CUSTOM_TAG, $customTagName);
            }
        }
    }

    /**
     * CDATA's content cannot contain the sequence ']]>' as that will terminate the CDATA section.
     * So, if the end sequence ']]>' appears in the string, we split the text into multiple CDATA sections.
     *
     * @param DOMDocument $document
     */
    protected function encodeLiteral(DOMDocument $document)
    {
        $xpath = new DOMXPath($document);

        $xpathExpression = '//literal[not(@class="html")]';

        $elements = $xpath->query($xpathExpression);

        foreach ($elements as $element) {
            $element->textContent = str_replace(']]>', ']]]]><![CDATA[>', $element->textContent);
        }
    }

    /**
     * Before calling this function, make sure you are logged in as admin, or at least have access to all the objects
     * being embedded and linked to in the $inputDocument.
     *
     * @param DOMDocument $inputDocument
     * @param bool $checkDuplicateIds
     * @param bool $checkIdValues
     * @param int|null $contentFieldId
     * @return string
     * @throws \Exception
     */
    public function convert(DOMDocument $inputDocument, $checkDuplicateIds = false, $checkIdValues = false, $contentFieldId = null)
    {
        $this->errors = [];
        $this->removeEzTemporaryAttributes($inputDocument, $contentFieldId);
        $this->removeComments($inputDocument);
        $this->checkEmptyEmbedTags($inputDocument, $contentFieldId);
        $this->fixLinksWithRemoteIds($inputDocument, $contentFieldId);
        $this->flattenLinksInLinks($inputDocument, $contentFieldId);
        $this->moveEmbedsInHeaders($inputDocument, $contentFieldId);
        $this->encodeLiteral($inputDocument);

        try {
            $convertedDocument = $this->getConverter()->convert($inputDocument);
        } catch (\Exception $e) {
            $this->log(LogLevel::ERROR,
                "Unable to convert ezmltext for contentobject_attribute.id=$contentFieldId",
                ['errors' => [$e->getMessage()]]
            );
            throw $e;
        }
        $this->writeWarningOnNonSupportedCustomTags($inputDocument, $contentFieldId);
        if ($checkDuplicateIds) {
            $this->reportNonUniqueIds($convertedDocument, $contentFieldId);
        }
        if ($checkIdValues) {
            $this->validateAttributeValues($convertedDocument, $contentFieldId);
        }

        // Needed by some disabled output escaping (eg. legacy ezxml paragraph <line/> elements)
        $convertedDocumentNormalized = new DOMDocument();
        try {
            // If env=dev, Symfony will throw ContextErrorException on line below if xml is invalid
            $result = $convertedDocumentNormalized->loadXML($convertedDocument->saveXML());
            if ($result === false) {
                $this->log(LogLevel::ERROR,
                    "Unable to convert ezmltext for contentobject_attribute.id=$contentFieldId",
                    ['result' => $convertedDocument->saveXML(), 'errors' => ['Unable to parse converted richtext output. See warning in logs or use --env=dev in order to se more verbose output.'], 'xmlString' => $inputDocument->saveXML()]
                );
            }
        } catch (ContextErrorException $e) {
            $this->log(LogLevel::ERROR,
                "Unable to convert ezmltext for contentobject_attribute.id=$contentFieldId",
                ['result' => $convertedDocument->saveXML(), 'errors' => [$e->getMessage()], 'xmlString' => $inputDocument->saveXML()]
            );
            $result = false;
        }

        if ($result) {
            $this->tagEmbeddedImages($convertedDocumentNormalized, $contentFieldId);

            $errors = $this->validator->validate($convertedDocumentNormalized);

            $result = $convertedDocumentNormalized->saveXML();

            if (!empty($errors)) {
                $this->log(LogLevel::ERROR,
                    "Validation errors when converting ezxmltext for contentobject_attribute.id=$contentFieldId",
                    ['result' => $result, 'errors' => $errors, 'xmlString' => $inputDocument->saveXML()]
                );
            }
        }

        return $result;
    }
}
