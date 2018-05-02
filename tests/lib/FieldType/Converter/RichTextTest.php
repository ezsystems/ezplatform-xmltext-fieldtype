<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType\Converter;

use eZ\Publish\Core\FieldType\XmlText\Converter\RichText;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
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
     * Provider for conversion test.
     *
     * @return array
     */
    public function providerForTestConvert()
    {
        $map = array();

        foreach (glob(__DIR__ . '/_fixtures/richtext/input/*.xml') as $inputFilePath) {
            $basename = basename($inputFilePath, '.xml');
            $outputFilePath = __DIR__ . "/_fixtures/richtext/output/{$basename}.xml";

            $map[] = array($inputFilePath, $outputFilePath);
        }

        return $map;
    }

    protected function normalizeRewrittenIds(DOMDocument $xmlDoc)
    {
        $counter = 0;
        $xpath = new DOMXPath($xmlDoc);
        $nodes = $xpath->query("//*[contains(@xml:id, 'duplicated_id_')]");
        foreach ($nodes as $node) {
            $id = $node->attributes->getNamedItem('id')->nodeValue;
            $node->attributes->getNamedItem('id')->nodeValue = "duplicated_id_foobar$counter";
            ++$counter;
        }
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
        $richText = new RichText(null);

        $result = $richText->convert($inputDocument, true);

        $convertedDocument = $this->createDocument($result, false);
        $expectedDocument = $this->createDocument($outputFilePath);

        // since duplicate ids are rewritten with random values, we need to normalize those
        $this->normalizeRewrittenIds($convertedDocument);

        $this->assertEquals(
            $expectedDocument,
            $convertedDocument
        );
    }
}
