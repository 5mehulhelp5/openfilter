<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Insights;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Insights\Purge;
use Iranimij\OpenFilter\Model\Insights\Recorder;
use Iranimij\OpenFilter\Model\ResourceModel\InsightStorage;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * M6 S9 aggregation: one beacon becomes a view, one row per filter and per value, and the combination of a page
 * without products; the nightly purge keeps the retention's days, today included.
 */
#[AllowMockObjectsWithoutExpectations]
class RecorderTest extends TestCase
{
    public function testABeaconBecomesCountsOfItsDay(): void
    {
        $rows = (new Recorder($this->createStub(InsightStorage::class), $this->createStub(DateTime::class)))
            ->rows(['category' => 20, 'picks' => ['color' => ['12', '13'], 'price' => []], 'bucket' => 0], 1, '2026-10-25');

        $base = ['day' => '2026-10-25', 'store_id' => 1, 'category_id' => 20];
        self::assertSame([
            $base + ['kind' => 'view', 'filter_key' => '', 'value' => '', 'hits' => 1],
            $base + ['kind' => 'filter', 'filter_key' => 'color', 'value' => '', 'hits' => 1],
            $base + ['kind' => 'value', 'filter_key' => 'color', 'value' => '12', 'hits' => 1],
            $base + ['kind' => 'value', 'filter_key' => 'color', 'value' => '13', 'hits' => 1],
            $base + ['kind' => 'filter', 'filter_key' => 'price', 'value' => '', 'hits' => 1],
            $base + ['kind' => 'zero', 'filter_key' => '', 'value' => 'color=12,13;price', 'hits' => 1],
        ], $rows);
    }

    public function testOnlyPagesWithFiltersAndNoProductsAreZeroResults(): void
    {
        $recorder = new Recorder($this->createStub(InsightStorage::class), $this->createStub(DateTime::class));
        $kinds = static fn (array $rows): array => array_column($rows, 'kind');

        self::assertSame(['view'], $kinds($recorder->rows(['category' => 0, 'picks' => [], 'bucket' => 0], 1, '2026-10-25')));
        self::assertSame(['view', 'filter', 'value'], $kinds($recorder->rows(['category' => 0, 'picks' => ['size' => ['7']], 'bucket' => -1], 1, '2026-10-25')));
        $long = ['category' => 0, 'picks' => ['color' => array_map('strval', range(100000, 100060))], 'bucket' => 0];
        self::assertNotContains('zero', $kinds($recorder->rows($long, 1, '2026-10-25')), 'longer than the column: not kept');
    }

    public function testRecordAddsTheRowsOfTodayInUtc(): void
    {
        $storage = $this->createMock(InsightStorage::class);
        $storage->expects(self::once())->method('add')->with(self::callback(
            static fn (array $rows): bool => count($rows) === 1 && $rows[0]['day'] === '2026-10-25'
        ));
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->with('Y-m-d')->willReturn('2026-10-25');

        (new Recorder($storage, $dateTime))->record(['category' => 3, 'picks' => [], 'bucket' => 1], 1);
    }

    public function testThePurgeKeepsTheRetentionDaysTodayIncluded(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getInsightsRetentionDays')->willReturn(3);
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(1_000_000);
        $dateTime->expects(self::once())->method('gmtDate')->with('Y-m-d', 1_000_000 - 2 * 86400)->willReturn('2026-10-23');
        $storage = $this->createMock(InsightStorage::class);
        $storage->expects(self::once())->method('purge')->with('2026-10-23')->willReturn(4);

        self::assertSame(4, (new Purge($config, $storage, $dateTime))->execute());
    }
}
