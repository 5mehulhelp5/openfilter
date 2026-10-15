<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Resolver;

use Iranimij\OpenFilter\Model\GraphQl\AggregationsBuilder;
use Iranimij\OpenFilter\Model\Resolver\Aggregations;
use Iranimij\OpenFilter\Plugin\GraphQl\ProductsPlugin;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResult;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class AggregationsTest extends TestCase
{
    public function testBuildsFromTheSearchResultAndWhatThePluginKept(): void
    {
        $aggregation = $this->createStub(AggregationInterface::class);
        $search = $this->createStub(SearchResult::class);
        $search->method('getSearchAggregation')->willReturn($aggregation);
        $builder = $this->createMock(AggregationsBuilder::class);
        $builder->expects(self::once())->method('build')
            ->with($aggregation, ['price_bucket' => []], ['color' => ['eq' => '5']], 1)
            ->willReturn([['code' => 'color']]);

        self::assertSame([['code' => 'color']], $this->resolve($builder, [
            'search_result' => $search,
            'total_count' => 2,
            ProductsPlugin::RESULT_KEY => ['filter' => ['color' => ['eq' => '5']], 'aggregations' => ['price_bucket' => []]],
        ]));
    }

    public function testEmptyWhenNothingWasFoundOrOpenFilterWasOff(): void
    {
        $builder = $this->createMock(AggregationsBuilder::class);
        $builder->expects(self::never())->method('build');
        $search = $this->createStub(SearchResult::class);

        self::assertSame([], $this->resolve($builder, ['search_result' => $search, 'total_count' => 2]));
        self::assertSame([], $this->resolve($builder, [
            'search_result' => $search, 'total_count' => 0, ProductsPlugin::RESULT_KEY => ['filter' => [], 'aggregations' => []],
        ]));
    }

    /**
     * @param array<string, mixed> $value
     * @return mixed
     */
    private function resolve(AggregationsBuilder $builder, array $value)
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return (new Aggregations($builder, $stores))->resolve($this->createStub(Field::class), new \stdClass(), $this->createStub(ResolveInfo::class), $value);
    }
}
