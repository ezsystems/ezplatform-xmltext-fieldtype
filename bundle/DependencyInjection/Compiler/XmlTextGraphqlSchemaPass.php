<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiler pass adding GraphQL schema mapping for ezxmltext field type.
 */
class XmlTextGraphqlSchemaPass implements CompilerPassInterface
{
    private const SCHEMA_FIELD_TYPES = 'ezplatform_graphql.schema.content.mapping.field_definition_type';

    public function process(ContainerBuilder $container)
    {
        $graphqlSchemaDef = $container->hasParameter(self::SCHEMA_FIELD_TYPES) ? $container->getParameter(self::SCHEMA_FIELD_TYPES) : [];

        $graphqlSchemaDef['ezxmltext'] = ['value_type' => 'XmlTextFieldValue'];
        $container->setParameter(self::SCHEMA_FIELD_TYPES, $graphqlSchemaDef);
    }
}
