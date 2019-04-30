<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType\Converter;

use eZ\Publish\Core\FieldType\XmlText\Converter\Expanding;
use PHPUnit\Framework\TestCase;
use DOMDocument;

/**
 * Tests the Expanding converter.
 */
class ExpandingTest extends TestCase
{
    /**
     * Provider for conversion test.
     *
     * @return array
     */
    public function providerForTestConvert()
    {
        $map = [];

        foreach (glob(__DIR__ . '/_fixtures/expanding/input/*.xml') as $inputFilePath) {
            $basename = basename($inputFilePath, '.xml');
            $outputFilePath = __DIR__ . "/_fixtures/expanding/output/{$basename}.xml";

            $map[] = [$inputFilePath, $outputFilePath];
        }

        return $map;
    }

    /**
     * @param string $xml
     * @param bool $isPath
     *
     * @return \DOMDocument
     */
    protected function createDocument($xml, $isPath = true)
    {
        $document = new DOMDocument();

        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        if ($isPath === true) {
            $xml = file_get_contents($xml);
        }

        $document->loadXml($xml);

        return $document;
    }

    /**
     * @param string $inputFilePath
     * @param string $outputFilePath
     *
     * @dataProvider providerForTestConvert
     */
    public function testConvert($inputFilePath, $outputFilePath)
    {
        $inputDocument = $this->createDocument($inputFilePath);

        $converter = new Expanding();
        $converter->convert($inputDocument);

        $outputDocument = $this->createDocument($outputFilePath);

        $this->assertEquals(
            $outputDocument,
            $inputDocument
        );
    }
}
