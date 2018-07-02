<?php
/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\FieldType\XmlText\Converter;

use eZ\Publish\Core\FieldType\XmlText\Converter;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Class TableToRichText.
 *
 * In ezxmltext we may have empty rows like this:
 * <table class="list" width="90%" border="0" ez-colums="3">
 * <tr/>
 *
 * In richtext, such rows needs to include the <td> tags too
 *  <informaltable class="list" width="90%">
 *    <tbody>
 *      <tr>
 *        <td/>
 *        <td/>
 *        <td/>
 *      </tr>
 */
class TableToRichText implements Converter
{
    /**
     * Attribute used for storing number of table columns.
     *
     * @const string
     */
    const ATTRIBUTE_COLUMNS = 'ez-columns';

    protected function getNumberOfColumns(DOMElement $tableElement)
    {
        // Let's first check if we have already calculated number of columns for this table
        if ($tableElement->hasAttribute(self::ATTRIBUTE_COLUMNS)) {
            $numberOfColumns = $tableElement->getAttribute(self::ATTRIBUTE_COLUMNS);
        } else {
            $numberOfColumns = 1;
            foreach ($tableElement->childNodes as $tableRow) {
                if ($tableRow->childNodes->length > $numberOfColumns) {
                    $numberOfColumns = $tableRow->childNodes->length;
                }
            }
            $tableElement->setAttribute(self::ATTRIBUTE_COLUMNS, $numberOfColumns);
        }

        return $numberOfColumns;
    }

    public function convert(DOMDocument $document)
    {
        $xpath = new DOMXPath($document);

        // Get all empty table rows
        $xpathExpression = '//table/tr[count(*) = 0]';

        $emptyRows = $xpath->query($xpathExpression);
        foreach ($emptyRows as $row) {
            $tableElement = $row->parentNode;
            $numberOfColumns = $this->getNumberOfColumns($tableElement);
            for ($i = 0; $i < $numberOfColumns; ++$i) {
                $row->appendChild($document->createElement('td'));
            }
        }
    }
}
