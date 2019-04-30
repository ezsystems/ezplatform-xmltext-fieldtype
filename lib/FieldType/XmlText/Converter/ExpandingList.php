<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\FieldType\XmlText\Converter;

use DOMElement;
use DOMNode;

/**
 * Class ExpandingList.
 * This class ensures that lists are untangled from their paragraphs except if the list is nested inside
 * another list.
 *
 * Example :
 * <section>
 *   <paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
 *     <line>This is a some text.</line>
 *     <ul>
 *       <li>
 *         <paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">Item B</paragraph>
 *       </li>
 *     </ul>
 *   </paragraph>
 * </section>
 *
 * will be changed into:
 * <section>
 *   <paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
 *     <line>This is a some text.</line>
 *   </paragraph>
 *   <ul>
 *     <li>
 *       <paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">Item B</paragraph>
 *     </li>
 *   </ul>
 * </section>
 */
class ExpandingList extends ExpandingToRichText
{
    protected $containmentMap = [
        'ul' => [],
        'ol' => [],
    ];

    protected function isTemporary(DOMElement $paragraph)
    {
        return $paragraph->hasAttribute('xmlns:tmp') && $this->isEmpty($paragraph);
    }

    protected function isTangled(DOMNode $node)
    {
        // Paragraphs below a <li> should not be expanded. So simply flagging any tags inside such a paragraph
        // as not tangled.
        if ($node instanceof DOMElement && $node->getAttribute(static::ATTRIBUTE_PARAGRAPH_PARENT) === 'li') {
            return false;
        }

        return parent::isTangled($node);
    }
}
