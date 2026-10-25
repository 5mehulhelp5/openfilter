<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Insights;

use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Insights\Report;
use Iranimij\OpenFilter\Model\Layer\CategoryTree;
use Iranimij\OpenFilter\Model\ResourceModel\InsightStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * M6 S9 report: filters by use with their share of pages, labelled values, unused filters, zero-result combinations,
 * filters worth indexing and the suggested order (active filters by use, hidden ones last).
 */
#[AllowMockObjectsWithoutExpectations]
class ReportTest extends TestCase
{
    public function testTheReportReadsTheCounts(): void
    {
        $report = $this->report()->build(1, 30);

        self::assertSame(100, $report['views']);
        self::assertTrue($report['enough']);
        self::assertSame([
            ['key' => 'color', 'label' => 'Color', 'hits' => 40, 'share' => 0.4],
            ['key' => 'price', 'label' => 'Price', 'hits' => 30, 'share' => 0.3],
            ['key' => 'size', 'label' => 'Size', 'hits' => 15, 'share' => 0.15],
            ['key' => 'new', 'label' => 'New', 'hits' => 0, 'share' => 0.0],
            ['key' => 'material', 'label' => 'material', 'hits' => 0, 'share' => 0.0],
        ], $report['filters']);
        self::assertSame([
            ['filter' => 'Color', 'label' => 'Rot', 'hits' => 30],
            ['filter' => 'Size', 'label' => 'M', 'hits' => 15],
            ['filter' => 'Color', 'label' => 'Blue', 'hits' => 10],
            ['filter' => 'New', 'label' => 'Yes', 'hits' => 2],
        ], $report['values'], 'store labels first, unknown filters left out');
        self::assertSame(['New', 'material'], $report['unused']);
        self::assertSame([['label' => 'Color: Blue + Price', 'hits' => 3]], $report['zero']);
        self::assertSame(['color'], array_column($report['indexable'], 'key'), 'size is used too little, price is no option filter');
        self::assertSame(['color', 'price', 'size', 'new', 'material', 'rating'], $report['order']);
    }

    public function testTheSuggestedOrderAloneAndAQuietStore(): void
    {
        $report = $this->report(views: 10);

        self::assertSame(['color', 'price', 'size', 'new', 'material', 'rating'], $report->suggestedOrder(1, 7));
        self::assertFalse($report->build(1, 7)['enough']);
    }

    private function report(int $views = 100): Report
    {
        $storage = $this->createMock(InsightStorage::class);
        $storage->method('sums')->willReturnCallback(static fn (string $kind): array => match ($kind) {
            'view' => [['filter_key' => '', 'value' => '', 'hits' => $views]],
            'filter' => [
                ['filter_key' => 'color', 'value' => '', 'hits' => 40],
                ['filter_key' => 'price', 'value' => '', 'hits' => 30],
                ['filter_key' => 'size', 'value' => '', 'hits' => 15],
            ],
            'value' => [
                ['filter_key' => 'color', 'value' => '12', 'hits' => 30],
                ['filter_key' => 'size', 'value' => '7', 'hits' => 15],
                ['filter_key' => 'gone', 'value' => '1', 'hits' => 12],
                ['filter_key' => 'color', 'value' => '13', 'hits' => 10],
                ['filter_key' => 'new', 'value' => '1', 'hits' => 2],
            ],
            'zero' => [
                ['filter_key' => '', 'value' => 'color=13;price', 'hits' => 3],
                ['filter_key' => '', 'value' => 'gone=1', 'hits' => 2],
            ],
            default => [],
        });
        $provider = $this->createMock(FilterConfigProvider::class);
        $provider->method('getForStore')->willReturn([
            'color' => new FilterConfig(['code' => 'color', 'attribute_id' => 93, 'label' => 'Color']),
            'size' => new FilterConfig(['code' => 'size', 'attribute_id' => 144, 'label' => 'Size']),
            'price' => new FilterConfig(['code' => 'price', 'builtin' => true]),
            'rating' => new FilterConfig(['code' => 'rating', 'builtin' => true, 'active' => false]),
            'new' => new FilterConfig(['code' => 'new', 'builtin' => true]),
            'material' => new FilterConfig(['code' => 'material', 'attribute_id' => 150]),
        ]);
        $slugs = $this->createMock(SlugStorage::class);
        $slugs->method('optionLabels')->willReturnCallback(static fn (int $attributeId): array => match ($attributeId) {
            93 => ['admin' => [12 => 'Red', 13 => 'Blue'], 'stores' => [1 => [12 => 'Rot']]],
            default => ['admin' => [7 => 'M'], 'stores' => []],
        });
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(1_000_000);
        $dateTime->method('gmtDate')->willReturn('2026-09-26');

        return new Report($storage, $provider, new Defaults(), $slugs, $this->createStub(CategoryTree::class), $dateTime);
    }
}
