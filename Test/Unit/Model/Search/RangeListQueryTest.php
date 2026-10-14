<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search;

use Iranimij\OpenFilter\Model\Search\FacetFilter;
use Iranimij\OpenFilter\Model\Search\QueryClauses;
use Iranimij\OpenFilter\Model\Search\RangeListQuery;
use Iranimij\OpenFilter\Model\Search\RangeLists;
use Iranimij\OpenFilter\Model\Search\Strategy\SingleQuery;
use PHPUnit\Framework\TestCase;

class RangeListQueryTest extends TestCase
{
    private const HULL = ['range' => ['price_0_1' => ['gte' => 0.0, 'lte' => 100.0]]];

    /**
     * @return array<string, mixed>
     */
    private function query(): array
    {
        return ['body' => [
            'query' => ['bool' => ['must' => [['term' => ['color' => 49]], self::HULL]]],
            'aggregations' => [
                'price_bucket' => ['extended_stats' => ['field' => 'price_0_1']],
                'color_bucket' => ['terms' => ['field' => 'color']],
            ],
        ]];
    }

    public function testPickedRangesReplaceCoresRangeAndAreCountedInTheSameRequest(): void
    {
        $lists = new RangeLists();
        $lists->register('price', [[0.0, 50.0], [80.0, 100.0]], [[0.0, 50.0], [50.0, 80.0], [80.0, null]], 1.0);
        $price = new FacetFilter('price', 'price_bucket', [self::HULL]);
        $colour = new FacetFilter('color', 'color_bucket', [['term' => ['color' => 49]]]);

        [$query, $filters] = (new RangeListQuery($lists))->apply($this->query(), [$colour, $price]);

        $anyOf = ['bool' => ['should' => [
            ['range' => ['price_0_1' => ['gte' => 0.0, 'lt' => 50.0]]],
            ['range' => ['price_0_1' => ['gte' => 80.0, 'lt' => 100.0]]],
        ], 'minimum_should_match' => 1]];
        self::assertSame([['term' => ['color' => 49]], $anyOf], $query['body']['query']['bool']['must']);
        self::assertSame(['range' => ['field' => 'price_0_1', 'ranges' => [
            ['from' => 0.0, 'to' => 50.0], ['from' => 50.0, 'to' => 80.0], ['from' => 80.0],
        ]]], $query['body']['aggregations']['of_ranges_price']);
        self::assertSame([$anyOf], $filters[1]->getClauses());
        self::assertSame(['price', 'of_ranges_price', []], [$filters[2]->getField(), $filters[2]->getBucketName(), $filters[2]->getClauses()]);
        self::assertSame([], $lists->take(), 'a registration is used once');

        $prepared = (new SingleQuery(new QueryClauses()))->prepareQuery($query, $filters);
        self::assertSame(
            ['filter' => ['bool' => ['must' => [['term' => ['color' => 49]]]]], 'aggs' => ['of_inner' => $query['body']['aggregations']['of_ranges_price']]],
            $prepared['body']['aggregations']['of_ranges_price'],
            'the ranges are counted without their own filter'
        );
    }

    public function testAutomaticRangesCountAHistogramInDisplayUnitsWithoutAPick(): void
    {
        $lists = new RangeLists();
        $lists->register('price', [], null, 0.8);

        [$query, $filters] = (new RangeListQuery($lists))->apply($this->query(), []);

        self::assertSame(['histogram' => [
            'field' => 'price_0_1', 'interval' => 0.8, 'offset' => 0.8 - 0.0008, 'min_doc_count' => 1,
        ]], $query['body']['aggregations']['of_ranges_price']);
        self::assertSame($this->query()['body']['query'], $query['body']['query']);
        self::assertSame([], $filters);
    }

    public function testAFieldWithoutCoreAggregationIsLeftAlone(): void
    {
        $lists = new RangeLists();
        $lists->register('width', [[1.0, 2.0]], null, 1.0);

        self::assertSame([$this->query(), []], (new RangeListQuery($lists))->apply($this->query(), []));
    }

    public function testCountsComeBackByField(): void
    {
        $lists = new RangeLists();
        $lists->collect(['of_ranges_price' => ['buckets' => [['key' => 0, 'doc_count' => 3]]], 'color_bucket' => ['buckets' => []]]);

        self::assertSame([['key' => 0, 'doc_count' => 3]], $lists->counts('price'));
        self::assertNull($lists->counts('color'));
    }
}
