<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType\Gateway;

use eZ\Publish\SPI\Persistence\Content\VersionInfo;
use eZ\Publish\SPI\Persistence\Content\Field;
use eZ\Publish\SPI\Persistence\Content\FieldValue;
use eZ\Publish\Core\FieldType\XmlText\XmlTextStorage\Gateway\LegacyStorage;
use PHPUnit\Framework\TestCase;

/**
 * Tests the LegacyStorage
 * Class LegacyStorageTest.
 */
class LegacyStorageTest extends TestCase
{
    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\eZ\Publish\Core\FieldType\XmlText\XmlTextStorage\Gateway\LegacyStorage
     */
    protected function getPartlyMockedLegacyStorage(array $testMethods = null)
    {
        return $this->getMockBuilder(LegacyStorage::class)
            ->disableOriginalConstructor()
            ->setMethods($testMethods)
            ->getMock();
    }

    /**
     * @return array
     */
    public function providerForTestStoreFieldData()
    {
        /*
         * 1. Input XML
         * 2. Use of getLinksId() in form of array( array $arguments, array $return ), empty means no call
         * 3. Use of getObjectId() in form of array( array $arguments, array $return ), empty means no call
         * 4. Use of insertLink() in form of array( $argument, $return ), empty means no call
         * 5. Use of linkUrl() in form of array( $argument, $return ), empty means no call
         * 6. Expected return value
         * 7. Resulting XML
         */
        return [
            // LINK
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url="/test">object link</link>.</paragraph></section>
',
                [['/test'], ['/test' => 55]],
                [[], []],
                [],
                [55, null],
                true,
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url_id="55">object link</link>.</paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url="/test">object link</link><link url="/test">object link</link>.</paragraph></section>
',
                [['/test'], ['/test' => 55]],
                [[], []],
                [],
                [55, null],
                true,
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url_id="55">object link</link><link url_id="55">object link</link>.</paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link object_remote_id="34oi5ne5tj5iojte8oj58otehj5tjheo8">object link</link>.</paragraph></section>
',
                [[], []],
                [['34oi5ne5tj5iojte8oj58otehj5tjheo8'], ['34oi5ne5tj5iojte8oj58otehj5tjheo8' => 55]],
                [],
                [],
                true,
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link object_id="55">object link</link>.</paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link object_remote_id="34oi5ne5tj5iojte8oj58otehj5tjheo8">object link</link><embed object_remote_id="34oi5ne5tj5iojte8oj58otehj5tjheo8">object link</embed>.</paragraph></section>
',
                [[], []],
                [['34oi5ne5tj5iojte8oj58otehj5tjheo8'], ['34oi5ne5tj5iojte8oj58otehj5tjheo8' => 55]],
                [],
                [],
                true,
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link object_id="55">object link</link><embed object_id="55">object link</embed>.</paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url="/newUrl">object link</link>.</paragraph></section>
',
                [['/newUrl'], []],
                [[], []],
                ['/newUrl', 66],
                [66, null],
                true,
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url_id="66">object link</link>.</paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url_id="55">object link</link>.</paragraph></section>
',
                [],
                [],
                [],
                [],
                false,
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url_id="55">object link</link>.</paragraph></section>
',
            ],

            // EMBED
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <embed object_remote_id="34oi5ne5tj5iojte8oj58otehj5tjheo8">object embed</embed>.</paragraph></section>
',
                [[], []],
                [['34oi5ne5tj5iojte8oj58otehj5tjheo8'], ['34oi5ne5tj5iojte8oj58otehj5tjheo8' => 55]],
                [],
                [],
                true,
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <embed object_id="55">object embed</embed>.</paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <embed object_id="55">object embed</embed>.</paragraph></section>
',
                [],
                [],
                [],
                [],
                false,
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <embed object_id="55">object embed</embed>.</paragraph></section>
',
            ],

