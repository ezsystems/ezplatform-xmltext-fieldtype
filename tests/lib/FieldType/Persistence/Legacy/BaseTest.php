<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace EzSystems\EzPlatformXmlTextFieldType\Tests\FieldType\Persistence\Legacy;

use Doctrine\DBAL\DBALException;
use ErrorException;
use eZ\Publish\API\Repository\Tests\BaseTest as APIBaseTest;
use eZ\Publish\SPI\Tests\Persistence\FileFixtureFactory;
use eZ\Publish\SPI\Tests\Persistence\FixtureImporter;

abstract class BaseTest extends APIBaseTest
{
    /**
     * @param string $file
     */
    protected function insertDatabaseFixture(string $file): void
    {
        try {
            $fixtureImporter = new FixtureImporter($this->getRawDatabaseConnection());
            $fixtureImporter->import((new FileFixtureFactory())->buildFixture($file));
        } catch (ErrorException | DBALException $e) {
            self::fail('Database fixture import failed: ' . $e->getMessage());
        }
    }
}
