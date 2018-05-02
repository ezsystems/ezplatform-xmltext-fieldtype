<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle;

use EzSystems\EzPlatformXmlTextFieldTypeBundle\DependencyInjection\Compiler\XmlTextConverterPass;
use EzSystems\EzPlatformXmlTextFieldTypeBundle\DependencyInjection\Configuration\Parser as ConfigParser;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class EzSystemsEzPlatformXmlTextFieldTypeBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new XmlTextConverterPass());

        /**
         * @var \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\EzPublishCoreExtension
         */
        $eZExtension = $container->getExtension('ezpublish');
        $eZExtension->addConfigParser(new ConfigParser\FieldType\XmlText());
        $eZExtension->addDefaultSettings(__DIR__ . '/Resources/config', ['default_settings.yml']);
    }
}
