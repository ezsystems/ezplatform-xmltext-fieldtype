<?php
/**
 * File containing the eZ\Publish\Core\FieldType\XmlText\InternalLinkValidator class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\FieldType\XmlText;

use DOMDocument;
use DOMXPath;
use eZ\Publish\SPI\Persistence\Content\Handler as ContentHandler;
use eZ\Publish\SPI\Persistence\Content\Location\Handler as LocationHandler;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;

/**
 * Validator for XmlText internal format links.
 */
class InternalLinkValidator
{
    /**
     * @var \eZ\Publish\SPI\Persistence\Content\Handler
     */
    private $contentHandler;

    /**
     * @var \eZ\Publish\SPI\Persistence\Content\Location\Handler;
     */
    private $locationHandler;

    /**
     * InternalLinkValidator constructor.
     * @param \eZ\Publish\SPI\Persistence\Content\Handler $contentHandler
     * @param \eZ\Publish\SPI\Persistence\Content\Location\Handler $locationHandler
     */
    public function __construct(ContentHandler $contentHandler, LocationHandler $locationHandler)
    {
        $this->contentHandler = $contentHandler;
        $this->locationHandler = $locationHandler;
    }

    /**
     * Extracts and validate internal links.
     *
     * @param \DOMDocument $xml
     * @return array
     */
    public function validate(DOMDocument $xml)
    {
        $errors = [];

        $xpath = new DOMXPath($xml);
        foreach ($xpath->query('//link') as $link) {
            if ($link->hasAttribute('object_id')) {
                $objectId = $link->getAttribute('object_id');
                if ($objectId && !$this->validateEzObject($objectId)) {
                    $errors[] = $this->getInvalidLinkError('ezobject', $objectId, $link->getAttribute('anchor_name'));
                }
            } elseif ($link->hasAttribute('node_id')) {
                $nodeId = $link->getAttribute('node_id');
                if ($nodeId && !$this->validateEzNode($nodeId)) {
                    $errors[] = $this->getInvalidLinkError('eznode', $nodeId, $link->getAttribute('anchor_name'));
                }
            }
        }

        return $errors;
    }

    /**
     * Validates link in "ezobject://" format.
     *
     * @param int $objectId
     * @return bool
     */
    protected function validateEzObject($objectId)
    {
        try {
            $this->contentHandler->loadContentInfo($objectId);
        } catch (NotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * Validates link in "eznode://" format.
     *
     * @param mixed $nodeId
     * @return bool
     */
    protected function validateEzNode($nodeId)
    {
        try {
            $this->locationHandler->load($nodeId);
        } catch (NotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * Builds error message for invalid url.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException If given $scheme is not supported.
     *
     * @param string $scheme
     * @param string $target
     * @param string|null $anchorName
     * @return string
     */
    protected function getInvalidLinkError($scheme, $target, $anchorName = null)
    {
        $url = "$scheme://$target" . ($anchorName ? '#' . $anchorName : '');

        switch ($scheme) {
            case 'eznode':
                return sprintf('Invalid link "%s": target node cannot be found', $url);
            case 'ezobject':
                return sprintf('Invalid link "%s": target object cannot be found', $url);
            default:
                throw new InvalidArgumentException($scheme, "Given scheme '{$scheme}' is not supported.");
        }
    }
}
