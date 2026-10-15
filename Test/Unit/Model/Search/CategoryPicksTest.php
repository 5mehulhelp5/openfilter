<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search;

use Iranimij\OpenFilter\Model\Search\CategoryPicks;
use Iranimij\OpenFilter\Model\Search\QueryClauses;
use Iranimij\OpenFilter\Model\Search\Strategy\SingleQuery;
use PHPUnit\Framework\TestCase;

class CategoryPicksTest extends TestCase
{
    public function testPicksNarrowNextToThePagesCategoryAndAreCountedWithoutThemselves(): void
    {
        $page = ['term' => ['category_ids' => 20]];
        $query = ['body' => [
            'query' => ['bool' => ['must' => [$page]]],
            'aggregations' => ['category_bucket' => ['terms' => ['field' => 'category_ids', 'include' => [21, 22, 23]]]],
        ]];
        $picks = new CategoryPicks();
        $picks->register(['21', 23, 21]);

        [$query, $filters] = $picks->apply($query, []);

        $anyOf = ['terms' => ['category_ids' => [21, 23]]];
        self::assertSame([$page, $anyOf], $query['body']['query']['bool']['must']);
        self::assertSame(['category_ids', 'category_bucket', [$anyOf]], [$filters[0]->getField(), $filters[0]->getBucketName(), $filters[0]->getClauses()]);

        $prepared = (new SingleQuery(new QueryClauses()))->prepareQuery($query, $filters);
        self::assertSame([$page], $prepared['body']['query']['bool']['must'], 'the page\'s category stays in the query');
        self::assertSame(['bool' => ['must' => [$anyOf]]], $prepared['body']['post_filter']);
        self::assertSame($query['body']['aggregations']['category_bucket'], $prepared['body']['aggregations']['category_bucket'], 'the tree counts without the picks');
    }

    public function testPicksAreTakenOnce(): void
    {
        $picks = new CategoryPicks();
        $picks->register([21]);
        $picks->apply(['body' => ['query' => ['bool' => ['must' => []]]]], []);

        $query = ['body' => ['query' => ['bool' => ['must' => []]]]];
        self::assertSame([$query, []], $picks->apply($query, []));
    }
}
