<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType;

use eZ\Publish\Core\FieldType\XmlText\Type as XmlTextType;
use eZ\Publish\Core\FieldType\XmlText\Input\EzXml;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use eZ\Publish\Core\Persistence\TransformationProcessor;
use eZ\Publish\API\Repository\Values\Content\Relation;
use eZ\Publish\SPI\FieldType\ValidationError;
use eZ\Publish\Core\FieldType\Value;
use Exception;
use DOMDocument;
use PHPUnit\Framework\TestCase;

/**
 * @group fieldType
 * @group ezxmltext
 */
class XmlTextTest extends TestCase
{
    /**
     * Normally this should be enough:.
     *
     * $ft = new XmlTextType( $this->getMock( 'eZ\\Publish\\Core\\FieldType\\XmlText\\Input\\Parser' ) );
     *
     * But there is a bug in PHPUnit when mocking an interface and calling the test in a certain way
     * (eg. with --group switch), when invocationMocker is missing.
     *
     * Possibly described here:
     * https://github.com/sebastianbergmann/phpunit-mock-objects/issues/26
     */
    protected function getFieldType()
    {
        $fieldType = new XmlTextType();
        $fieldType->setTransformationProcessor($this->getTransformationProcessorMock());

        return $fieldType;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getTransformationProcessorMock()
    {
        return $this->getMockForAbstractClass(
            TransformationProcessor::class,
            [],
            '',
            false,
            true,
            true
        );
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\FieldType::getValidatorConfigurationSchema
     */
    public function testValidatorConfigurationSchema()
    {
        $ft = $this->getFieldType();
        self::assertEmpty(
            $ft->getValidatorConfigurationSchema(),
            'The validator configuration schema does not match what is expected.'
        );
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\FieldType::getSettingsSchema
     */
    public function testSettingsSchema()
    {
        $ft = $this->getFieldType();
        self::assertSame(
            [
                'numRows' => [
                    'type' => 'int',
                    'default' => 10,
                ],
                'tagPreset' => [
                    'type' => 'choice',
                    'default' => XmlTextType::TAG_PRESET_DEFAULT,
                ],
            ],
            $ft->getSettingsSchema(),
            'The settings schema does not match what is expected.'
        );
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\XmlText\Type::validateFieldSettings
     * @dataProvider providerForTestValidateFieldSettingsValid
     */
    public function testValidateFieldSettingsValid($settings)
    {
        $validationResult = $this->getFieldType()->validateFieldSettings($settings);

        $this->assertInternalType(
            'array',
            $validationResult,
            'The method validateFieldSettings() must return an array.'
        );
        $this->assertEquals(
            [],
            $validationResult,
            'validateFieldSettings() considered the input settings invalid, while they should be valid: '
        );
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\XmlText\Type::validateFieldSettings
     * @dataProvider providerForTestValidateFieldSettingsInvalid
     */
    public function testValidateFieldSettingsInvalid($settings)
    {
        $validationResult = $this->getFieldType()->validateFieldSettings($settings);

        $this->assertInternalType(
            'array',
            $validationResult,
            'The method validateFieldSettings() must return an array.'
        );

        $this->assertNotEquals(
            [],
            $validationResult,
            'validateFieldSettings() considered the input settings valid, while they should be invalid.'
        );

        foreach ($validationResult as $actualResultElement) {
            $this->assertInstanceOf(
                ValidationError::class,
                $actualResultElement,
                'Validation result of incorrect type.'
            );
        }
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\XmlText\Type::acceptValue
     */
    public function testAcceptValueInvalidType()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\InvalidArgumentException::class);

        $this->getFieldType()->acceptValue($this->createMock(Value::class));
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\Author\Type::acceptValue
     * @dataProvider providerForTestAcceptValueValidFormat
     */
    public function testAcceptValueValidFormat($input)
    {
        $fieldType = $this->getFieldType();
        $fieldType->acceptValue($input);
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\Author\Type::acceptValue
     * @dataProvider providerForTestAcceptValueInvalidFormat
     */
    public function testAcceptValueInvalidFormat($input, $errorMessage)
    {
        try {
            $fieldType = $this->getFieldType();
            $fieldType->acceptValue($input);
            $this->fail('An InvalidArgumentException was expected! None thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals($errorMessage, $e->getMessage());
        } catch (Exception $e) {
            $this->fail(
                'An InvalidArgumentException was expected! ' . \get_class($e) . ' thrown with message: ' . $e->getMessage()
            );
        }
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\XmlText\Type::toPersistenceValue
     */
    public function testToPersistenceValue()
    {
        $xmlData = '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><header level="1">Header 1</header></section>';
        $xmlDoc = new DOMDocument();
        $xmlDoc->loadXML($xmlData);
        // @todo Do one per value class
        $ft = $this->getFieldType();

        $fieldValue = $ft->toPersistenceValue($ft->acceptValue($xmlData));

        self::assertInternalType('string', $fieldValue->data);
        self::assertSame($xmlDoc->saveXML(), $fieldValue->data);
    }

    public static function providerForTestValidateFieldSettingsValid()
    {
        return [
            [
                [
                    'numRows' => 10,
                    'tagPreset' => '',
                ],
            ],
            [
                [
                    'numRows' => 10,
                    'tagPreset' => 0,
                ],
            ],
        ];
    }

    public static function providerForTestValidateFieldSettingsInvalid()
    {
        return [
            [
                [
                    'numRows' => '',
                    'tagPreset' => '',
                ],
            ],
            [
                [
                    'numRows' => 10,
                    'tagPreset' => 'a',
                ],
            ],
            [
                [
                    'numRows' => 'a',
                    'tagPreset' => 0,
                ],
            ],
        ];
    }

    public static function providerForTestAcceptValueValidFormat()
    {
        return [
            [
                $xml = '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><header level="1">This is a piece of text</header></section>',
            ],
            [new EzXml($xml)],

            [
                $xml = '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" />',
            ],
            [new EzXml($xml)],
        ];
    }

    public static function providerForTestAcceptValueInvalidFormat()
    {
        return [
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section><h1>This is a piece of text</h1></section>',
                "Argument 'xmlString' is invalid: Validation of XML content failed: Element 'h1': This element is not expected. Expected is one of ( section, paragraph, header ).",
            ],

            [
                'This is not XML at all!',
                "Argument 'xmlString' is invalid: Validation of XML content failed: Start tag expected, '<' not found\nThe document has no document element.",
            ],

            [
                '<unknown><format /></unknown>',
                "Argument 'xmlString' is invalid: Validation of XML content failed: Element 'unknown': No matching global declaration available for the validation root.",
            ],
        ];
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\XmlText\Type::getName
     * @dataProvider providerForTestGetName
     */
    public function testGetNamePassingValue($xml, $value)
    {
        $ft = $this->getFieldType();
        $this->assertEquals(
            $value,
            $ft->getName($ft->acceptValue($xml))
        );
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\XmlText\Type::getName
     * @dataProvider providerForTestGetName
     */
    public function testGetNamePassingXML($xml, $value)
    {
        $ft = $this->getFieldType();
        $this->assertEquals(
            $value,
            $ft->getName(
                $ft->acceptValue($xml)
            )
        );
    }

    public static function providerForTestGetName()
    {
        return [
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><header level="1">This is a piece of text</header></section>',
                'This is a piece of text',
            ],

            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><header level="1">This is a piece of <emphasize>text</emphasize></header></section>',
                /* @todo FIXME: should probably be "This is a piece of text" */
                'This is a piece of',
            ],

            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><header level="1"><strong>This is a piece</strong> of text</header></section>',
                /* @todo FIXME: should probably be "This is a piece of text" */
                'This is a piece',
            ],

            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><header level="1"><strong><emphasize>This is</emphasize> a piece</strong> of text</header></section>',
                /* @todo FIXME: should probably be "This is a piece of text" */
                'This is',
            ],

            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph><table class="default" border="0" width="100%" custom:summary="wai" custom:caption=""><tr><td><paragraph>First cell</paragraph></td><td><paragraph>Second cell</paragraph></td></tr><tr><td><paragraph>Third cell</paragraph></td><td><paragraph>Fourth cell</paragraph></td></tr></table></paragraph><paragraph>Text after table</paragraph></section>',
                /* @todo FIXME: should probably be "First cell" */
                'First cellSecond cell',
            ],

            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><ul><li><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">List item</paragraph></li></ul></paragraph></section>',
                'List item',
            ],

            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><ul><li><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">List <emphasize>item</emphasize></paragraph></li></ul></paragraph></section>',
                'List item',
            ],

            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" />',
                '',
            ],

            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph><strong><emphasize>A simple</emphasize></strong> paragraph!</paragraph></section>',
                'A simple',
            ],

            ['<section><paragraph>test</paragraph></section>', 'test'],

            ['<section><paragraph><link node_id="1">test</link><link object_id="1">test</link></paragraph></section>', 'test'],
        ];
    }

