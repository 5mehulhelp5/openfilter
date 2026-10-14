<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search\Strategy;

use Iranimij\OpenFilter\Model\Search\FacetFilter;
use Iranimij\OpenFilter\Model\Search\QueryClauses;
use Iranimij\OpenFilter\Model\Search\Strategy\PerAttribute;
use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use PHPUnit\Framework\TestCase;

class PerAttributeTest extends TestCase
{
    public function testOneCountRequestPerActiveAttributeWithoutItsOwnFilter(): void
    {
        $color = ['terms' => ['color' => [1, 2]]];
        $size = ['term' => ['size' => 7]];
        $query = ['body' => [
            'from' => 24, 'size' => 12, 'sort' => [['price' => 'asc']],
            'query' => ['bool' => ['must' => [$color, $size]]],
            'aggregations' => ['color_bucket' => ['terms' => ['field' => 'color']], 'size_bucket' => ['terms' => ['field' => 'size']]],
        ]];
        $client = new class {
            /** @var array<int, array<string, mixed>> */
            public array $sent = [];

            /**
             * @param array<string, mixed> $query
             * @return array<string, mixed>
             */
            public function query(array $query): array
            {
                $this->sent[] = $query;
                $bucket = (string) array_key_first($query['body']['aggregations']);

                return ['aggregations' => [$bucket => ['buckets' => [['key' => $bucket, 'doc_count' => 5]]]]];
            }
        };
        $manager = $this->createStub(ConnectionManager::class);
        $manager->method('getConnection')->willReturn($client);
        $strategy = new PerAttribute(new QueryClauses(), $manager);
        $filters = [new FacetFilter('color', 'color_bucket', [$color]), new FacetFilter('size', 'size_bucket', [$size])];

        self::assertSame($query, $strategy->prepareQuery($query, $filters), 'the main request is core\'s');
        $response = $strategy->prepareResponse($query, ['aggregations' => ['color_bucket' => [], 'size_bucket' => []]], $filters);
        $sent = $client->sent;

        self::assertCount(2, $sent);
        self::assertSame([$size], $sent[0]['body']['query']['bool']['must']);
        self::assertSame(0, $sent[0]['body']['size']);
        self::assertSame(0, $sent[0]['body']['from']);
        self::assertArrayNotHasKey('sort', $sent[0]['body']);
        self::assertSame(['color_bucket'], array_keys($sent[0]['body']['aggregations']));
        self::assertSame([$color], $sent[1]['body']['query']['bool']['must']);
        self::assertSame('color_bucket', $response['aggregations']['color_bucket']['buckets'][0]['key']);
        self::assertSame('size_bucket', $response['aggregations']['size_bucket']['buckets'][0]['key']);
        self::assertSame('per_attribute', $strategy->getCode());
    }

    public function testSkipsAttributesWhoseClauseOrAggregationIsMissing(): void
    {
        $manager = $this->createStub(ConnectionManager::class);
        $strategy = new PerAttribute(new QueryClauses(), $manager);
        $query = ['body' => ['query' => ['bool' => ['must' => [['term' => ['size' => 7]]]]], 'aggregations' => []]];
        $response = ['aggregations' => ['size_bucket' => ['buckets' => []]]];

        self::assertSame($response, $strategy->prepareResponse($query, $response, [
            new FacetFilter('color', 'color_bucket', [['term' => ['color' => 1]]]),
            new FacetFilter('size', 'size_bucket', [['term' => ['size' => 7]]]),
        ]));
    }
}
