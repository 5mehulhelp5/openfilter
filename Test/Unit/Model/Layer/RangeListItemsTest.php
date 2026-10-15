<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Layer;

use Iranimij\OpenFilter\Model\Layer\RangeListItems;
use Iranimij\OpenFilter\Model\Search\NiceRanges;
use Iranimij\OpenFilter\Model\Search\RangeLists;
use Iranimij\OpenFilter\Model\Url\Range;
use Iranimij\OpenFilter\Model\Url\RangeList;
use PHPUnit\Framework\TestCase;

class RangeListItemsTest extends TestCase
{
    private RangeLists $lists;

    protected function setUp(): void
    {
        $this->lists = new RangeLists();
    }

    public function testRegistersPickedAndListedRangesInIndexValues(): void
    {
        $toIndex = static fn (float $display): float => $display / 1.25;

        $this->items()->register('price', ['0-50', '100-'], '0-50,50-100,100-', $toIndex);

        self::assertSame(['price' => [
            'selected' => [[0.0, 40.0], [80.0, null]],
            'ranges' => [[0.0, 40.0], [40.0, 80.0], [80.0, null]],
            'interval' => 0.8,
        ]], $this->lists->take());
    }

    public function testMerchantRangesKeepTheirOrderAndHideEmptyUnlessPicked(): void
    {
        $this->lists->collect(['of_ranges_price' => ['buckets' => [
            ['key' => '0.0-50.0', 'doc_count' => 4], ['key' => '50.0-100.0', 'doc_count' => 0], ['key' => '100.0-*', 'doc_count' => 2],
        ]]]);

        $items = $this->items()->items('price', ['50-100'], '0-50,50-100,100-', null, static fn (float $v): float => $v);

        self::assertSame([
            ['from' => 0.0, 'to' => 50.0, 'value' => '0-50', 'count' => 4, 'selected' => false],
            ['from' => 50.0, 'to' => 100.0, 'value' => '50-100', 'count' => 0, 'selected' => true],
            ['from' => 100.0, 'to' => null, 'value' => '100-', 'count' => 2, 'selected' => false],
        ], $items);
    }

    public function testAutomaticRangesSumTheHistogramInDisplayValues(): void
    {
        // Index values are 0.8 of display values (currency); buckets are one display unit wide, a thousandth down.
        $this->lists->collect(['of_ranges_price' => ['buckets' => [
            ['key' => 9.6 - 0.0008, 'doc_count' => 2],
            ['key' => 15.2 - 0.0008, 'doc_count' => 1],
            ['key' => 16.0 - 0.0008, 'doc_count' => 5], // display 20.00: the first value of 20-40
            ['key' => 76.8 - 0.0008, 'doc_count' => 1],
        ]]]);

        $items = $this->items()->items('price', [], null, ['min' => 12.0, 'max' => 96.0], static fn (float $index): float => $index / 0.8);

        self::assertSame([['0-20', 3], ['20-40', 5], ['80-', 1]], array_map(static fn (array $i): array => [$i['value'], $i['count']], $items));
    }

    public function testNoStatsNoAutomaticRanges(): void
    {
        self::assertSame([], $this->items()->items('price', [], null, null, static fn (float $v): float => $v));
    }

    private function items(): RangeListItems
    {
        return new RangeListItems($this->lists, new RangeList(new Range()), new NiceRanges(), new Range());
    }
}