    /**
     * @covers \eZ\Publish\Core\FieldType\XmlText\Type::getRelations
     */
    public function testGetRelations()
    {
        $xml = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/"
         xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"
         xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">
    <paragraph><link node_id="72">link1</link></paragraph>
    <paragraph><link node_id="61">link2</link></paragraph>
    <paragraph><link node_id="61">link3</link></paragraph>
    <paragraph><link object_id="70">link4</link></paragraph>
    <paragraph><link object_id="75">link5</link></paragraph>
    <paragraph><link object_id="75">link6</link></paragraph>
    <paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
        <embed view="embed" size="medium" node_id="52" custom:offset="0" custom:limit="5"/>
        <embed view="embed" size="medium" node_id="42" custom:offset="0" custom:limit="5"/>
        <embed view="embed" size="medium" node_id="52" custom:offset="0" custom:limit="5"/>
        <embed view="embed" size="medium" object_id="72" custom:offset="0" custom:limit="5"/>
        <embed view="embed" size="medium" object_id="74" custom:offset="0" custom:limit="5"/>
        <embed view="embed" size="medium" object_id="72" custom:offset="0" custom:limit="5"/>
    </paragraph>
</section>
EOT;

        $ft = $this->getFieldType();
        $this->assertEquals(
            [
                Relation::LINK => [
                    'locationIds' => [72, 61],
                    'contentIds' => [70, 75],
                ],
                Relation::EMBED => [
                    'locationIds' => [52, 42],
                    'contentIds' => [72, 74],
                ],
            ],
            $ft->getRelations($ft->acceptValue($xml))
        );
    }

    protected function provideFieldTypeIdentifier()
    {
        return 'ezxmltext';
    }

    public function provideDataForGetName()
    {
        return [];
    }
}
