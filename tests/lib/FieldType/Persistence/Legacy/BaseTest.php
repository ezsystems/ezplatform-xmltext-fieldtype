<?php
/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType\Persistence\Legacy;

use eZ\Publish\API\Repository\Tests\BaseTest as APIBaseTest;

abstract class BaseTest extends APIBaseTest
{
    /**
     * Taken from ezpublish-kernel/eZ/Publish/Core/Persistence/Legacy/Tests/TestCase.php.
     *
     * @param string $file
     * @throws \Exception
     */
    protected function insertDatabaseFixture($file)
    {
        $data = require $file;
        $db = $this->getSetupFactory()->getDatabaseHandler();

        foreach ($data as $table => $rows) {
            // Check that at least one row exists
            if (!isset($rows[0])) {
                continue;
            }

            $q = $db->createInsertQuery();
            $q->insertInto($db->quoteIdentifier($table));

            // Contains the bound parameters
            $values = [];

            // Binding the parameters
            foreach ($rows[0] as $col => $val) {
                $q->set(
                    $db->quoteIdentifier($col),
                    $q->bindParam($values[$col])
                );
            }

            $stmt = $q->prepare();

            foreach ($rows as $row) {
                try {
                    // This CANNOT be replaced by:
                    // $values = $row
                    // each $values[$col] is a PHP reference which should be
                    // kept for parameters binding to work
                    foreach ($row as $col => $val) {
                        $values[$col] = $val;
                    }

                    $stmt->execute();
                } catch (Exception $e) {
                    echo "$table ( ", implode(', ', $row), " )\n";
                    throw $e;
                }
            }
        }

        $this->resetSequences();
    }

    public function resetSequences()
    {
        switch ($this->getDB()) {
            case 'pgsql':
                // Update PostgreSQL sequences
                $handler = $this->getSetupFactory()->getDatabaseHandler();

                $queries = array_filter(preg_split('(;\\s*$)m',
                    file_get_contents(__DIR__ . '/_fixtures/setval.pgsql.sql')));
                foreach ($queries as $query) {
                    $handler->exec($query);
                }
        }
    }
}
