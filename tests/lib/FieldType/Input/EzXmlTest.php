<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType\Input;

use eZ\Publish\Core\FieldType\XmlText\Input\EzXml;
use PHPUnit\Framework\TestCase;
use Exception;

class EzXmlTest extends TestCase
{
    /**
     * @dataProvider providerForTestConvertCorrect
     */
    public function testConvertCorrect($xmlString)
    {
        $input = new EzXml($xmlString);
        $this->assertEquals($xmlString, $input->getInternalRepresentation());
    }

    public function providerForTestConvertCorrect()
    {
        return [
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph>&lt;test&gt;</paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><paragraph><link href="" url="" url_id="1" object_remote_id="" object_id="1" node_id="1">test</link></paragraph></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><header><line>Multi</line><line>line</line></header></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><header><custom name="underline">Underline</custom></header></section>
',
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/"><header><line><strong>Multi</strong></line><line><custom name="underline">line</custom></line></header></section>
',
            ],
        ];
    }

    /**
     * @dataProvider providerForTestConvertIncorrect
     */
    public function testConvertIncorrect($xmlString, $exceptionMessage)
    {
        try {
            $input = new EzXml($xmlString);
        } catch (Exception $e) {
            $this->assertEquals($exceptionMessage, $e->getMessage());

            return;
        }

        $this->fail('Expecting an Exception with message: ' . $exceptionMessage);
    }

    public function providerForTestConvertIncorrect()
    {
        return [
            [
                '<?xml version="1.0" encoding="utf-8"?><section><wrongTag/></section>',
                "Argument 'xmlString' is invalid: Validation of XML content failed: Element 'wrongTag': This element is not expected. Expected is one of ( section, paragraph, header ).",
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?><section><paragraph wrongAttribute="foo">Some content</paragraph>
<paragraph>
<table><tr></tr></table>
<link node_id="abc"><link object_id="123">This is a link</link></link>
</paragraph>
</section>',
                "Argument 'xmlString' is invalid: Validation of XML content failed: Element 'paragraph', attribute 'wrongAttribute': The attribute 'wrongAttribute' is not allowed.
Element 'tr': Missing child element(s). Expected is one of ( th, td ).
Element 'link', attribute 'node_id': 'abc' is not a valid value of the atomic type 'xs:integer'.
Element 'link': This element is not expected. Expected is one of ( custom, strong, emphasize, embed, embed-inline ).",
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?><section><header>With a literal<literal>literal</literal></header></section>',
                "Argument 'xmlString' is invalid: Validation of XML content failed: Element 'literal': This element is not expected. Expected is one of ( custom, strong, emphasize, link, anchor, line ).",
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?><section><header>With a embed <embed /></header></section>',
                "Argument 'xmlString' is invalid: Validation of XML content failed: Element 'embed': This element is not expected. Expected is one of ( custom, strong, emphasize, link, anchor, line ).",
            ],
            [
                '<?xml version="1.0" encoding="utf-8"?><section><header>With a embed <embed-inline /></header></section>',
                "Argument 'xmlString' is invalid: Validation of XML content failed: Element 'embed-inline': This element is not expected. Expected is one of ( custom, strong, emphasize, link, anchor, line ).",
            ],
        ];
    }
}
