<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Tests\DependencyInjection\Configuration\Parser\FieldType;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\EzPublishCoreExtension;
use eZ\Bundle\EzPublishCoreBundle\Tests\DependencyInjection\Configuration\Parser\AbstractParserTestCase;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use EzSystems\EzPlatformXmlTextFieldTypeBundle\DependencyInjection\Configuration\Parser\FieldType\XmlText as XmlTextConfigParser;
use Symfony\Component\Yaml\Yaml;

class XmlTextTest extends AbstractParserTestCase
{
    /**
     * Return an array of container extensions you need to be registered for each test (usually just the container
     * extension you are testing.
     *
     * @return ExtensionInterface[]
     */
    protected function getContainerExtensions()
    {
        $extension = new EzPublishCoreExtension([new XmlTextConfigParser()]);

        $extension->addDefaultSettings(
            __DIR__ . '/../../../../../../bundle/Resources/config',
            ['default_settings.yml']
        );

        return [
            $extension,
        ];
    }

    protected function getMinimalConfiguration()
    {
        return Yaml::parse(file_get_contents(__DIR__ . '/../../../Fixtures/ezpublish_minimal.yml'));
    }

    /**
     * @dataProvider xmlTextSettingsProvider
     */
    public function testXmlTextSettings(array $config, array $expected)
    {
        $this->load(
            [
                'system' => [
                    'ezdemo_site' => $config,
                ],
            ]
        );

        foreach ($expected as $key => $val) {
            $this->assertConfigResolverParameterValue($key, $val, 'ezdemo_site');
        }
    }

    public function xmlTextSettingsProvider()
    {
        return [
            [
                [
                    'fieldtypes' => [
                        'ezxml' => [
                            'custom_tags' => [
                                ['path' => '/foo/bar.xsl', 'priority' => 123],
                                ['path' => '/foo/custom.xsl', 'priority' => -10],
                                ['path' => '/another/custom.xsl', 'priority' => 27],
                            ],
                        ],
                    ],
                ],
                [
                    'fieldtypes.ezxml.custom_xsl' => [
                        // Default settings will be added
                        ['path' => '%kernel.root_dir%/../vendor/ezsystems/ezplatform-xmltext-fieldtype/lib/FieldType/XmlText/Input/Resources/stylesheets/eZXml2Html5_core.xsl', 'priority' => 0],
                        ['path' => '%kernel.root_dir%/../vendor/ezsystems/ezplatform-xmltext-fieldtype/lib/FieldType/XmlText/Input/Resources/stylesheets/eZXml2Html5_custom.xsl', 'priority' => 0],
                        ['path' => '/foo/bar.xsl', 'priority' => 123],
                        ['path' => '/foo/custom.xsl', 'priority' => -10],
                        ['path' => '/another/custom.xsl', 'priority' => 27],
                    ],
                ],
            ],
        ];
    }
}
