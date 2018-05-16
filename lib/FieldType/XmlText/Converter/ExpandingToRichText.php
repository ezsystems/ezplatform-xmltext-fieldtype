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
    protected $containmentMap = array(
        'embed' => array(
            'link' => true,
        ),
        'table' => array(),
        'literal' => array(),
    );

    /**
     * Checks whether a paragraph can be considered as temporary and can then be
     * ignored later.
     *
     * Note : I believe this one could always return false unless when isEmpty() when converting to richtext, but leaving as-is for now.
     *
     * @param \DOMElement $paragraph
     * @return bool
     */
    protected function isTemporary(DOMElement $paragraph)
    {
        return
            $paragraph->hasAttribute('xmlns:tmp')
            && (
                $this->containsBlock($paragraph)
                || $this->isEmpty($paragraph)
            )
            ;
    }
}
