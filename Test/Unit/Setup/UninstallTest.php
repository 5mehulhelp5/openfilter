<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Setup;

use Iranimij\OpenFilter\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use PHPUnit\Framework\TestCase;

class UninstallTest extends TestCase
{
    public function testDropsEveryTableSettingsAndPatchRecords(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $setup = $this->createStub(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $t): string => 'pfx_' . $t);
        $connection->method('isTableExists')->willReturnCallback(static fn (string $t): bool => $t !== 'pfx_openfilter_builtin_store');
        $dropped = [];
        $connection->method('dropTable')->willReturnCallback(static function (string $table) use (&$dropped): bool {
            $dropped[] = $table;

            return true;
        });
        $deleted = [];
        $connection->method('delete')->willReturnCallback(static function (string $table, array $where) use (&$deleted): int {
            $deleted[] = [$table, $where];

            return 1;
        });

        (new Uninstall())->uninstall($setup, $this->createStub(ModuleContextInterface::class));

        self::assertSame([
            'pfx_openfilter_filter_category',
            'pfx_openfilter_option_slug',
            'pfx_openfilter_attribute_slug',
            'pfx_openfilter_attribute_store',
            'pfx_openfilter_attribute',
            'pfx_openfilter_builtin',
        ], $dropped, 'children first, missing tables skipped');
        self::assertSame([
            ['pfx_core_config_data', ['path LIKE ?' => 'openfilter/%']],
            ['pfx_patch_list', ['patch_name LIKE ?' => 'Iranimij\\\\OpenFilter\\\\%']],
        ], $deleted);
    }
}
