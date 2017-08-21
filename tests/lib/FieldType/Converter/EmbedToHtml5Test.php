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

use eZ\Publish\Core\FieldType\XmlText\Converter\EmbedToHtml5;
use eZ\Publish\API\Repository\Values\Content\VersionInfo as APIVersionInfo;
use eZ\Publish\Core\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use PHPUnit_Framework_TestCase;
use DOMDocument;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

/**
 * Tests the EmbedToHtml5 Preconverter
 * Class EmbedToHtml5Test.
 */
class EmbedToHtml5Test extends PHPUnit_Framework_TestCase
{
    /**
     * @return array
     */
    public function providerEmbedXmlSampleContent()
    {
        return array(
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><embed align="right" class="itemized_sub_items" custom:limit="5" custom:offset="3" object_id="104" size="medium" view="embed"/></paragraph></section>',
                104,
                APIVersionInfo::STATUS_DRAFT,
                'embed',
                array(
                    'objectParameters' => array(
                        'align' => 'right',
                        'size' => 'medium',
                        'offset' => 3,
                        'limit' => 5,
                    ),
                    'noLayout' => true,
                ),
                array(
                    array('content', 'read', true),
                    array('content', 'versionread', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><embed class="itemized_sub_items" custom:limit="5" custom:funkyattrib="3" object_id="107" size="medium" view="embed"/></paragraph></section>',
                107,
                APIVersionInfo::STATUS_DRAFT,
                'embed',
                array(
                    'objectParameters' => array(
                        'size' => 'medium',
                        'funkyattrib' => 3,
                        'limit' => 5,
                    ),
                    'noLayout' => true,
                ),
                array(
                    array('content', 'read', false),
                    array('content', 'view_embed', true),
                    array('content', 'versionread', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph><embed-inline object_id="110" size="small" view="embed-inline"/></paragraph></section>',
                110,
                APIVersionInfo::STATUS_PUBLISHED,
                'embed-inline',
                array(
                    'noLayout' => true,
                    'objectParameters' => array(
                        'size' => 'small',
                    ),
                ),
                array(
                    array('content', 'read', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph><embed align="left" custom:limit="5" custom:offset="0" object_id="113" size="large" view="embed"/></paragraph></section>',
                113,
                APIVersionInfo::STATUS_DRAFT,
                'embed',
                array(
                    'noLayout' => true,
                    'objectParameters' => array(
                        'align' => 'left',
                        'size' => 'large',
                        'limit' => '5',
                        'offset' => '0',
                    ),
                ),
                array(
                    array('content', 'read', true),
                    array('content', 'versionread', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/">
<paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
<embed
align="right"
class="itemized_sub_items"
custom:limit="5"
custom:offset="3"
object_id="104"
size="medium"
view="embed"
url="http://ez.no"
/>
</paragraph>
</section>',
                104,
                APIVersionInfo::STATUS_DRAFT,
                'embed',
                array(
                    'objectParameters' => array(
                        'align' => 'right',
                        'size' => 'medium',
                        'offset' => 3,
                        'limit' => 5,
                    ),
                    'noLayout' => true,
                    'linkParameters' => array(
                        'href' => 'http://ez.no',
                        'resourceType' => 'URL',
                        'resourceId' => null,
                        'wrapped' => false,
                    ),
                ),
                array(
                    array('content', 'read', true),
                    array('content', 'versionread', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/">
<paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
<embed
class="itemized_sub_items"
custom:limit="5"
custom:funkyattrib="3"
object_id="107"
size="medium"
view="embed"
url="http://ez.no"
ezlegacytmp-embed-link-target="target"
ezlegacytmp-embed-link-title="title"
ezlegacytmp-embed-link-id="id"
ezlegacytmp-embed-link-class="class"
ezlegacytmp-embed-link-node_id="111"
/>
</paragraph>
</section>',
                107,
                APIVersionInfo::STATUS_DRAFT,
                'embed',
                array(
                    'objectParameters' => array(
                        'size' => 'medium',
                        'funkyattrib' => 3,
                        'limit' => 5,
                    ),
                    'noLayout' => true,
                    'linkParameters' => array(
                        'href' => 'http://ez.no',
                        'target' => 'target',
                        'title' => 'title',
                        'id' => 'id',
                        'class' => 'class',
                        'resourceType' => 'LOCATION',
                        'resourceId' => '111',
                        'wrapped' => false,
                    ),
                ),
                array(
                    array('content', 'read', false),
                    array('content', 'view_embed', true),
                    array('content', 'versionread', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/">
<paragraph>
<embed-inline
object_id="110"
size="small"
view="embed-inline"
url="http://ez.no"
/>
</paragraph>
</section>',
                110,
                APIVersionInfo::STATUS_PUBLISHED,
                'embed-inline',
                array(
                    'noLayout' => true,
                    'objectParameters' => array(
                        'size' => 'small',
                    ),
                    'linkParameters' => array(
                        'href' => 'http://ez.no',
                        'resourceType' => 'URL',
                        'resourceId' => null,
                        'wrapped' => false,
                    ),
                ),
                array(
                    array('content', 'read', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/">
<paragraph>
<embed
align="left"
custom:limit="5"
custom:offset="0"
object_id="113"
size="large"
view="embed"
url="http://ez.no"
ezlegacytmp-embed-link-target="target"
ezlegacytmp-embed-link-title="title"
ezlegacytmp-embed-link-id="id"
ezlegacytmp-embed-link-class="class"
/>
</paragraph>
</section>',
                113,
                APIVersionInfo::STATUS_DRAFT,
                'embed',
                array(
                    'noLayout' => true,
                    'objectParameters' => array(
                        'align' => 'left',
                        'size' => 'large',
                        'limit' => '5',
                        'offset' => '0',
                    ),
                    'linkParameters' => array(
                        'href' => 'http://ez.no',
                        'target' => 'target',
                        'title' => 'title',
                        'id' => 'id',
                        'class' => 'class',
                        'resourceType' => 'URL',
                        'resourceId' => null,
                        'wrapped' => false,
                    ),
                ),
                array(
                    array('content', 'read', true),
                    array('content', 'versionread', true),
                ),
            ),
        );
    }

    /**
     * @return array
     */
    public function providerEmbedXmlSampleLocation()
    {
        return array(
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><embed align="right" class="itemized_sub_items" custom:limit="7" custom:offset="2" node_id="114" size="medium" view="embed"/></paragraph></section>',
                114,
                'embed',
                array(
                    'objectParameters' => array(
                        'align' => 'right',
                        'size' => 'medium',
                        'offset' => 2,
                        'limit' => 7,
                    ),
                    'noLayout' => true,
                ),
                array(
                    array('content', 'read', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><embed align="right" class="itemized_sub_items" custom:limit="7" custom:offset="2" node_id="114" size="medium" view="embed"/></paragraph></section>',
                114,
                'embed',
                array(
                    'objectParameters' => array(
                        'align' => 'right',
                        'size' => 'medium',
                        'offset' => 2,
                        'limit' => 7,
                    ),
                    'noLayout' => true,
                ),
                array(
                    array('content', 'read', false),
                    array('content', 'view_embed', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/">
<paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
<embed
align="right"
class="itemized_sub_items"
custom:limit="7"
custom:offset="2"
node_id="114"
size="medium"
view="embed"
url="http://ez.no"
ezlegacytmp-embed-link-node_id="222"
ezlegacytmp-embed-link-object_id="333"
/>
</paragraph>
</section>',
                114,
                'embed',
                array(
                    'objectParameters' => array(
                        'align' => 'right',
                        'size' => 'medium',
                        'offset' => 2,
                        'limit' => 7,
                    ),
                    'noLayout' => true,
                    'linkParameters' => array(
                        'href' => 'http://ez.no',
                        'resourceType' => 'CONTENT',
                        'resourceId' => '333',
                        'wrapped' => false,
                    ),
                ),
                array(
                    array('content', 'read', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns="http://www.w3.org/1999/html">
<paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
<link>
<embed
align="right"
class="itemized_sub_items"
custom:limit="7"
custom:offset="2"
node_id="114"
size="medium"
view="embed"
url="http://ez.no"
ezlegacytmp-embed-link-target="target"
ezlegacytmp-embed-link-title="title"
ezlegacytmp-embed-link-id="id"
ezlegacytmp-embed-link-class="class"
ezlegacytmp-embed-link-anchor_name="anchovy"
/>
</link>
</paragraph>
</section>',
                114,
                'embed',
                array(
                    'objectParameters' => array(
                        'align' => 'right',
                        'size' => 'medium',
                        'offset' => 2,
                        'limit' => 7,
                    ),
                    'noLayout' => true,
                    'linkParameters' => array(
                        'href' => 'http://ez.no',
                        'target' => 'target',
                        'title' => 'title',
                        'id' => 'id',
                        'class' => 'class',
                        'resourceType' => 'URL',
                        'resourceId' => null,
                        'resourceFragmentIdentifier' => 'anchovy',
                        'wrapped' => false,
                    ),
                ),
                array(
                    array('content', 'read', false),
                    array('content', 'view_embed', true),
                ),
            ),
        );
    }

    /**
     * @return array
     */
    public function providerEmbedXmlBadSample()
    {
        return array(
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><embed align="right" class="itemized_sub_items" custom:limit="5" custom:offset="3" custom:object_id="105" object_id="104" size="medium" view="embed"/></paragraph></section>',
                104,
                APIVersionInfo::STATUS_PUBLISHED,
                'embed',
                array(
                    'noLayout' => true,
                    'objectParameters' => array(
                        'align' => 'right',
                        'size' => 'medium',
                        'limit' => 5,
                        'offset' => 3,
                    ),
                ),
                array(
                    array('content', 'read', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/">
<paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/">
<embed
align="right"
class="itemized_sub_items"
custom:limit="5"
custom:offset="3"
custom:object_id="105"
object_id="104"
size="medium"
view="embed"
url="http://ez.no"
ezlegacytmp-embed-link-target="target"
ezlegacytmp-embed-link-title="title"
ezlegacytmp-embed-link-id="id"
ezlegacytmp-embed-link-class="class"
ezlegacytmp-embed-link-node_id="222"
/>
</paragraph>
</section>',
                104,
                APIVersionInfo::STATUS_PUBLISHED,
                'embed',
                array(
                    'noLayout' => true,
                    'objectParameters' => array(
                        'align' => 'right',
                        'size' => 'medium',
                        'limit' => 5,
                        'offset' => 3,
                    ),
                    'linkParameters' => array(
                        'href' => 'http://ez.no',
                        'target' => 'target',
                        'title' => 'title',
                        'id' => 'id',
                        'class' => 'class',
                        'resourceType' => 'LOCATION',
                        'resourceId' => '222',
                        'wrapped' => false,
                    ),
                ),
                array(
                    array('content', 'read', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/">
<paragraph>
<link>
<embed-inline
align="right"
class="itemized_sub_items"
custom:limit="5"
custom:offset="3"
custom:object_id="105"
object_id="104"
size="medium"
view="embed"
url="http://ez.no"
ezlegacytmp-embed-link-target="target"
ezlegacytmp-embed-link-title="title"
ezlegacytmp-embed-link-id="id"
ezlegacytmp-embed-link-class="class"
ezlegacytmp-embed-link-node_id="222"
/>
and that was embedded
</link>
</paragraph>
</section>',
                104,
                APIVersionInfo::STATUS_PUBLISHED,
                'embed',
                array(
                    'noLayout' => true,
                    'objectParameters' => array(
                        'align' => 'right',
                        'size' => 'medium',
                        'limit' => 5,
                        'offset' => 3,
                    ),
                    'linkParameters' => array(
                        'href' => 'http://ez.no',
                        'target' => 'target',
                        'title' => 'title',
                        'id' => 'id',
                        'class' => 'class',
                        'resourceType' => 'LOCATION',
                        'resourceId' => '222',
                        'wrapped' => true,
                    ),
                ),
                array(
                    array('content', 'read', true),
                ),
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/">
<paragraph>
<link>
<embed-inline
align="right"
class="itemized_sub_items"
custom:limit="5"
custom:offset="3"
custom:object_id="105"
object_id="104"
size="medium"
view="embed"
url="http://ez.no"
ezlegacytmp-embed-link-target="target"
ezlegacytmp-embed-link-title="title"
ezlegacytmp-embed-link-id="id"
ezlegacytmp-embed-link-class="class"
ezlegacytmp-embed-link-node_id="222"
/>
</link>
</paragraph>
</section>',
                104,
                APIVersionInfo::STATUS_PUBLISHED,
                'embed',
                array(
                    'noLayout' => true,
                    'objectParameters' => array(
                        'align' => 'right',
                        'size' => 'medium',
                        'limit' => 5,
                        'offset' => 3,
                    ),
                    'linkParameters' => array(
                        'href' => 'http://ez.no',
                        'target' => 'target',
                        'title' => 'title',
                        'id' => 'id',
                        'class' => 'class',
                        'resourceType' => 'LOCATION',
                        'resourceId' => '222',
                        'wrapped' => false,
                    ),
                ),
                array(
                    array('content', 'read', true),
                ),
            ),
        );
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockFragmentHandler()
    {
        return $this->getMockBuilder('Symfony\\Component\\HttpKernel\\Fragment\\FragmentHandler')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockContentService()
    {
        return $this->getMockBuilder('eZ\\Publish\\Core\\Repository\\ContentService')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockLocationService()
    {
        return $this->getMockBuilder('eZ\\Publish\\Core\\Repository\\LocationService')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getLoggerMock()
    {
        return $this->getMock('Psr\\Log\\LoggerInterface');
    }

    /**
     * @param $contentService
     * @param $locationService
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockRepository($contentService, $locationService)
    {
        $repositoryClass = 'eZ\\Publish\\Core\\Repository\\Repository';
        $repository = $this
            ->getMockBuilder($repositoryClass)
            ->disableOriginalConstructor()
            ->getMock();

        $repository->expects($this->any())
            ->method('sudo')
            ->with($this->anything())
            ->will($this->returnCallback(
                function ($callback) use ($repository) {
                    return $callback($repository);
                }
            ));

        $repository->expects($this->any())
            ->method('getContentService')
            ->will($this->returnValue($contentService));

        $repository->expects($this->any())
            ->method('getLocationService')
            ->will($this->returnValue($locationService));

        return $repository;
    }

    /**
     * @param $xmlString
     * @param $contentId
     * @param $status
     * @param $view
     * @param $parameters
     * @param $permissionsMap
     */
    public function runNodeEmbedContent($xmlString, $contentId, $status, $view, $parameters, $permissionsMap)
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xmlString);

        $fragmentHandler = $this->getMockFragmentHandler();
        $contentService = $this->getMockContentService();

        $versionInfo = $this->getMock('eZ\\Publish\\API\\Repository\\Values\\Content\\VersionInfo');
        $versionInfo->expects($this->any())
            ->method('__get')
            ->with('status')
            ->will($this->returnValue($status));

        $content = $this->getMock('eZ\\Publish\\API\\Repository\\Values\\Content\\Content');
        $content->expects($this->any())
            ->method('getVersionInfo')
            ->will($this->returnValue($versionInfo));

        $contentService->expects($this->once())
            ->method('loadContent')
            ->with($this->equalTo($contentId))
            ->will($this->returnValue($content));

        $repository = $this->getMockRepository($contentService, null);
        foreach ($permissionsMap as $index => $permissions) {
            $repository->expects($this->at($index + 2))
                ->method('canUser')
                ->with(
                    $permissions[0],
                    $permissions[1],
                    $content,
                    null
                )
                ->will(
                    $this->returnValue($permissions[2])
                );
        }

        $fragmentHandler->expects($this->once())
            ->method('render')
            ->with(
                new ControllerReference(
                    'ez_content:embedAction',
                    array(
                        'contentId' => $contentId,
                        'viewType' => $view,
                        'layout' => false,
                        'params' => $parameters,
                    )
                )
            );

        $converter = new EmbedToHtml5(
            $fragmentHandler,
            $repository,
            array('view', 'class', 'node_id', 'object_id'),
            $this->getMock('Psr\\Log\\LoggerInterface')
        );

        $converter->convert($dom);
    }

    /**
     * @param $xmlString
     * @param $locationId
     * @param $view
     * @param $parameters
     * @param $permissionsMap
     */
    public function runNodeEmbedLocation($xmlString, $locationId, $view, $parameters, $permissionsMap)
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xmlString);

        $fragmentHandler = $this->getMockFragmentHandler();
        $locationService = $this->getMockLocationService();

        $contentInfo = new ContentInfo(array('id' => 42));
        $location = new Location(array('id' => $locationId, 'contentInfo' => $contentInfo));

        $locationService->expects($this->once())
            ->method('loadLocation')
            ->with($this->equalTo($locationId))
            ->will($this->returnValue($location));

        $repository = $this->getMockRepository(null, $locationService);
        foreach ($permissionsMap as $index => $permissions) {
            $repository->expects($this->at($index + 2))
                ->method('canUser')
                ->with(
                    $permissions[0],
                    $permissions[1],
                    $contentInfo,
                    $location
                )
                ->will(
                    $this->returnValue($permissions[2])
                );
        }

        $fragmentHandler->expects($this->once())
            ->method('render')
            ->with(
                new ControllerReference(
                    'ez_content:embedAction',
                    array(
                        'contentId' => $location->getContentInfo()->id,
                        'locationId' => $location->id,
                        'viewType' => $view,
                        'layout' => false,
                        'params' => $parameters,
                    )
                )
            );

        $converter = new EmbedToHtml5(
            $fragmentHandler,
            $repository,
            array('view', 'class', 'node_id', 'object_id'),
            $this->getMock('Psr\\Log\\LoggerInterface')
        );

        $converter->convert($dom);
    }

    /**
     * Basic test to see if preconverter will build an embed.
     *
     * @dataProvider providerEmbedXmlSampleContent
     */
    public function testProperEmbedsContent($xmlString, $contentId, $status, $view, $parameters, $permissionsMap)
    {
        $this->runNodeEmbedContent($xmlString, $contentId, $status, $view, $parameters, $permissionsMap);
    }

    /**
     * Basic test to see if preconverter will build an embed.
     *
     * @dataProvider providerEmbedXmlSampleLocation
     */
    public function testProperEmbedsLocation($xmlString, $locationId, $view, $parameters, $permissionsMap)
    {
        $this->runNodeEmbedLocation($xmlString, $locationId, $view, $parameters, $permissionsMap);
    }

    /**
     * Ensure converter doesn't pass on non-custom attributes.
     *
     * @dataProvider providerEmbedXmlBadSample
     */
    public function testImproperEmbeds($xmlString, $contentId, $status, $view, $parameters, $permissionsMap)
    {
        $this->runNodeEmbedContent($xmlString, $contentId, $status, $view, $parameters, $permissionsMap);
    }

    public function providerForTestEmbedContentThrowsUnauthorizedException()
    {
        return array(
            array(
                array(
                    array('content', 'read', false),
                    array('content', 'view_embed', false),
                ),
            ),
            array(
                array(
                    array('content', 'read', false),
                    array('content', 'view_embed', true),
                    array('content', 'versionread', false),
                ),
            ),
            array(
                array(
                    array('content', 'read', true),
                    array('content', 'versionread', false),
                ),
            ),
        );
    }

    /**
     * @dataProvider providerForTestEmbedContentThrowsUnauthorizedException
     * @expectedException \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testEmbedContentThrowsUnauthorizedException($permissionsMap)
    {
        $dom = new \DOMDocument();
        $dom->loadXML('<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><embed view="embed" object_id="42" url="http://www.ez.no"/></paragraph></section>');

        $fragmentHandler = $this->getMockFragmentHandler();
        $contentService = $this->getMockContentService();

        $versionInfo = $this->getMock('eZ\\Publish\\API\\Repository\\Values\\Content\\VersionInfo');
        $versionInfo->expects($this->any())
            ->method('__get')
            ->with('status')
            ->will($this->returnValue(APIVersionInfo::STATUS_DRAFT));

        $content = $this->getMock('eZ\\Publish\\API\\Repository\\Values\\Content\\Content');
        $content->expects($this->any())
            ->method('getVersionInfo')
            ->will($this->returnValue($versionInfo));

        $contentService->expects($this->once())
            ->method('loadContent')
            ->with($this->equalTo(42))
            ->will($this->returnValue($content));

        $repository = $this->getMockRepository($contentService, null);
        foreach ($permissionsMap as $index => $permissions) {
            $repository->expects($this->at($index + 2))
                ->method('canUser')
                ->with(
                    $permissions[0],
                    $permissions[1],
                    $content,
                    null
                )
                ->will(
                    $this->returnValue($permissions[2])
                );
        }

        $converter = new EmbedToHtml5(
            $fragmentHandler,
            $repository,
            array('view', 'class', 'node_id', 'object_id'),
            $this->getMock('Psr\\Log\\LoggerInterface')
        );

        $converter->convert($dom);
    }

    /**
     * @expectedException \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testEmbedLocationThrowsUnauthorizedException()
    {
        $dom = new \DOMDocument();
        $dom->loadXML('<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><embed view="embed" node_id="42" url="http://www.ez.no"/></paragraph></section>');

        $fragmentHandler = $this->getMockFragmentHandler();
        $locationService = $this->getMockLocationService();

        $contentInfo = $this->getMock('eZ\\Publish\\API\\Repository\\Values\\Content\\ContentInfo');
        $location = $this->getMock('eZ\\Publish\\API\\Repository\\Values\\Content\\Location');
        $location
            ->expects($this->exactly(2))
            ->method('getContentInfo')
            ->will($this->returnValue($contentInfo));

        $locationService->expects($this->once())
            ->method('loadLocation')
            ->with($this->equalTo(42))
            ->will($this->returnValue($location));

        $repository = $this->getMockRepository(null, $locationService);
        $repository->expects($this->at(2))
            ->method('canUser')
            ->with('content', 'read', $contentInfo, $location)
            ->will(
                $this->returnValue(false)
            );
        $repository->expects($this->at(3))
            ->method('canUser')
            ->with('content', 'view_embed', $contentInfo, $location)
            ->will(
                $this->returnValue(false)
            );

        $converter = new EmbedToHtml5(
            $fragmentHandler,
            $repository,
            array('view', 'class', 'node_id', 'object_id'),
            $this->getMock('Psr\\Log\\LoggerInterface')
        );

        $converter->convert($dom);
    }

    public function dataProviderForTestEmbedContentNotFound()
    {
        return array(
            array(
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><embed object_id="42" url="http://www.ez.no"/></paragraph></section>',
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"/></section>',
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph>hello <embed object_id="42" url="http://www.ez.no"/> goodbye</paragraph></section>',
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph>hello  goodbye</paragraph></section>',
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph><link>hello <embed size="medium" object_id="42" url="http://www.ez.no"/> goodbye</link></paragraph></section>',
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph><link>hello  goodbye</link></paragraph></section>',
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph><link><embed object_id="42" url="http://www.ez.no"/></link></paragraph></section>',
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph><link/></paragraph></section>',
            ),
        );
    }

    /**
     * @param string $input
     * @param string $output
     *
     * @dataProvider dataProviderForTestEmbedContentNotFound
     */
    public function testEmbedContentNotFound($input, $output)
    {
        $fragmentHandler = $this->getMockFragmentHandler();
        $contentService = $this->getMockContentService();
        $repository = $this->getMockRepository($contentService, null);
        $logger = $this->getLoggerMock();

        $contentService->expects($this->once())
            ->method('loadContent')
            ->with($this->equalTo(42))
            ->will(
                $this->throwException(
                    $this->getMock('eZ\\Publish\\API\\Repository\\Exceptions\\NotFoundException')
                )
            );

        $logger->expects($this->at(0))
            ->method('error')
            ->with(
                'While generating embed for xmltext, could not locate Content object with ID 42'
            );

        $converter = new EmbedToHtml5(
            $fragmentHandler,
            $repository,
            array('view', 'class', 'node_id', 'object_id'),
            $logger
        );

        $document = new DOMDocument();
        $document->loadXML($input);

        $converter->convert($document);

        $outputDocument = new DOMDocument();
        $outputDocument->loadXML($output);

        $this->assertEquals($outputDocument, $document);
    }

    public function dataProviderForTestEmbedLocationNotFound()
    {
        return array(
            array(
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"><embed node_id="42" url="http://www.ez.no"/></paragraph></section>',
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"/></section>',
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph>hello <embed node_id="42" url="http://www.ez.no"/> goodbye</paragraph></section>',
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph>hello  goodbye</paragraph></section>',
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph><link>hello <embed node_id="42" url="http://www.ez.no"/> goodbye</link></paragraph></section>',
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph><link>hello  goodbye</link></paragraph></section>',
            ),
            array(
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph><link><embed node_id="42" url="http://www.ez.no"/></link></paragraph></section>',
                '<?xml version="1.0" encoding="utf-8"?><section xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/" xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/"><paragraph><link/></paragraph></section>',
            ),
        );
    }

    /**
     * @param string $input
     * @param string $output
     *
     * @dataProvider dataProviderForTestEmbedLocationNotFound
     */
    public function testEmbedLocationNotFound($input, $output)
    {
        $fragmentHandler = $this->getMockFragmentHandler();
        $locationService = $this->getMockLocationService();
        $repository = $this->getMockRepository(null, $locationService);
        $logger = $this->getLoggerMock();

        $locationService->expects($this->once())
            ->method('loadLocation')
            ->with($this->equalTo(42))
            ->will(
                $this->throwException(
                    $this->getMock('eZ\\Publish\\API\\Repository\\Exceptions\\NotFoundException')
                )
            );

        $logger->expects($this->at(0))
            ->method('error')
            ->with(
                'While generating embed for xmltext, could not locate Location with ID 42'
            );

        $converter = new EmbedToHtml5(
            $fragmentHandler,
            $repository,
            array('view', 'class', 'node_id', 'object_id'),
            $logger
        );

        $document = new DOMDocument();
        $document->loadXML($input);

        $converter->convert($document);

        $outputDocument = new DOMDocument();
        $outputDocument->loadXML($output);

        $this->assertEquals($outputDocument, $document);
    }

    /**
     * @param string $input
     * @param string $output
     * @param string $contentReplacement
     * @param int    $contentId
     *
     * @dataProvider providerEmbedRemovesTextContent
     */
    public function testEmbedRemovesTextContent($input, $output, $contentReplacement, $contentId)
    {
        $status = APIVersionInfo::STATUS_DRAFT;
        $permissionsMap = array(
            array('content', 'read', true),
            array('content', 'versionread', true),
        );



        $dom = new \DOMDocument();
        $dom->loadXML($input);

        $fragmentHandler = $this->getMockFragmentHandler();
        $contentService = $this->getMockContentService();

        $versionInfo = $this->getMock('eZ\\Publish\\API\\Repository\\Values\\Content\\VersionInfo');
        $versionInfo->expects($this->any())
            ->method('__get')
            ->with('status')
            ->will($this->returnValue($status));

        $content = $this->getMock('eZ\\Publish\\API\\Repository\\Values\\Content\\Content');
        $content->expects($this->any())
            ->method('getVersionInfo')
            ->will($this->returnValue($versionInfo));

        $contentService->expects($this->once())
            ->method('loadContent')
            ->with($this->equalTo($contentId))
            ->will($this->returnValue($content));

        $repository = $this->getMockRepository($contentService, null);
        foreach ($permissionsMap as $index => $permissions) {
            $repository->expects($this->at($index + 2))
                ->method('canUser')
                ->with(
                    $permissions[0],
                    $permissions[1],
                    $content,
                    null
                )
                ->will(
                    $this->returnValue($permissions[2])
                );
        }

        $fragmentHandler->expects($this->once())
            ->method('render')
            ->will($this->returnValue($contentReplacement));

        $converter = new EmbedToHtml5(
            $fragmentHandler,
            $repository,
            array('view', 'class', 'node_id', 'object_id'),
            $this->getMock('Psr\\Log\\LoggerInterface')
        );

        $converter->convert($dom);

        $outputDocument = new DOMDocument();
        $outputDocument->loadXML($output);

        $this->assertEquals($outputDocument, $dom);

    }

    public function providerEmbedRemovesTextContent()
    {
        $xmlFramework = '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">
<paragraph>eZ Publish</paragraph>
<paragraph>
%s
</paragraph>
</section>';

        return array(
            array(
                sprintf($xmlFramework, '<embed-inline object_id="123">content to be removed</embed-inline>'),
                sprintf($xmlFramework, '<embed-inline object_id="123">ContentReplacement</embed-inline>'),
                'ContentReplacement',
                123
            ),
            array(
                sprintf($xmlFramework, '<embed object_id="789">Content to be removed</embed>'),
                sprintf($xmlFramework, '<embed object_id="789">Other random content</embed>'),
                'Other random content',
                789
            ),
        );
    }
}
