<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Doctor;

use Iranimij\OpenFilter\Model\Doctor\EngineProbe;
use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\Elasticsearch\SearchAdapter\SearchIndexNameResolver;
use PHPUnit\Framework\TestCase;

class EngineProbeTest extends TestCase
{
    public function testFieldsComeFromTheMappingOfTheStoreIndexOnce(): void
    {
        $client = new class {
            public int $calls = 0;

            /**
             * @param array<string, mixed> $params
             * @return array<string, mixed>
             */
            public function getMapping(array $params): array
            {
                $this->calls++;

                return ['magento2_product_1_v3' => ['mappings' => ['properties' => ['color' => [], 'of_rating' => []]]]];
            }
        };
        $probe = $this->probe($client);

        self::assertSame(['color', 'of_rating'], $probe->fields(1));
        self::assertSame(['color', 'of_rating'], $probe->fields(1));
        self::assertSame(1, $client->calls);
    }

    public function testCountAndDistinctAskForVisibleProductsOfTheCategory(): void
    {
        $client = new class {
            /** @var array<int, array<string, mixed>> */
            public array $queries = [];

            /**
             * @param array<string, mixed> $query
             * @return array<string, mixed>
             */
            public function query(array $query): array
            {
                $this->queries[] = $query;

                return [
                    'hits' => ['total' => ['value' => 7]],
                    'aggregations' => ['values' => ['buckets' => [['key' => 1], ['key' => 2]]]],
                ];
            }
        };
        $probe = $this->probe($client);

        self::assertSame(7, $probe->count(1, 12, ['exists' => ['field' => 'color']]));
        self::assertSame(2, $probe->distinct(1, 12, 'color'));
        $filter = $client->queries[0]['body']['query']['bool']['filter'];
        self::assertSame('magento2_product_1', $client->queries[0]['index']);
        self::assertSame(['term' => ['category_ids' => 12]], $filter[0]);
        self::assertSame(['terms' => ['visibility' => [2, 4]]], $filter[1]);
        self::assertSame(['exists' => ['field' => 'color']], $filter[2]);
        self::assertSame(['field' => 'color', 'size' => 2], $client->queries[1]['body']['aggregations']['values']['terms']);
    }

    public function testAnEngineThatCannotBeAskedGivesNull(): void
    {
        $failing = new class {
            /**
             * @param array<string, mixed> $params
             * @return array<string, mixed>
             */
            public function getMapping(array $params): array
            {
                throw new \RuntimeException('down');
            }

            /**
             * @param array<string, mixed> $query
             * @return array<string, mixed>
             */
            public function query(array $query): array
            {
                throw new \RuntimeException('down');
            }
        };
        $probe = $this->probe($failing);
        self::assertNull($probe->fields(1));
        self::assertNull($probe->count(1, 12, []));
        self::assertNull($probe->distinct(1, 12, 'color'));

        $other = $this->probe(new \stdClass());
        self::assertNull($other->fields(1), 'a client without getMapping');
        self::assertNull($other->count(1, 12, []), 'a client without query');
    }

    private function probe(object $client): EngineProbe
    {
        $manager = $this->createStub(ConnectionManager::class);
        $manager->method('getConnection')->willReturn($client);
        $names = $this->createStub(SearchIndexNameResolver::class);
        $names->method('getIndexName')->willReturnCallback(static fn ($store): string => 'magento2_product_' . $store);

        return new EngineProbe($manager, $names);
    }
}
