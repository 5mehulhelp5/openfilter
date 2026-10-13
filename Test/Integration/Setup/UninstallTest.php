<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Setup;

use Iranimij\OpenFilter\Setup\Uninstall;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The uninstall class knows every table of the declarative schema (a new table cannot be forgotten), in an order the
 * foreign keys allow, and its patch filter matches the recorded data patches. The drop itself is unit-tested: DDL
 * would commit inside the shared integration database.
 */
class UninstallTest extends TestCase
{
    public function testEveryDeclaredTableIsDroppedChildrenFirst(): void
    {
        $path = Bootstrap::getObjectManager()->get(ComponentRegistrar::class)
            ->getPath(ComponentRegistrar::MODULE, 'Iranimij_OpenFilter');
        $schema = simplexml_load_file($path . '/etc/db_schema.xml');
        $declared = array_map('strval', $schema->xpath('//table/@name') ?: []);
        sort($declared);
        $listed = Uninstall::TABLES;
        sort($listed);

        self::assertSame($declared, $listed);

        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $position = array_flip(Uninstall::TABLES);
        foreach (Uninstall::TABLES as $table) {
            self::assertTrue($connection->isTableExists($connection->getTableName($table)), $table);
            foreach ($connection->getForeignKeys($connection->getTableName($table)) as $key) {
                $parent = (string) $key['REF_TABLE_NAME'];
                if (isset($position[$parent])) {
                    self::assertGreaterThan($position[$table], $position[$parent], "$table before $parent");
                }
            }
        }
    }

    public function testPatchFilterMatchesTheRecordedPatches(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $select = $connection->select()->from($connection->getTableName('patch_list'), 'patch_name')
            ->where('patch_name LIKE ?', 'Iranimij\\\\OpenFilter\\\\%');

        self::assertContains(
            'Iranimij\OpenFilter\Setup\Patch\Data\DefaultFilterConfig',
            $connection->fetchCol($select)
        );
    }
}
