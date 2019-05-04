<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests;

use eZ\Publish\API\Repository\Tests\FieldType\RelationSearchBaseIntegrationTestTrait;
use eZ\Publish\API\Repository\Tests\FieldType\SearchBaseIntegrationTest;
use eZ\Publish\Core\FieldType\XmlText\Value as XmlTextValue;
use eZ\Publish\Core\FieldType\XmlText\Type as XmlTextType;
use eZ\Publish\API\Repository\Values\Content\Field;
use DOMDocument;
use eZ\Publish\Core\Repository\Values\Content\Relation;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentType;
use EzSystems\EzPlatformXmlTextFieldType\Tests\SetupFactory\LegacySetupFactory;

/**
 * Integration test for use field type.
 *
 * @group integration
 * @group field-type
 */
class XmlTextAPIIntegrationTest extends SearchBaseIntegrationTest
{
    use RelationSearchBaseIntegrationTestTrait;

    /**
     * @var \DOMDocument
     */
    private $createdDOMValue;

    private $updatedDOMValue;

    protected function setUp()
    {
        parent::setUp();
        $this->createdDOMValue = new DOMDocument();
        $this->createdDOMValue->loadXML(<<<EOT
<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">
<paragraph>Example</paragraph>
<paragraph><link node_id="58">link1</link></paragraph>
<paragraph><link object_id="54">link2</link></paragraph>
<paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
    <embed view="embed" size="medium" node_id="60" custom:offset="0" custom:limit="5"/>
    <embed view="embed" size="medium" object_id="56" custom:offset="0" custom:limit="5"/>
</paragraph>
</section>
EOT
        );

        $this->updatedDOMValue = new DOMDocument();
        $this->updatedDOMValue->loadXML(<<<EOT
<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">
<paragraph>Example 2</paragraph>
<paragraph><link node_id="60">link1</link></paragraph>
<paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
    <embed view="embed" size="medium" object_id="56" custom:offset="0" custom:limit="5"/>
</paragraph>
</section>
EOT
        );
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\Content\Content $content
     *
     * @return \eZ\Publish\Core\Repository\Values\Content\Relation[]
     */
    public function getCreateExpectedRelations(Content $content)
    {
        $contentService = $this->getRepository()->getContentService();

        return [
            new Relation(
                [
                    'type' => Relation::LINK,
                    'sourceContentInfo' => $content->contentInfo,
                    'destinationContentInfo' => $contentService->loadContentInfo(56),
                ]
            ),
            new Relation(
                [
                    'type' => Relation::LINK,
                    'sourceContentInfo' => $content->contentInfo,
                    'destinationContentInfo' => $contentService->loadContentInfo(54),
                ]
            ),
            new Relation(
                [
                    'type' => Relation::EMBED,
                    'sourceContentInfo' => $content->contentInfo,
                    'destinationContentInfo' => $contentService->loadContentInfo(58),
                ]
            ),
            new Relation(
                [
                    'type' => Relation::EMBED,
                    'sourceContentInfo' => $content->contentInfo,
                    'destinationContentInfo' => $contentService->loadContentInfo(56),
                ]
            ),
        ];
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\Content\Content $content
     *
     * @return \eZ\Publish\Core\Repository\Values\Content\Relation[]
     */
    public function getUpdateExpectedRelations(Content $content)
    {
        $contentService = $this->getRepository()->getContentService();

        return [
            new Relation(
                [
                    'type' => Relation::LINK,
                    'sourceContentInfo' => $content->contentInfo,
                    'destinationContentInfo' => $contentService->loadContentInfo(58),
                ]
            ),
            new Relation(
                [
                    'type' => Relation::EMBED,
                    'sourceContentInfo' => $content->contentInfo,
                    'destinationContentInfo' => $contentService->loadContentInfo(56),
                ]
            ),
        ];
    }

    /**
     * Get name of tested field type.
     *
     * @return string
     */
    public function getTypeName()
    {
        return 'ezxmltext';
    }

    /**
     * Get expected settings schema.
     *
     * @return array
     */
    public function getSettingsSchema()
    {
        return [
            'numRows' => [
                'type' => 'int',
                'default' => 10,
            ],
            'tagPreset' => [
                'type' => 'choice',
                'default' => XmlTextType::TAG_PRESET_DEFAULT,
            ],
        ];
    }

    /**
     * Get a valid $fieldSettings value.
     *
     * @return mixed
     */
    public function getValidFieldSettings()
    {
        return [
            'numRows' => 0,
            'tagPreset' => XmlTextType::TAG_PRESET_DEFAULT,
        ];
    }

    /**
     * Get $fieldSettings value not accepted by the field type.
     *
     * @return mixed
     */
    public function getInvalidFieldSettings()
    {
        return [
            'somethingUnknown' => 0,
        ];
    }

    /**
     * Get expected validator schema.
     *
     * @return array
     */
    public function getValidatorSchema()
    {
        return [];
    }

    /**
     * Get a valid $validatorConfiguration.
     *
     * @return mixed
     */
    public function getValidValidatorConfiguration()
    {
        return [];
    }

    /**
     * Get $validatorConfiguration not accepted by the field type.
     *
     * @return mixed
     */
    public function getInvalidValidatorConfiguration()
    {
        return [
            'unknown' => ['value' => 23],
        ];
    }

    /**
     * Get initial field data for valid object creation.
     *
     * @return mixed
     */
    public function getValidCreationFieldData()
    {
        $doc = new DOMDocument();
        $doc->loadXML(<<<EOT
<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">
<paragraph>Example</paragraph>
<paragraph><link node_id="58">link1</link></paragraph>
<paragraph><link object_id="54">link2</link></paragraph>
<paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
    <embed view="embed" size="medium" node_id="60" custom:offset="0" custom:limit="5"/>
    <embed view="embed" size="medium" object_id="56" custom:offset="0" custom:limit="5"/>
</paragraph>
</section>
EOT
        );

        return new XmlTextValue($doc);
    }

    /**
     * Get name generated by the given field type (either via Nameable or fieldType->getName()).
     *
     * @return string
     */
    public function getFieldName()
    {
        return 'Example link1 link2';
    }

    /**
     * Asserts that the field data was loaded correctly.
     *
     * Asserts that the data provided by {@link getValidCreationFieldData()}
     * was stored and loaded correctly.
     *
     * @param Field $field
     */
    public function assertFieldDataLoadedCorrect(Field $field)
    {
        $this->assertInstanceOf(
            XmlTextValue::class,
            $field->value
        );

        $this->assertPropertiesCorrect(
            [
                'xml' => $this->createdDOMValue,
            ],
            $field->value
        );
    }

    /**
     * Get field data which will result in errors during creation.
     *
     * This is a PHPUnit data provider.
     *
     * The returned records must contain of an error producing data value and
     * the expected exception class (from the API or SPI, not implementation
     * specific!) as the second element. For example:
     *
     * <code>
     * array(
     *      array(
     *          new DoomedValue( true ),
     *          'eZ\\Publish\\API\\Repository\\Exceptions\\ContentValidationException'
     *      ),
     *      // ...
     * );
     * </code>
     *
     * @return array[]
     */
    public function provideInvalidCreationFieldData()
    {
        return [
            [
                new \stdClass(),
                InvalidArgumentType::class,
            ],
        ];
    }

    /**
     * Get update field externals data.
     *
     * @return array
     */
    public function getValidUpdateFieldData()
    {
        return new XmlTextValue($this->updatedDOMValue);
    }

    /**
     * Get externals updated field data values.
     *
     * This is a PHPUnit data provider
     *
     * @return array
     */
    public function assertUpdatedFieldDataLoadedCorrect(Field $field)
    {
        $this->assertInstanceOf(
            XmlTextValue::class,
            $field->value
        );

        $this->assertPropertiesCorrect(
            [
                'xml' => $this->updatedDOMValue,
            ],
            $field->value
        );
    }

    /**
     * Get field data which will result in errors during update.
     *
     * This is a PHPUnit data provider.
     *
     * The returned records must contain of an error producing data value and
     * the expected exception class (from the API or SPI, not implementation
     * specific!) as the second element. For example:
     *
     * <code>
     * array(
     *      array(
     *          new DoomedValue( true ),
     *          'eZ\\Publish\\API\\Repository\\Exceptions\\ContentValidationException'
     *      ),
     *      // ...
     * );
     * </code>
     *
     * @return array[]
     */
    public function provideInvalidUpdateFieldData()
    {
        return $this->provideInvalidCreationFieldData();
    }

    /**
     * Asserts the the field data was loaded correctly.
     *
     * Asserts that the data provided by {@link getValidCreationFieldData()}
     * was copied and loaded correctly.
     *
     * @param Field $field
     */
    public function assertCopiedFieldDataLoadedCorrectly(Field $field)
    {
        $this->assertInstanceOf(
            XmlTextValue::class,
            $field->value
        );

        $this->assertPropertiesCorrect(
            [
                'xml' => $this->createdDOMValue,
            ],
            $field->value
        );
    }

    /**
     * Get data to test to hash method.
     *
     * This is a PHPUnit data provider
     *
     * The returned records must have the the original value assigned to the
     * first index and the expected hash result to the second. For example:
     *
     * <code>
     * array(
     *      array(
     *          new MyValue( true ),
     *          array( 'myValue' => true ),
     *      ),
     *      // ...
     * );
     * </code>
     *
     * @return array
     */
    public function provideToHashData()
    {
        $xml = new DOMDocument();
        $xml->loadXML(<<<EOT
<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">
<paragraph>Example</paragraph>
</section>
EOT
        );

        return [
            [
                new XmlTextValue($xml),
                ['xml' => $xml->saveXML()],
            ],
        ];
    }

    /**
     * Get expectations for the fromHash call on our field value.
     *
     * This is a PHPUnit data provider
     *
     * @return array
     */
    public function provideFromHashData()
    {
        return [
            [
                [
                    'xml' => '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">
<paragraph>Foobar</paragraph>
</section>
',
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideFromHashData
     * @todo: Requires correct registered FieldTypeService, needs to be
     *        maintained!
     */
    public function testFromHash($hash, $expectedValue = null)
    {
        $xmlTextValue = $this
                ->getRepository()
                ->getFieldTypeService()
                ->getFieldType($this->getTypeName())
                ->fromHash($hash);
        $this->assertInstanceOf(
            XmlTextValue::class,
            $xmlTextValue
        );
        $this->assertInstanceOf('DOMDocument', $xmlTextValue->xml);

        $this->assertEquals($hash['xml'], (string)$xmlTextValue);
    }

    public function providerForTestIsEmptyValue()
    {
        $doc = new DOMDocument();
        $doc->loadXML('<section></section>');

        return [
            [new XmlTextValue()],
            [new XmlTextValue($doc)],
        ];
    }

    public function providerForTestIsNotEmptyValue()
    {
        $doc = new DOMDocument();
        $doc->loadXML('<section> </section>');
        $doc2 = new DOMDocument();
        $doc2->loadXML('<section><paragraph></paragraph></section>');

        return [
            [
                $this->getValidCreationFieldData(),
            ],
            [new XmlTextValue($doc)],
            [new XmlTextValue($doc2)],
        ];
    }

    /**
     * Get data to test remote id conversion.
     *
     * This is a PHP Unit data provider
     *
     * @see testConvertReomoteObjectIdToObjectId()
     */
    public function providerForTestConvertRemoteObjectIdToObjectId()
    {
        $remote_id = '[RemoteId]';
        $object_id = '[ObjectId]';

        return [
            [
                // test link
                '<?xml version="1.0" encoding="utf-8"?>
<section>
    <paragraph><link anchor_name="test" object_remote_id="' . $remote_id . '">link</link></paragraph>
</section>
',
                '<?xml version="1.0" encoding="utf-8"?>
<section>
    <paragraph><link anchor_name="test" object_id="' . $object_id . '">link</link></paragraph>
</section>
',
            ],
            [
                // test embed
                '<?xml version="1.0" encoding="utf-8"?>
<section>
    <paragraph><embed view="embed" size="medium" object_remote_id="' . $remote_id . '"/></paragraph>
</section>
',
                '<?xml version="1.0" encoding="utf-8"?>
<section>
    <paragraph><embed view="embed" size="medium" object_id="' . $object_id . '"/></paragraph>
</section>
',
            ],
            [
                // test embed-inline
                '<?xml version="1.0" encoding="utf-8"?>
<section>
    <paragraph><embed-inline size="medium" object_remote_id="' . $remote_id . '"/></paragraph>
</section>
',
                '<?xml version="1.0" encoding="utf-8"?>
<section>
    <paragraph><embed-inline size="medium" object_id="' . $object_id . '"/></paragraph>
</section>
',
            ],
        ];
    }

    /**
     * This tests the conversion from remote_object_id to object_id.
     *
     * @dataProvider providerForTestConvertRemoteObjectIdToObjectId
     */
    public function testConvertRemoteObjectIdToObjectId($test, $expected)
    {
        $repository = $this->getRepository();

        $contentService = $repository->getContentService();
        $locationService = $repository->getLocationService();

        // Create test content type
        $contentType = $this->createContentType(
            $this->getValidFieldSettings(),
            $this->getValidValidatorConfiguration()
        );
        $createStruct = $contentService->newContentCreateStruct($contentType, 'eng-GB');

        $createStruct->setField('name', 'Folder Link');
        $draft = $contentService->createContent(
            $createStruct,
            [$locationService->newLocationCreateStruct(2)]
        );

        $target = $contentService->publishVersion(
            $draft->versionInfo
        );

        $object_id = $target->versionInfo->contentInfo->id;
        $node_id = $target->versionInfo->contentInfo->mainLocationId;
        $remote_id = $target->versionInfo->contentInfo->remoteId;

        // create value to be tested
        $testStruct = $contentService->newContentCreateStruct($contentType, 'eng-GB');
        $testStruct->setField('name', 'Article - test');
        $testStruct->setField(
            'data',
            str_replace(
                '[RemoteId]',
                $remote_id,
                $test
            )
        );
        $test = $contentService->createContent(
            $testStruct,
            [$locationService->newLocationCreateStruct($node_id)]
        );

        $this->assertEquals(
            $test->getField('data')->value->xml->saveXML(),
            str_replace('[ObjectId]', $object_id, $expected)
        );
    }

    protected function checkSearchEngineSupport()
    {
        if (ltrim(\get_class($this->getSetupFactory()), '\\') === LegacySetupFactory::class) {
            $this->markTestSkipped(
                "'ezxmltext' field type is not searchable with Legacy Search Engine"
            );
        }
    }

    protected function getValidSearchValueOne()
    {
        return <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">
    <paragraph>caution is the path to mediocrity</paragraph>
</section>
EOT;
    }

    protected function getSearchTargetValueOne()
    {
        // ensure case-insensitivity
        return strtoupper('caution is the path to mediocrity');
    }

    protected function getValidSearchValueTwo()
    {
        return <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">
    <paragraph>truth suffers from too much analysis</paragraph>
</section>
EOT;
    }

    protected function getSearchTargetValueTwo()
    {
        // ensure case-insensitivity
        return strtoupper('truth suffers from too much analysis');
    }

    protected function getFullTextIndexedFieldData()
    {
        return [
            ['mediocrity', 'analysis'],
        ];
    }
}
