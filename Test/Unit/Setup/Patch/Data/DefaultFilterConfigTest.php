<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Setup\Patch\Data;

use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Setup\Patch\Data\DefaultFilterConfig;
use Magento\Framework\FlagManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DefaultFilterConfigTest extends TestCase
{
    private const ATTRIBUTES = [
        10 => ['code' => 'material', 'input_type' => 'select', 'swatch_type' => null, 'position' => 1],
        11 => ['code' => 'color', 'input_type' => 'select', 'swatch_type' => 'visual', 'position' => 5],
        12 => ['code' => 'price', 'input_type' => 'price', 'swatch_type' => null, 'position' => 0, 'attribute_id' => 12],
        13 => ['code' => 'size', 'input_type' => 'select', 'swatch_type' => 'text', 'position' => 2],
        14 => ['code' => 'brand', 'input_type' => 'select', 'swatch_type' => null, 'position' => 0],
    ];

    public function testANewInstallOrdersSwatchesBeforeTheOtherFilters(): void
    {
        $flags = $this->createMock(FlagManager::class);
        $flags->expects(self::once())->method('saveFlag')->with(DefaultFilterConfig::FLAG_ORDERED, '1');

        self::assertSame(
            [10 => 130, 11 => 110, 13 => 100, 14 => 120],
            $this->positions([], $flags),
            'size and color (swatches, by Magento position), then brand and material'
        );
    }

    public function testAnInstallWithSettingsGetsNoOrder(): void
    {
        $flags = $this->createMock(FlagManager::class);
        $flags->expects(self::never())->method('saveFlag');

        self::assertSame([14 => null], $this->positions([10 => [], 11 => [], 13 => []], $flags), 'only the new attribute, unordered');
    }

    /**
     * @param array<int, array<string, mixed>> $rows saved attribute rows
     * @param FlagManager $flags
     * @return array<int, int|null> attribute id => position override written
     */
    private function positions(array $rows, FlagManager $flags): array
    {
        $written = [];
        $storage = $this->createStub(FilterConfigStorage::class);
        $storage->method('attributeRows')->willReturn($rows);
        $storage->method('builtinRows')->willReturn([]);
        $storage->method('filterableAttributes')->willReturn(self::ATTRIBUTES);
        $storage->method('saveAttribute')->willReturnCallback(static function (int $id, array $row) use (&$written): void {
            $written[$id] = $row['position_override'] ?? null;
        });

        (new DefaultFilterConfig($storage, new Defaults(), $flags))->apply();

        return $written;
    }
}
