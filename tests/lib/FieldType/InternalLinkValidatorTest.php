<?php
/**
 * File containing the eZ\Publish\Core\FieldType\XmlText\InternalLinkValidator class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType;

use eZ\Publish\Core\Base\Tests\PHPUnit5CompatTrait;
use eZ\Publish\Core\FieldType\XmlText\InternalLinkValidator;
use eZ\Publish\SPI\Persistence\Content\Handler as ContentHandler;
use eZ\Publish\SPI\Persistence\Content\Location\Handler as LocationHandler;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use PHPUnit\Framework\TestCase;

class InternalLinkValidatorTest extends TestCase
{
    use PHPUnit5CompatTrait;

    /** @var \eZ\Publish\SPI\Persistence\Content\Handler|\PHPUnit_Framework_MockObject_MockObject */
    private $contentHandler;
    /** @var \eZ\Publish\SPI\Persistence\Content\Location\Handler|\PHPUnit_Framework_MockObject_MockObject */
    private $locationHandler;

    /**
     * @before
     */
    public function setupInternalLinkValidator()
    {
        $this->contentHandler = $this->createMock(ContentHandler::class);
        $this->locationHandler = $this->createMock(LocationHandler::class);
    }

    public function testValidateEzObjectExistingTarget()
    {
        $objectId = 2;

        $this->contentHandler
            ->expects($this->once())
            ->method('loadContentInfo')
            ->with($objectId);

        $validator = $this->getInternalLinkValidator();

        $errors = $validator->validate($this->createInputDocument([
            [
                'scheme' => 'ezobject',
                'target' => $objectId,
                'anchor_name' => null,
            ],
        ]));

        $this->assertEmpty($errors);
    }

    /**
     * @dataProvider getValidateEzObjectNonExistingTargetData
     */
    public function testValidateEzObjectNonExistingTarget($objectId, $anchorName)
    {
        $exception = $this->createMock(NotFoundException::class);

        $this->contentHandler
            ->expects($this->once())
            ->method('loadContentInfo')
            ->with($objectId)
            ->willThrowException($exception);

        $validator = $this->getInternalLinkValidator();

        $errors = $validator->validate($this->createInputDocument([
            [
                'scheme' => 'ezobject',
                'target' => $objectId,
                'anchor_name' => $anchorName,
            ],
        ]));

        $this->assertCount(1, $errors);
        $this->assertContainsEzObjectInvalidLinkError($objectId, $anchorName, $errors);
    }

    public function getValidateEzObjectNonExistingTargetData()
    {
        return [
            [
                'objectId' => 2,
                'anchorName' => null,
            ],
            [
                'objectId' => 2,
                'anchorName' => 'anchor',
            ],
        ];
    }

    public function testValidateEzNodeExistingTarget()
    {
        $nodeId = 2;

        $this->locationHandler
            ->expects($this->once())
            ->method('load')
            ->with($nodeId);

        $validator = $this->getInternalLinkValidator();

        $errors = $validator->validate($this->createInputDocument([
            [
                'scheme' => 'eznode',
                'target' => $nodeId,
                'anchor_name' => null,
            ],
        ]));

        $this->assertEmpty($errors);
    }

    /**
     * @dataProvider getValidateEzNodeNonExistingTargetData
     */
    public function testValidateEzNodeNonExistingTarget($nodeId, $anchorName)
    {
        $exception = $this->createMock(NotFoundException::class);
        $this->locationHandler
            ->expects($this->once())
            ->method('load')
            ->with($nodeId)
            ->willThrowException($exception);

        $validator = $this->getInternalLinkValidator();

        $errors = $validator->validate($this->createInputDocument([
            [
                'scheme' => 'eznode',
                'target' => $nodeId,
                'anchor_name' => $anchorName,
            ],
        ]));

        $this->assertCount(1, $errors);
        $this->assertContainsEzNodeInvalidLinkError($nodeId, $anchorName, $errors);
    }

    public function getValidateEzNodeNonExistingTargetData()
    {
        return [
            [
                'nodeId' => 2,
                'anchorName' => null,
            ],
            [
                'nodeId' => 2,
                'anchroName' => 'anchor',
            ],
        ];
    }

    private function assertContainsEzObjectInvalidLinkError($target, $anchorName, array $errors)
    {
        $format = 'Invalid link "ezobject://%s%s": target object cannot be found';

        $this->assertContains(sprintf($format, $target, ($anchorName ? '#' . $anchorName : '')), $errors);
    }

    private function assertContainsEzNodeInvalidLinkError($target, $anchorName, array $errors)
    {
        $format = 'Invalid link "eznode://%s%s": target node cannot be found';

        $this->assertContains(sprintf($format, $target, ($anchorName ? '#' . $anchorName : '')), $errors);
    }

    /**
     * @return \eZ\Publish\Core\FieldType\XmlText\InternalLinkValidator|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getInternalLinkValidator(array $methods = null)
    {
        return $this->getMockBuilder(InternalLinkValidator::class)
            ->setMethods($methods)
            ->setConstructorArgs([
                $this->contentHandler,
                $this->locationHandler,
            ])
            ->getMock();
    }

    private function createInputDocument(array $urls = [])
    {
        $links = [];
        foreach ($urls as $url) {
            switch ($url['scheme']) {
                case 'eznode':
                    $links[] = $this->createEzNodeLink($url['target'], $url['anchor_name']);
                    break;
                case 'ezobject':
                    $links[] = $this->creteEzObjectLink($url['target'], $url['anchor_name']);
                    break;
            }
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>' . implode('', $links) . '</paragraph></section>';

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        return $doc;
    }

    private function createEzNodeLink($id, $anchorName = null)
    {
        if (!$anchorName) {
            return sprintf('<link node_id="%s">Link</link>', $id);
        }

        return sprintf('<link node_id="%s" anchor_name="%s">Link</link>', $id, $anchorName);
    }

    private function creteEzObjectLink($id, $anchorName = null)
    {
        if (!$anchorName) {
            return sprintf('<link object_id="%s">Link</link>', $id);
        }

        return sprintf('<link object_id="%s" anchor_name="%s">Link</link>', $id, $anchorName);
    }
}
