<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace eZ\Publish\Core\FieldType\XmlText\XmlTextStorage\Gateway;

use eZ\Publish\Core\FieldType\XmlText\XmlTextStorage\Gateway;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use eZ\Publish\SPI\Persistence\Content\VersionInfo;
use eZ\Publish\SPI\Persistence\Content\Field;
use eZ\Publish\Core\FieldType\Url\UrlStorage\Gateway as UrlGateway;
use DOMDocument;
use PDO;

class LegacyStorage extends Gateway
{
    /**
     * @var \eZ\Publish\Core\Persistence\Database\DatabaseHandler
     */
    protected $dbHandler;

    public function __construct(DatabaseHandler $dbHandler, UrlGateway $urlGateway)
    {
        parent::__construct($urlGateway);

        $this->dbHandler = $dbHandler;
    }

    /**
     * Populates $field->value with external data.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\Field $field
     */
    public function getFieldData(Field $field)
    {
        if ($field->value->data === null) {
            return;
        }

        $doc = new DOMDocument();
        if (!$doc->loadXML($field->value->data)) {
            return;
        }

        $linkTags = $doc->getElementsByTagName('link');

        if ($linkTags->length > 0) {
            $links = [];

            foreach ($linkTags as $link) {
                $urlId = $link->getAttribute('url_id');
                if (!empty($urlId)) {
                    if (!isset($links[$urlId])) {
                        $links[$urlId] = [];
                    }
                    $links[$urlId][] = $link;
                }
            }

            if (!empty($links)) {
                $linkIdUrlMap = $this->getIdUrlMap(array_keys($links));

                foreach ($linkIdUrlMap as $urlId => $url) {
                    foreach ($links[$urlId] as $link) {
                        $link->setAttribute('url', $url);
                        $link->removeAttribute('url_id');
                    }
                }

                // Store xml changes back to field
                $field->value->data = $doc->saveXML();
            }
        }
    }

    /**
     * Stores data, external to XMLText type.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\VersionInfo $versionInfo
     * @param \eZ\Publish\SPI\Persistence\Content\Field $field
     *
     * @return bool
     */
    public function storeFieldData(VersionInfo $versionInfo, Field $field)
    {
        if ($field->value->data === null) {
            return;
        }

        $doc = new DOMDocument();
        if (!$doc->loadXML($field->value->data)) {
            return;
        }

        // Get all element tag types that contain url's or object_remote_id
        $urls = [];
        $remoteIds = [];
        $elements = [];
        foreach (['link', 'embed', 'embed-inline'] as $tagName) {
            $tags = $doc->getElementsByTagName($tagName);
            if ($tags->length === 0) {
                continue;
            }

            // First loop on $elements to populate $urls & $remoteIds
            /** @var $tag \DOMElement */
            foreach ($tags as $tag) {
                $url = null;
                if ($tag->hasAttribute('url')) {
                    $url = $tag->getAttribute('url');
                } elseif ($tag->hasAttribute('href')) {
                    $url = $tag->getAttribute('href');
                } elseif ($tag->hasAttribute('object_remote_id')) {
                    $remoteIds[$tag->getAttribute('object_remote_id')] = true;
                } else {
                    continue;
                }

                // Keep url unique if it has value
                if ($url) {
                    $urls[$url] = true;
                }

                $elements[] = $tag;
            }
        }
        unset($tags);

        // If we found some elements, fix them to point to internal ids
        if (!empty($elements)) {
            $linksIds = $this->getUrlIdMap(array_keys($urls));
            $objectRemoteIdMap = $this->getObjectId(array_keys($remoteIds));
            $urlLinkSet = [];

            // Now loop again to insert the right value in "url_id" attribute and fix "object_remote_id"
            /** @var $element \DOMElement */
            foreach ($elements as $element) {
                if ($element->hasAttribute('url')) {
                    $url = $element->getAttribute('url');
                    if (!$url) {
                        throw new NotFoundException('<link url=', $url);
                    }

                    // Insert url once if not already existing
                    if (!isset($linksIds[$url])) {
                        $linksIds[$url] = $this->insertUrl($url);
                    }
                    if (!isset($urlLinkSet[$url])) {
                        $this->linkUrl($linksIds[$url], $field->id, $versionInfo->versionNo);
                        $urlLinkSet[$url] = true;
                    }

                    $element->setAttribute('url_id', $linksIds[$url]);
                    $element->removeAttribute('url');
                } elseif ($element->hasAttribute('href')) {
                    $url = $element->getAttribute('href');
                    if (!$url) {
                        throw new NotFoundException('<link href=', $url);
                    }

                    // Insert url once if not already existing
                    if (!isset($linksIds[$url])) {
                        $linksIds[$url] = $this->insertUrl($url);
                    }
                    if (!isset($urlLinkSet[$url])) {
                        $this->linkUrl($linksIds[$url], $field->id, $versionInfo->versionNo);
                        $urlLinkSet[$url] = true;
                    }

                    $element->setAttribute('url_id', $linksIds[$url]);
                    $element->removeAttribute('href');
                } elseif ($element->hasAttribute('object_remote_id')) {
                    $objectRemoteId = $element->getAttribute('object_remote_id');
                    if (!isset($objectRemoteIdMap[$objectRemoteId])) {
                        throw new NotFoundException('object_remote_id', $objectRemoteId);
                    }

                    $element->setAttribute('object_id', $objectRemoteIdMap[$objectRemoteId]);
                    $element->removeAttribute('object_remote_id');
                }
            }

            // Store xml changes back to field
            $field->value->data = $doc->saveXML();
        }

        // Return true if some elements where changed
        return !empty($elements);
    }

    /**
     * Fetches rows in ezcontentobject table referenced by remoteIds in $linksRemoteIds array.
     * Returns as hash with remote id as key and corresponding id as value.
     *
     * @param array $linksRemoteIds
     *
     * @return array
     */
    protected function getObjectId(array $linksRemoteIds)
    {
        $objectRemoteIdMap = [];

        if (!empty($linksRemoteIds)) {
            /** @var $q \eZ\Publish\Core\Persistence\Database\SelectQuery */
            $q = $this->dbHandler->createSelectQuery();
            $q
                ->select('id', 'remote_id')
                ->from('ezcontentobject')
                ->where($q->expr->in('remote_id', $linksRemoteIds));

            $statement = $q->prepare();
            $statement->execute();
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $objectRemoteIdMap[$row['remote_id']] = $row['id'];
            }
        }

        return $objectRemoteIdMap;
    }
}
