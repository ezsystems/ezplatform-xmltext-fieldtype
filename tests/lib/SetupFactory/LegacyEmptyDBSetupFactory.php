<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\SetupFactory;

use eZ\Publish\API\Repository\Tests\SetupFactory\Legacy as CoreLegacySetupFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

/**
 * Used to setup the infrastructure for Repository Public API integration tests,
 * based on Repository with Legacy Storage and Search Engine implementation.
 */
class LegacyEmptyDBSetupFactory extends CoreLegacySetupFactory
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
        $loader->load('../../bundle/Resources/config/services.yml');

        // Service ezxmltext.command.convert_to_richtext requires kernel.cache_dir
        $containerBuilder->setParameter('kernel.cache_dir', __DIR__);
    }

    public function getDatabaseHandler()
    {
        return parent::getDatabaseHandler();
    }

    public function getInitialData()
    {
        $data = parent::getInitialData();
        $tables = [];
        // just get the  table names in the dump, so that insertData() will truncate them
        foreach (array_reverse(array_keys($data)) as $table) {
            $tables[$table] = [];
        }

        return $tables;
    }

    public function resetDB()
    {
        $this->getRepository(true);
    }
}
