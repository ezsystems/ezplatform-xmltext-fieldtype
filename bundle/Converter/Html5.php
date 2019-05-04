<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Converter;

use eZ\Publish\Core\FieldType\XmlText\Converter\Html5 as BaseHtml5Converter;
use eZ\Publish\Core\MVC\ConfigResolverInterface;

/**
 * Adds ConfigResolver awareness to the original Html5 converter.
 * @deprecated Not in use anymore
 */
class Html5 extends BaseHtml5Converter
{
    public function __construct($stylesheet, ConfigResolverInterface $configResolver, array $preConverters = [])
    {
        $customStylesheets = $configResolver->getParameter('fieldtypes.ezxml.custom_xsl');
        $customStylesheets = $customStylesheets ?: [];
        parent::__construct($stylesheet, $customStylesheets, $preConverters);
    }
}
