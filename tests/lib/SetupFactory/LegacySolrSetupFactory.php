<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\SetupFactory;

use EzSystems\EzPlatformSolrSearchEngine\Tests\SetupFactory\LegacySetupFactory as SolrLegacySetupFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

/**
 * Used to setup the infrastructure for Repository Public API integration tests,
 * based on Repository with Legacy Storage Engine implementation.
 */
class LegacySolrSetupFactory extends SolrLegacySetupFactory
{
    protected function externalBuildContainer(ContainerBuilder $containerBuilder)
    {
        $loader = new YamlFileLoader(
            $containerBuilder,
            new FileLocator(__DIR__ . '/../../../lib/settings')
        );

        $loader->load('storage_engines/legacy/external_storage_gateways.yml');
        $loader->load('storage_engines/legacy/field_value_converters.yml');
        $loader->load('fieldtype_external_storages.yml');
        $loader->load('fieldtypes.yml');
        $loader->load('indexable_fieldtypes.yml');

        parent::externalBuildContainer($containerBuilder);
    }
}
