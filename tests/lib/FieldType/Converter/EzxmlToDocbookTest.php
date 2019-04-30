<?php

/**
 * File containing the EzxmlToDocbookTest conversion test.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType\Converter;

use EzSystems\EzPlatformRichText\eZ\RichText\Validator\Validator;
use eZ\Publish\Core\FieldType\XmlText\Converter\RichText;
use eZ\Publish\Core\SignalSlot\Repository;
use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\API\Repository\Values\Content\Location;

/**
 * Tests conversion from legacy ezxml to docbook format.
 */
class EzxmlToDocbookTest extends BaseTest
{
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

    /**
     * @return \eZ\Publish\Core\FieldType\XmlText\Converter
     */
    protected function getConverter($inputFile)
    {
        $validatorSchemas = [
            './vendor/ezsystems/ezplatform-richtext/src/lib/eZ/RichText/Resources/schemas/docbook/ezpublish.rng',
            './vendor/ezsystems/ezplatform-richtext/src/lib/eZ/RichText/Resources/schemas/docbook/docbook.iso.sch.xsl',
        ];
        $apiRepositoryStub = $this->createApiRepositoryStub();

        if (basename($inputFile) === '017-customYoutube.xml') {
            $customStylesheets = [
                [
                    'path' => __DIR__ . '/Xslt/_fixtures/ezxml/custom_stylesheets/youtube_docbook.xsl',
                    'priority' => 100,
                ],
            ];
            $validatorSchemas[] = __DIR__ . '/../../../../tests/lib/FieldType/Converter/Xslt/_fixtures/docbook/custom_schemas/youtube.rng';

            $validator = new Validator($validatorSchemas);
            $converter = new RichText($apiRepositoryStub, null, $validator);
            $converter->setCustomStylesheets($customStylesheets);

            return $converter;
        }

        if ($this->converter === null) {
            $validator = new Validator($validatorSchemas);
            $this->converter = new RichText($apiRepositoryStub, null, $validator);
        }

        return $this->converter;
    }

    /**
     * Returns subdirectories for input and output fixtures.
     *
     * The test will try to match each XML file in input directory with
     * the file of the same name in the output directory.
     *
     * It is possible to test lossy conversion as well (say legacy ezxml).
     * To use this file name of the fixture that is converted with data loss
     * needs to end with `.lossy.xml`. As input test with this fixture will
     * be skipped, but as output fixture it will be matched to the input
     * fixture file of the same name but without `.lossy` part.
     *
     * Comments in fixtures are removed before conversion, so be free to use
     * comments inside fixtures for documentation as needed.
     *
     * @return array
     */
    public function getFixtureSubdirectories()
    {
        return [
            'input' => 'ezxml',
            'output' => 'docbook',
        ];
    }
}
