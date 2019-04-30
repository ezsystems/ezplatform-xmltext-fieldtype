<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Templating\Twig\Extension;

use eZ\Publish\Core\FieldType\XmlText\Converter\Html5 as Html5Converter;
use Twig_Extension;
use Twig_SimpleFilter;

class XmlTextExtension extends Twig_Extension
{
    /**
     * @var Html5Converter
     */
    private $xmlTextConverter;

    public function __construct(Html5Converter $xmlTextConverter)
    {
        $this->xmlTextConverter = $xmlTextConverter;
    }

    public function getName()
    {
        return 'ezpublish.xml_text';
    }

    public function getFilters()
    {
        return [
            new Twig_SimpleFilter(
                'xmltext_to_html5',
                [$this, 'xmlTextToHtml5'],
                ['is_safe' => ['html']]
            ),
        ];
    }

    /**
     * Implements the "xmltext_to_html5" filter.
     *
     * @param \DOMDocument $xmlData
     *
     * @return string
     */
    public function xmltextToHtml5($xmlData)
    {
        return $this->xmlTextConverter->convert($xmlData);
    }
}
