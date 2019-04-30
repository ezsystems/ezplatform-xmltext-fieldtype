<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\REST\Common\FieldTypeProcessor;

use eZ\Publish\Core\REST\Common\FieldTypeProcessor\XmlTextProcessor;
use eZ\Publish\Core\FieldType\XmlText\Type;
use PHPUnit\Framework\TestCase;

class XmlTextProcessorTest extends TestCase
{
    public function fieldSettingsHashes()
    {
        return [
            [
                ['tagPreset' => 'TAG_PRESET_DEFAULT'],
                ['tagPreset' => Type::TAG_PRESET_DEFAULT],
            ],
            [
                ['tagPreset' => 'TAG_PRESET_SIMPLE_FORMATTING'],
                ['tagPreset' => Type::TAG_PRESET_SIMPLE_FORMATTING],
            ],
        ];
    }

    /**
     * @covers \eZ\Publish\Core\REST\Common\FieldTypeProcessor\XmlTextProcessor::preProcessFieldSettingsHash
     * @dataProvider fieldSettingsHashes
     */
    public function testPreProcessFieldSettingsHash($inputSettings, $outputSettings)
    {
        $processor = $this->getProcessor();

        $this->assertEquals(
            $outputSettings,
            $processor->preProcessFieldSettingsHash($inputSettings)
        );
    }

    /**
     * @covers \eZ\Publish\Core\REST\Common\FieldTypeProcessor\XmlTextProcessor::postProcessFieldSettingsHash
     * @dataProvider fieldSettingsHashes
     */
    public function testPostProcessFieldSettingsHash($outputSettings, $inputSettings)
    {
        $processor = $this->getProcessor();

        $this->assertEquals(
            $outputSettings,
            $processor->postProcessFieldSettingsHash($inputSettings)
        );
    }

    /**
     * @return \eZ\Publish\Core\REST\Common\FieldTypeProcessor\XmlTextProcessor
     */
    protected function getProcessor()
    {
        return new XmlTextProcessor();
    }
}
