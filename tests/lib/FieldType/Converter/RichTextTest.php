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
use eZ\Publish\Core\SignalSlot\Repository;
use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\API\Repository\Values\Content\Location;
use Psr\Log\NullLogger;

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
        $map = [];

        foreach (glob(__DIR__ . '/_fixtures/richtext/input/*.xml') as $inputFilePath) {
            $basename = basename($inputFilePath, '.xml');
            $outputFilePath = __DIR__ . "/_fixtures/richtext/output/{$basename}.xml";
            $logFilePath = __DIR__ . "/_fixtures/richtext/log/{$basename}.log";
            if (!file_exists($logFilePath)) {
                $logFilePath = null;
            }

            $map[] = [$inputFilePath, $outputFilePath, $logFilePath];
        }

        return $map;
    }

    /**
     * Provider for conversion test.
     *
     * @return array
     */
    public function providerForTestTagEmbeddedImages()
    {
        $map = [];

        foreach (glob(__DIR__ . '/_fixtures/tag_embedded_images/input/*.xml') as $inputFilePath) {
            $basename = basename($inputFilePath, '.xml');
            $outputFilePath = __DIR__ . "/_fixtures/tag_embedded_images/output/{$basename}.xml";

            $map[] = [$inputFilePath, $outputFilePath];
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

    private function createApiRepositoryStub()
    {
        $apiRepositoryStub = $this->createMock(Repository::class);
        $contentServiceStub = $this->createMock(ContentService::class);
        $locationServiceStub = $this->createMock(LocationService::class);
        $contentInfoImageStub = $this->createMock(ContentInfo::class);
        $contentInfoFileStub = $this->createMock(ContentInfo::class);
        $locationStub = $this->createMock(Location::class);
        // content with id=126 is an image, content with id=128,129 is a file
        $map = [
            [126, $contentInfoImageStub],
            [128, $contentInfoFileStub],
            [129, $contentInfoFileStub],
        ];
        $apiRepositoryStub->method('getContentService')
            ->willReturn($contentServiceStub);
        $apiRepositoryStub->method('getLocationService')
            ->willReturn($locationServiceStub);
        $contentServiceStub->method('loadContentInfo')
            ->will($this->returnValueMap($map));

        // image content type has id=27, file content type has id=27
        $contentInfoImageStub->method('__get')->willReturn(27);
        $contentInfoFileStub->method('__get')->willReturn(25);

        $locationServiceStub->method('loadLocation')->willReturn($locationStub);
        $locationStub->method('getContentInfo')->willReturn($contentInfoImageStub);

        return $apiRepositoryStub;
    }

    private function createLoggerStub($logFilePath)
    {
        $loggerStub = $this->createMock(NullLogger::class);
        $logMethodUsage = ['warning' => false, 'error' => false];

        if ($logFilePath !== null) {
            $log = file_get_contents($logFilePath);
            $logLines = explode("\n", $log);
            $logNo = 0;
            foreach ($logLines as $expectedLogLine) {
                if ($expectedLogLine === '') {
                    continue;
                }
                $logMethod = substr($expectedLogLine, 0, strpos($expectedLogLine, ':'));
                $logMessage = substr($expectedLogLine, strpos($expectedLogLine, ':') + 1);
                $logMethodUsage[$logMethod] = true;
                if (strpos($expectedLogLine, '*') !== false) {
                    $loggerStub->expects($this->at($logNo++))
                        ->method($logMethod)
                    ->with($this->callback(function ($actualLogMessage) use ($logMessage) {
                        $expectedLogMessage = substr($logMessage, 0, strpos($logMessage, '*'));

                        $this->assertEquals($expectedLogMessage, substr($actualLogMessage, 0, strlen($expectedLogMessage)), 'Actual log message do not match the expected one');

                        return true;
                    }));
                } else {
                    $loggerStub->expects($this->at($logNo++))
                        ->method($logMethod)
                        ->with($logMessage);
                }
            }
        }
        foreach ($logMethodUsage as $method => $used) {
            if (!$used) {
                $loggerStub->expects($this->never())
                    ->method($method);
            }
        }

        return $loggerStub;
    }

    /**
     * @param string $inputFilePath
     * @param string $outputFilePath
     *
     * @dataProvider providerForTestConvert
     */
    public function testConvert($inputFilePath, $outputFilePath, $logFilePath)
    {
        $apiRepositoryStub = $this->createApiRepositoryStub();
        $loggerStub = $this->createLoggerStub($logFilePath);

        $inputDocument = $this->createDocument($inputFilePath);
        $richText = new RichText($apiRepositoryStub, $loggerStub);
        $richText->setImageContentTypes([27]);

        $result = $richText->convert($inputDocument, true, true);

        if ($result === false && !file_exists($outputFilePath)) {
            return;
        }
        $convertedDocument = $this->createDocument($result, false);
        $expectedDocument = $this->createDocument($outputFilePath);

        // since duplicate ids are rewritten with random values, we need to normalize those
        $this->normalizeRewrittenIds($convertedDocument);

        $this->assertEquals(
            $expectedDocument,
            $convertedDocument,
            sprintf(
                "Failed asserting that two DOM documents are equal.\nInput file: %s\nOutput file %s",
                $inputFilePath,
                $outputFilePath
            )
        );
    }

    /**
     * @param string $inputFilePath
     * @param string $outputFilePath
     *
     * @dataProvider providerForTestTagEmbeddedImages
     */
    public function testTagEmbeddedImages($inputFilePath, $outputFilePath)
    {
        $apiRepositoryStub = $this->createApiRepositoryStub();

        $inputDocument = $this->createDocument($inputFilePath);
        $richText = new RichText($apiRepositoryStub);
        $richText->setImageContentTypes(array(27));

        $richText->tagEmbeddedImages($inputDocument, null);

        $expectedDocument = $this->createDocument($outputFilePath);

        // since duplicate ids are rewritten with random values, we need to normalize those
        $this->normalizeRewrittenIds($inputDocument);

        $this->assertEquals(
            $expectedDocument,
            $inputDocument
        );
    }
}
