<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType\Persistence\Legacy;

use function count;
use Doctrine\DBAL\FetchMode;
use eZ\Publish\Core\FieldType\XmlText\Persistence\Legacy\ContentModelGateway;

class ContentModelGatewayTest extends BaseTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->getSetupFactory()->resetDB();
    }

    public function getContentTypeIdsProvider(): array
    {
        return [
            [
                ['image', 'thumbnail'],
                ['image' => 27, 'thumbnail' => 2],
            ],
            [
                ['image'],
                ['image' => 27],
            ],
        ];
    }

    /**
     * @dataProvider getContentTypeIdsProvider
     *
     * @param string[] $identifiers
     * @param array $expected Content Type identifier to ID map
     *
     * @throws \ErrorException
     */
    public function testGetContentTypeIds(array $identifiers, array $expected): void
    {
        $this->insertDatabaseFixture(__DIR__ . '/_fixtures/contentclass.php');
        $gatewayService = $this->getGatewayService();
        $ids = $gatewayService->getContentTypeIds($identifiers);
        $this->assertEquals($expected, $ids);
    }

    /**
     * @throws \ErrorException
     */
    public function testCountContentTypeFieldsByFieldType(): void
    {
        $this->insertDatabaseFixture(__DIR__ . '/_fixtures/contentclass_attribute.php');
        $gatewayService = $this->getGatewayService();

        $count = $gatewayService->countContentTypeFieldsByFieldType('ezxmltext');
        $this->assertEquals(2, $count, 'Expected to find 2 content type fields');

        $count = $gatewayService->countContentTypeFieldsByFieldType('ezstring');
        $this->assertEquals(1, $count, 'Expected to find 1 content type field');

        $count = $gatewayService->countContentTypeFieldsByFieldType('foobar');
        $this->assertEquals(0, $count, 'Expected to find 0 content type fields');
    }

    /**
     * @throws \ErrorException
     */
    public function testGetContentTypeFieldTypeUpdateQuery(): void
    {
        $this->insertDatabaseFixture(__DIR__ . '/_fixtures/contentclass_attribute.php');
        $gatewayService = $this->getGatewayService();

        $count1 = $gatewayService->countContentTypeFieldsByFieldType('ezxmltext');
        $this->assertEquals(2, $count1, 'Expected to find 2 field definitions subject for conversion');

        $updateQuery = $gatewayService->getContentTypeFieldTypeUpdateQuery('ezxmltext', 'ezrichtext');
        $updateQuery->execute();

        $count2 = $gatewayService->countContentTypeFieldsByFieldType('ezxmltext');
        $this->assertEquals(0, $count2, 'Expected all field definitions to be converted');
    }

    public function getRowCountOfContentObjectAttributesProvider(): array
    {
        return [
            [
                ['ezxmltext'],
                68,
                2,
            ],
            [
                ['ezstring'],
                68,
                3,
            ],
            [
                ['foobar'],
                68,
                0,
            ],
            [
                ['ezxmltext'],
                69,
                3,
            ],
            [
                ['ezxmltext'],
                null,
                5,
            ],
            [
                ['ezrichtext'],
                null,
                2,
            ],
            [
                ['ezxmltext', 'ezrichtext'],
                null,
                7,
            ],
        ];
    }

    /**
     * @dataProvider getRowCountOfContentObjectAttributesProvider
     *
     * @throws \ErrorException
     */
    public function testGetRowCountOfContentObjectAttributes(
        array $datatypeString,
        ?int $contentId,
        int $expectedCount
    ): void {
        $this->insertDatabaseFixture(__DIR__ . '/_fixtures/contentobject_attribute.php');

        $gatewayService = $this->getGatewayService();
        $count = $gatewayService->getRowCountOfContentObjectAttributes($datatypeString, $contentId);

        $this->assertEquals($expectedCount, $count, 'Number of attributes does not match');
    }

    public function getFieldRowsProvider(): array
    {
        return [
            [ //test $contentId
                ['ezxmltext'],
                68,
                0,
                100,
                [
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => 183,
                        'contentobject_id' => 68,
                        'data_float' => 0,
                        'data_int' => 1045487555,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>Content consumption is changing rapidly. An agile solution to distribute your content and empower your digital business model is key to success in every industry.</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => 283,
                        'language_code' => 'eng-GB',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => '184',
                        'contentobject_id' => 68,
                        'data_float' => '0',
                        'data_int' => 1045487555,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>eZ Publish Enterprise is the platform to make the omni-channel approach possible. A powerful presentation engine provides a multiplicity of websites and pages that display your content in a variety of renderings. A powerful API directly and simply integrates your content with any Web-enabled application on any device, including the iPad, iPhone or Android without ever interfering with or impacting the platform itself.</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => '284',
                        'language_code' => 'eng-GB',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                ],
            ],
            [ // test $offset, $limit
                ['ezxmltext'],
                null,
                0,
                1,
                [
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => 183,
                        'contentobject_id' => 68,
                        'data_float' => 0,
                        'data_int' => 1045487555,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>Content consumption is changing rapidly. An agile solution to distribute your content and empower your digital business model is key to success in every industry.</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => 283,
                        'language_code' => 'eng-GB',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                ],
            ],
            [ // test $offset, $limit
                ['ezxmltext'],
                null,
                1,
                1,
                [
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => '184',
                        'contentobject_id' => 68,
                        'data_float' => '0',
                        'data_int' => 1045487555,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>eZ Publish Enterprise is the platform to make the omni-channel approach possible. A powerful presentation engine provides a multiplicity of websites and pages that display your content in a variety of renderings. A powerful API directly and simply integrates your content with any Web-enabled application on any device, including the iPad, iPhone or Android without ever interfering with or impacting the platform itself.</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => '284',
                        'language_code' => 'eng-GB',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                ],
            ],
            [ // test $offset, $limit
                ['ezxmltext'],
                null,
                1,
                2,
                [
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => '184',
                        'contentobject_id' => 68,
                        'data_float' => '0',
                        'data_int' => 1045487555,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>eZ Publish Enterprise is the platform to make the omni-channel approach possible. A powerful presentation engine provides a multiplicity of websites and pages that display your content in a variety of renderings. A powerful API directly and simply integrates your content with any Web-enabled application on any device, including the iPad, iPhone or Android without ever interfering with or impacting the platform itself.</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => '284',
                        'language_code' => 'eng-GB',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => 183,
                        'contentobject_id' => '69',
                        'data_float' => '0',
                        'data_int' => null,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>Increasing the productivity of your content infrastructure, eZ Publish Enterprise provides you with powerful tools to create, automate and collaborate on content...</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => '295',
                        'language_code' => 'eng-GB',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                ],
            ],
            [ // test multiple datatype strings
                ['ezxmltext', 'ezrichtext'],
                null,
                0,
                100,
                [
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => 183,
                        'contentobject_id' => 68,
                        'data_float' => 0,
                        'data_int' => 1045487555,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>Content consumption is changing rapidly. An agile solution to distribute your content and empower your digital business model is key to success in every industry.</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => 283,
                        'language_code' => 'eng-GB',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => '184',
                        'contentobject_id' => 68,
                        'data_float' => '0',
                        'data_int' => 1045487555,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>eZ Publish Enterprise is the platform to make the omni-channel approach possible. A powerful presentation engine provides a multiplicity of websites and pages that display your content in a variety of renderings. A powerful API directly and simply integrates your content with any Web-enabled application on any device, including the iPad, iPhone or Android without ever interfering with or impacting the platform itself.</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => '284',
                        'language_code' => 'eng-GB',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => 183,
                        'contentobject_id' => '69',
                        'data_float' => 0,
                        'data_int' => null,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>Increasing the productivity of your content infrastructure, eZ Publish Enterprise provides you with powerful tools to create, automate and collaborate on content...</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => '295',
                        'language_code' => 'eng-GB',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => 183,
                        'contentobject_id' => 69,
                        'data_float' => 0,
                        'data_int' => null,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>version2</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => '295',
                        'language_code' => 'eng-GB',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 2,
                    ],
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => 183,
                        'contentobject_id' => 69,
                        'data_float' => 0,
                        'data_int' => null,
                        'data_text' => '<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>version2</paragraph></section>',
                        'data_type_string' => 'ezxmltext',
                        'id' => '296',
                        'language_code' => 'nor-NO',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 2,
                    ],
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => 183,
                        'contentobject_id' => 70,
                        'data_float' => 0,
                        'data_int' => null,
                        'data_text' => '<section xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:ezxhtml="http://ez.no/xmlns/ezpublish/docbook/xhtml" xmlns:ezcustom="http://ez.no/xmlns/ezpublish/docbook/custom" version="5.0-variant ezpublish-1.0"><title ezxhtml:level="2">header1text</title></section>',
                        'data_type_string' => 'ezrichtext',
                        'id' => '297',
                        'language_code' => 'nor-NO',
                        'language_id' => 2,
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                    [
                        'attribute_original_id' => 0,
                        'contentclassattribute_id' => 183,
                        'contentobject_id' => 70,
                        'data_float' => 0,
                        'data_int' => null,
                        'data_text' => '<section xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:ezxhtml="http://ez.no/xmlns/ezpublish/docbook/xhtml" xmlns:ezcustom="http://ez.no/xmlns/ezpublish/docbook/custom" version="5.0-variant ezpublish-1.0"><title ezxhtml:level="2">header1text</title></section>',
                        'data_type_string' => 'ezrichtext',
                        'id' => '298',
                        'language_code' => 'eng-GB',
                        'language_id' => '1',
                        'sort_key_int' => 0,
                        'sort_key_string' => '',
                        'version' => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider getFieldRowsProvider
     *
     * @throws \ErrorException
     */
    public function testGetFieldRows(
        array $datatypeString,
        ?int $contentId,
        int $offset,
        int $limit,
        array $expectedRows
    ): void {
        $this->insertDatabaseFixture(__DIR__ . '/_fixtures/contentobject_attribute.php');

        $gatewayService = $this->getGatewayService();
        $statement = $gatewayService->getFieldRows($datatypeString, $contentId, $offset, $limit);
        $index = 0;
        while ($row = $statement->fetch(FetchMode::ASSOCIATIVE)) {
            $this->assertLessThan(count($expectedRows), $index, 'Too many rows returned by getFieldRows');
            $this->assertEquals($expectedRows[$index], $row, 'Result from getFieldRows() did not return expected result');
            ++$index;
        }
        $this->assertEquals(count($expectedRows), $index, 'Too few rows returned by getFieldRows');
    }

    /**
     * @throws \ErrorException
     */
    protected function getFieldRows(int $contentId): array
    {
        $gatewayService = $this->getGatewayService();
        $statement = $gatewayService->getFieldRows('ezxmltext', $contentId, 0, 100);

        return $statement->fetchAll(FetchMode::ASSOCIATIVE);
    }

    /**
     * @throws \ErrorException
     */
    public function getAllFieldRows(): array
    {
        $query = $this->getRawDatabaseConnection()->createQueryBuilder();
        $query->select('a.*')
            ->from('ezcontentobject_attribute', 'a')
            ->orderBy('a.id');

        $statement = $query->execute();

        return $statement->fetchAll(FetchMode::ASSOCIATIVE);
    }

    public function getUpdateFieldRowQueryProvider(): array
    {
        return [
            [
                283,
                1,
                'foobar',
            ],
            [
                295,
                1,
                'foobar',
            ],
        ];
    }

    /**
     * @dataProvider getUpdateFieldRowQueryProvider
     *
     * @throws \ErrorException
     */
    public function testGetUpdateFieldRowQuery(int $id, int $version, string $datatext): void
    {
        $this->insertDatabaseFixture(__DIR__ . '/_fixtures/contentobject_attribute.php');

        $gatewayService = $this->getGatewayService();
        $originalRows = $this->getAllFieldRows();

        $updateQuery = $gatewayService->getUpdateFieldRowQuery($id, $version, $datatext);
        $updateQuery->execute();

        $updatedRows = $this->getAllFieldRows();
        foreach ($originalRows as $expectedRow) {
            if ($expectedRow['id'] == $id && $expectedRow['version'] == $version) {
                $expectedRow['data_text'] = $datatext;
                $expectedRow['data_type_string'] = 'ezrichtext';
            }

            $rowFound = false;
            foreach ($updatedRows as $updatedRow) {
                if ($expectedRow['id'] == $updatedRow['id'] && $expectedRow['version'] == $updatedRow['version'] && $expectedRow['language_code'] == $updatedRow['language_code']) {
                    $this->assertEquals($expectedRow, $updatedRow, 'Table row is not correct');
                    $rowFound = true;
                    break;
                }
            }
            $this->assertTrue($rowFound, "Row seems to have disappeared from db where id=$id and version=$version");
        }
    }

    public function contentObjectAttributeExistsProvider(): array
    {
        return [
            [
                68,
                283,
                1,
                'eng-GB',
                true,
            ],
            [
                68,
                283,
                1,
                'nor-NO',
                false,
            ],
            [
                69,
                295,
                1,
                'eng-GB',
                true,
            ],
            [
                69,
                295,
                2,
                'eng-GB',
                true,
            ],
            [
                69,
                295,
                3,
                'eng-GB',
                false,
            ],
        ];
    }

    /**
     * @dataProvider contentObjectAttributeExistsProvider
     *
     * @throws \ErrorException
     */
    public function testContentObjectAttributeExists(
        int $objectId,
        int $attributeId,
        int $version,
        string $language,
        bool $expectedResult
    ): void {
        $this->insertDatabaseFixture(__DIR__ . '/_fixtures/contentobject_attribute.php');

        $gatewayService = $this->getGatewayService();
        $result = $gatewayService->contentObjectAttributeExists($objectId, $attributeId, $version, $language);

        $this->assertEquals($expectedResult, $result, 'contentObjectAttributeExists() did not return expected value');
    }

    public function updateContentObjectAttributeProvider(): array
    {
        return [
            [
                'foobar',
                68,
                283,
                1,
                'eng-GB',
            ],
            [
                'foobar',
                69,
                295,
                1,
                'eng-GB',
            ],
            [
                'foobar',
                69,
                295,
                2,
                'eng-GB',
            ],
            [
                'foobar',
                69,
                296,
                2,
                'nor-NO',
            ],
        ];
    }

    /**
     * @dataProvider updateContentObjectAttributeProvider
     *
     * @throws \ErrorException
     */
    public function testUpdateContentObjectAttribute(
        string $xml,
        int $objectId,
        int $attributeId,
        int $version,
        string $language
    ): void {
        $this->insertDatabaseFixture(__DIR__ . '/_fixtures/contentobject_attribute.php');

        $gatewayService = $this->getGatewayService();
        $originalRows = $this->getAllFieldRows();

        $gatewayService->updateContentObjectAttribute($xml, $objectId, $attributeId, $version, $language);

        $updatedRows = $this->getAllFieldRows();
        foreach ($originalRows as $expectedRow) {
            if ($expectedRow['contentobject_id'] == $objectId
                && $expectedRow['id'] == $attributeId
                && $expectedRow['version'] == $version
                && $expectedRow['language_code'] == $language) {
                $expectedRow['data_text'] = $xml;
            }

            $rowFound = false;
            foreach ($updatedRows as $updatedRow) {
                if ($expectedRow['contentobject_id'] == $updatedRow['contentobject_id'] && $expectedRow['id'] == $updatedRow['id'] && $expectedRow['version'] == $updatedRow['version'] && $expectedRow['language_code'] == $updatedRow['language_code']) {
                    $this->assertEquals($expectedRow, $updatedRow, 'Table row is not correct');
                    $rowFound = true;
                    break;
                }
            }
            $this->assertTrue($rowFound, "Row seems to have disappeared from db where id=$objectId and version=$version");
        }
    }

    /**
     * @throws \ErrorException
     */
    protected function getGatewayService(): ContentModelGateway
    {
        $serviceContainer = $this->getSetupFactory()->getServiceContainer();

        /** @var \eZ\Publish\Core\FieldType\XmlText\Persistence\Legacy\ContentModelGateway $contentModelGateway */
        $contentModelGateway = $serviceContainer->get(
            'ezxmltext.persistence.legacy.content_model_gateway'
        );

        return $contentModelGateway;
    }
}
