<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Plugin\GraphQl;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\GraphQl\RangeFilters;
use Iranimij\OpenFilter\Model\GraphQl\RequestState;
use Iranimij\OpenFilter\Plugin\GraphQl\ProductsPlugin;
use Iranimij\OpenFilter\Plugin\GraphQl\SearchCriteriaPlugin;
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder;
use Magento\CatalogGraphQl\Model\Resolver\Products;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ProductsPluginTest extends TestCase
{
    public function testAFieldAskingForOpenFiltersAggregationsSearchesAsLayeredNavigation(): void
    {
        $state = new RequestState();
        $ranges = $this->createMock(RangeFilters::class);
        $ranges->expects(self::once())->method('register')->with(1, ['color' => ['eq' => '5']]);
        $criteria = new SearchCriteriaPlugin($state);
        $seen = null;
        $proceed = function () use ($state, $criteria, &$seen): array {
            $seen = [$state->isActive(), $criteria->beforeBuild($this->createStub(SearchCriteriaBuilder::class), [], false)[1]];
            $state->keep(['price_bucket' => ['min' => 1]]);

            return ['total_count' => 3];
        };

        $result = $this->plugin($state, $ranges, true)->aroundResolve(
            $this->createStub(Products::class),
            $proceed,
            $this->createStub(Field::class),
            new \stdClass(),
            $this->info(['items' => true, 'openfilter_aggregations' => true]),
            null,
            ['filter' => ['color' => ['eq' => '5']]]
        );

        self::assertSame([true, true], $seen, 'layered while searching; Magento asked for aggregations');
        self::assertSame(['filter' => ['color' => ['eq' => '5']], 'aggregations' => ['price_bucket' => ['min' => 1]]], $result[ProductsPlugin::RESULT_KEY]);
        self::assertFalse($state->isActive());
    }

    public function testOtherQueriesAndStoreViewsWithoutOpenFilterAreLeftAlone(): void
    {
        $state = new RequestState();
        $criteria = new SearchCriteriaPlugin($state);
        foreach ([[['items' => true], true], [['openfilter_aggregations' => true], false]] as [$selection, $enabled]) {
            $proceed = function () use ($state, $criteria): array {
                self::assertFalse($state->isActive());
                self::assertFalse($criteria->beforeBuild($this->createStub(SearchCriteriaBuilder::class), [], false)[1]);

                return ['total_count' => 1];
            };
            $result = $this->plugin($state, $this->createStub(RangeFilters::class), $enabled)->aroundResolve(
                $this->createStub(Products::class),
                $proceed,
                $this->createStub(Field::class),
                new \stdClass(),
                $this->info($selection),
                null,
                []
            );
            self::assertSame(['total_count' => 1], $result);
        }
    }

    private function plugin(RequestState $state, RangeFilters $ranges, bool $enabled): ProductsPlugin
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return new ProductsPlugin($config, $state, $ranges, $stores);
    }

    /**
     * @param array<string, bool> $selection
     */
    private function info(array $selection): ResolveInfo
    {
        $info = $this->createStub(ResolveInfo::class);
        $info->method('getFieldSelection')->willReturn($selection);

        return $info;
    }

}
