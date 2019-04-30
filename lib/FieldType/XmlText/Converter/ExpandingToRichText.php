<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\FieldType\XmlText\Converter;

use DOMElement;

/**
 * Class ExpandingToRichText.
 *
 * This class is used when preparing for xslt transformation of ezxmltext to richtext.
 */
class ExpandingToRichText extends Expanding
{
    /**
     * Holds map of the elements that expand the paragraph.
     *
     * Name of the element is first level key, second level keys
     * hold names of the elements that CAN wrap it, and which will be kept
     * as wrappers when paragraph is expanded.
     *
     * @var array
     */
    protected $containmentMap = [
        'embed' => [
            'link' => true,
        ],
        'table' => [],
        'literal' => [],
        'header' => [],
    ];

    /**
     * Checks whether a paragraph can be considered as temporary and can then be
     * ignored later.
     *
     * @param \DOMElement $paragraph
     * @return bool
     */
    protected function isTemporary(DOMElement $paragraph)
    {
        return
            $paragraph->hasAttribute('xmlns:tmp')
            && !$this->containsText($paragraph)
            && (
                $this->containsBlock($paragraph)
                || $this->containsCustomTag($paragraph)
                || $this->isEmpty($paragraph)
            )
            ;
    }

    protected function containsCustomTag(DOMElement $paragraph)
    {
        //Safety pin; Custom tags should be the only element inside the paragraph
        // Also, paragraph might be empty...
        if ($paragraph->childNodes->length !== 1) {
            return false;
        }
        if ($paragraph->childNodes->item(0)->localName === 'custom') {
            return true;
        }

        return false;
    }

    protected function containsText(DOMElement $paragraph)
    {
        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof \DOMText) {
                return true;
            } elseif ($child instanceof \DOMElement) {
                switch ($child->localName) {
                    case 'strong':
                    case 'emphasize':
                    case 'underline':
                    case 'link':
                        return true;
                    case 'custom':
                        switch ($child->getAttribute('name')) {
                            case 'underline':
                            case 'strike':
                            case 'sub':
                            case 'sup':
                                return true;
                        }
                        break;
                }
            }
        }

        return false;
    }
}
