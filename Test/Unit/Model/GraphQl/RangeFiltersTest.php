<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\GraphQl;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\GraphQl\RangeFilters;
use Iranimij\OpenFilter\Model\Layer\RangeListItems;
use Iranimij\OpenFilter\Model\Search\NiceRanges;
use Iranimij\OpenFilter\Model\Search\RangeLists;
use Iranimij\OpenFilter\Model\Url\Range;
use Iranimij\OpenFilter\Model\Url\RangeList;
use PHPUnit\Framework\TestCase;

class RangeFiltersTest extends TestCase
{
    public function testRegistersRangeFiltersInIndexUnitsWithThePickedRange(): void
    {
        $lists = new RangeLists();
        $ranges = $this->ranges($lists);

        $ranges->register(1, ['price' => ['from' => '10', 'to' => '30'], 'width' => ['from' => '5']]);

        self::assertSame([
            'price' => ['selected' => [[10.0, 30.0]], 'ranges' => [[10.0, 30.0], [30.0, 50.0], [50.0, null]], 'interval' => 1.0],
        ], $lists->take(), 'only filters shown as ranges; the slider "width" is not registered');
    }

    public function testOptionsFromTheCountedRanges(): void
    {
        $lists = new RangeLists();
        $ranges = $this->ranges($lists);
        $ranges->register(1, []);
        $lists->take();
        $lists->collect([RangeLists::BUCKET_PREFIX . 'price' => ['buckets' => [['doc_count' => 2], ['doc_count' => 0], ['doc_count' => 4]]]]);
        $price = (new Defaults())->forBuiltin('price')->setDisplayMode(Filter::DISPLAY_RANGES)->setRanges('10-30,30-50,50-');

        self::assertSame(['10-30' => 2, '50-' => 4], array_column($ranges->options($price, [], null), 'count', 'value'));
    }

    public function testPicked(): void
    {
        $ranges = $this->ranges(new RangeLists());

        self::assertSame(['10-30'], $ranges->picked(['price' => ['from' => '10', 'to' => '30']], 'price'));
        self::assertSame(['-30'], $ranges->picked(['price' => ['to' => '30']], 'price'));
        self::assertSame([], $ranges->picked(['price' => ['from' => 'x']], 'price'));
        self::assertSame([], $ranges->picked(['price' => 'nonsense'], 'price'));
        self::assertSame([], $ranges->picked([], 'price'));
    }

    private function ranges(RangeLists $lists): RangeFilters
    {
        $defaults = new Defaults();
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('getActive')->willReturn([
            'price' => $defaults->forBuiltin('price')->setDisplayMode(Filter::DISPLAY_RANGES)->setRanges('10-30,30-50,50-'),
            'width' => $defaults->forAttribute('width', 99, 'decimal', null, 50),
        ]);
        $range = new Range();

        return new RangeFilters($provider, new RangeListItems($lists, new RangeList($range), new NiceRanges(), $range), $range);
    }
}