            // EMBED-INLINE
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <embed-inline object_remote_id="34oi5ne5tj5iojte8oj58otehj5tjheo8">object embed</embed-inline>.</paragraph></section>
',
                [[], []],
                [['34oi5ne5tj5iojte8oj58otehj5tjheo8'], ['34oi5ne5tj5iojte8oj58otehj5tjheo8' => 55]],
                [],
                [],
                true,
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <embed-inline object_id="55">object embed</embed-inline>.</paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <embed-inline object_id="55">object embed</embed-inline>.</paragraph></section>
',
                [],
                [],
                [],
                [],
                false,
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <embed-inline object_id="55">object embed</embed-inline>.</paragraph></section>
',
            ],
        ];
    }

    /**
     * @dataProvider providerForTestStoreFieldData
     */
    public function testStoreFieldData(
        $inputXML,
        $getLinksIdData,
        $getObjectIdData,
        $insertLinkData,
        $linkUrlData,
        $expectedReturnValue,
        $expectedResultXML
    ) {
        $versionInfo = new VersionInfo();
        $field = new Field(['value' => new FieldValue(['data' => $inputXML])]);
        $legacyStorage = $this->getPartlyMockedLegacyStorage(['getUrlIdMap', 'getObjectId', 'insertUrl', 'linkUrl']);

        $methodMap = [
            'getUrlIdMap' => $getLinksIdData,
            'getObjectId' => $getObjectIdData,
            'insertUrl' => $insertLinkData,
            'linkUrl' => $linkUrlData,
        ];
        foreach ($methodMap as $method => $data) {
            if (empty($data)) {
                $legacyStorage->expects($this->never())
                    ->method($method);
            } else {
                $legacyStorage->expects($this->once())
                    ->method($method)
                    ->with($this->equalTo($data[0]))
                    ->will($this->returnValue($data[1]));
            }
        }

        $this->assertEquals($expectedReturnValue, $legacyStorage->storeFieldData($versionInfo, $field));
        $this->assertEquals($expectedResultXML, $field->value->data);
    }

    /**
     * @return array
     */
    public function providerForTestStoreFieldDataException()
    {
        /*
         * 1. Input XML
         * 2. Use of getLinksId() in form of array( array $arguments, array $return ), empty means no call
         * 3. Use of getObjectId() in form of array( array $arguments, array $return ), empty means no call
         * 4. Use of insertLink() in form of array( $argument, $return ), empty means no call
         * 5. Expected return value
         * 6. Resulting XML
         */
        return [
            // LINK
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url="">object link</link>.</paragraph></section>
',
                [[], []],
                [[], []],
                [],
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link object_remote_id="34oi5ne5tj5iojte8oj58otehj5tjheo8">object link</link>.</paragraph></section>
',
                [[], []],
                [['34oi5ne5tj5iojte8oj58otehj5tjheo8'], []],
                [],
            ],

            // EMBED
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <embed object_remote_id="34oi5ne5tj5iojte8oj58otehj5tjheo8">object link</embed>.</paragraph></section>
',
                [[], []],
                [['34oi5ne5tj5iojte8oj58otehj5tjheo8'], []],
                [],
            ],

            // EMBED-INLINE
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <embed-inline object_remote_id="34oi5ne5tj5iojte8oj58otehj5tjheo8">object link</embed-inline>.</paragraph></section>
',
                [[], []],
                [['34oi5ne5tj5iojte8oj58otehj5tjheo8'], []],
                [],
            ],
        ];
    }

    /**
     * @dataProvider providerForTestStoreFieldDataException
     */
    public function testStoreFieldDataException(
        $inputXML,
        $getLinksIdData,
        $getObjectIdData,
        $insertLinkData
    ) {
        $this->expectException(\eZ\Publish\Core\Base\Exceptions\NotFoundException::class);

        $versionInfo = new VersionInfo();
        $field = new Field(['value' => new FieldValue(['data' => $inputXML])]);
        $legacyStorage = $this->getPartlyMockedLegacyStorage(['getUrlIdMap', 'getObjectId', 'insertUrl']);

        $methodMap = [
            'getUrlIdMap' => $getLinksIdData,
            'getObjectId' => $getObjectIdData,
            'insertUrl' => $insertLinkData,
        ];
        foreach ($methodMap as $method => $data) {
            if (empty($data)) {
                $legacyStorage->expects($this->never())
                    ->method($method);
            } else {
                $legacyStorage->expects($this->once())
                    ->method($method)
                    ->with($this->equalTo($data[0]))
                    ->will($this->returnValue($data[1]));
            }
        }

        $legacyStorage->storeFieldData($versionInfo, $field);
    }

    /**
     * @return array
     */
    public function providerForTestGetFieldData()
    {
        /*
         * 1. Input XML
         * 2. Use of getLinksUrl() in form of array( array $arguments, array $return ), empty means no call
         * 6. Resulting XML
         */
        return [
            // LINK
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url_id="55">object link</link>.</paragraph></section>
',
                [[55], [55 => '/test']],
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url="/test">object link</link>.</paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url_id="55">object link</link><link url_id="55">object link</link>.</paragraph></section>
',
                [[55], [55 => '/test']],
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url="/test">object link</link><link url="/test">object link</link>.</paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url_id="">object link</link>.</paragraph></section>
',
                [],
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>This is an <link url_id="">object link</link>.</paragraph></section>
',
            ],
        ];
    }

    /**
     * @dataProvider providerForTestGetFieldData
     */
    public function testGetFieldData(
        $inputXML,
        $getLinksUrlData,
        $expectedResultXML
    ) {
        $field = new Field(['value' => new FieldValue(['data' => $inputXML])]);
        $legacyStorage = $this->getPartlyMockedLegacyStorage(['getIdUrlMap']);

        if (empty($getLinksUrlData)) {
            $legacyStorage->expects($this->never())
                ->method('getIdUrlMap');
        } else {
            $legacyStorage->expects($this->once())
                ->method('getIdUrlMap')
                ->with($this->equalTo($getLinksUrlData[0]))
                ->will($this->returnValue($getLinksUrlData[1]));
        }

        $legacyStorage->getFieldData($field);
        $this->assertEquals($expectedResultXML, $field->value->data);
    }
}
