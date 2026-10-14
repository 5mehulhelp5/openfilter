<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search\Strategy;

use Iranimij\OpenFilter\Model\Search\FacetFilter;
use Iranimij\OpenFilter\Model\Search\QueryClauses;
use Iranimij\OpenFilter\Model\Search\Strategy\SingleQuery;
use PHPUnit\Framework\TestCase;

class SingleQueryTest extends TestCase
{
    private const COLOR = ['terms' => ['color' => [1, 2]]];
    private const SIZE = ['term' => ['size' => 7]];
    private const CATEGORY = ['term' => ['category_ids' => 3]];

    public function testFacetFiltersMoveToPostFilterAndAggregationsIgnoreTheirOwnField(): void
    {
        $query = $this->query();
        $filters = [new FacetFilter('color', 'color_bucket', [self::COLOR]), new FacetFilter('size', 'size_bucket', [self::SIZE])];

        $prepared = (new SingleQuery(new QueryClauses()))->prepareQuery($query, $filters);

        self::assertSame([self::CATEGORY], $prepared['body']['query']['bool']['must'], 'category stays in the query');
        self::assertSame(['bool' => ['must' => [self::COLOR, self::SIZE]]], $prepared['body']['post_filter']);
        self::assertSame([self::SIZE], $prepared['body']['aggregations']['color_bucket']['filter']['bool']['must']);
        self::assertSame([self::COLOR], $prepared['body']['aggregations']['size_bucket']['filter']['bool']['must']);
        self::assertSame(
            [self::COLOR, self::SIZE],
            $prepared['body']['aggregations']['material_bucket']['filter']['bool']['must'],
            'an attribute without its own filter counts under all active filters'
        );
        self::assertSame(
            $query['body']['aggregations']['color_bucket'],
            $prepared['body']['aggregations']['color_bucket']['aggs'][SingleQuery::INNER]
        );
    }

    public function testASingleActiveAttributeLeavesItsOwnAggregationUnwrapped(): void
    {
        $prepared = (new SingleQuery(new QueryClauses()))
            ->prepareQuery($this->query(), [new FacetFilter('color', 'color_bucket', [self::COLOR])]);

        self::assertSame($this->query()['body']['aggregations']['color_bucket'], $prepared['body']['aggregations']['color_bucket']);
    }

    public function testQueryIsUntouchedWithoutFiltersOrWhenAClauseIsNotFound(): void
    {
        $strategy = new SingleQuery(new QueryClauses());

        self::assertSame($this->query(), $strategy->prepareQuery($this->query(), []));
        self::assertSame(
            $this->query(),
            $strategy->prepareQuery($this->query(), [new FacetFilter('color', 'color_bucket', [['term' => ['color' => 9]]])])
        );
    }

    public function testResponseIsUnwrapped(): void
    {
        $inner = ['buckets' => [['key' => 1, 'doc_count' => 4]]];
        $response = ['aggregations' => [
            'color_bucket' => ['doc_count' => 9, SingleQuery::INNER => $inner],
            'size_bucket' => $inner,
        ]];

        $result = (new SingleQuery(new QueryClauses()))->prepareResponse([], $response, []);

        self::assertSame(['color_bucket' => $inner, 'size_bucket' => $inner], $result['aggregations']);
        self::assertSame('single_query', (new SingleQuery(new QueryClauses()))->getCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function query(): array
    {
        return ['body' => [
            'query' => ['bool' => ['must' => [self::CATEGORY, self::COLOR, self::SIZE]]],
            'aggregations' => [
                'color_bucket' => ['terms' => ['field' => 'color', 'size' => 500]],
                'size_bucket' => ['terms' => ['field' => 'size', 'size' => 500]],
                'material_bucket' => ['terms' => ['field' => 'material', 'size' => 500]],
            ],
        ]];
    }
}
