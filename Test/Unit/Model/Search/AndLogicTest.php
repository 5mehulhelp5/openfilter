<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search;

use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Search\AndLogic;
use Iranimij\OpenFilter\Model\Search\FacetFilter;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class AndLogicTest extends TestCase
{
    public function testAnAllOfAttributeLeavesTheFacetsAndGetsOneClausePerValue(): void
    {
        $colour = new FacetFilter('color', 'color_bucket', [['terms' => ['color' => [49, 50]]]]);
        $size = new FacetFilter('size', 'size_bucket', [['terms' => ['size' => [7, 8]]]]);

        [$facets, $clauses] = $this->logic(['color' => 'and', 'size' => 'or'])->split([$colour, $size]);

        self::assertSame([$size], $facets);
        self::assertSame([['term' => ['color' => 49]], ['term' => ['color' => 50]]], $clauses);
    }

    public function testOneValueNeedsNoExtraClauseButStaysConjunctive(): void
    {
        $colour = new FacetFilter('color', 'color_bucket', [['term' => ['color' => 49]]]);

        self::assertSame([[], []], $this->logic(['color' => 'and'])->split([$colour]));
    }

    public function testWithoutAllOfAttributesNothingChanges(): void
    {
        $colour = new FacetFilter('color', 'color_bucket', [['terms' => ['color' => [49, 50]]]]);
        $price = new FacetFilter('price', 'price_bucket', [['range' => ['price_0_1' => ['gte' => 10]]]]);

        self::assertSame([[$colour, $price], []], $this->logic(['color' => 'or'])->split([$colour, $price]));
    }

    public function testABuiltinIsNeverAllOf(): void
    {
        $stock = new FacetFilter('of_builtin:stock', 'stock_bucket', [['term' => ['of_in_stock' => 1]]]);

        self::assertSame([[$stock], []], $this->logic(['stock' => 'and'])->split([$stock]));
    }

    /**
     * @param array<string, string> $logic filter code => logic
     */
    private function logic(array $logic): AndLogic
    {
        $defaults = new Defaults();
        $configs = [];
        foreach ($logic as $code => $value) {
            $config = in_array($code, ['stock', 'price'], true)
                ? $defaults->forBuiltin($code)
                : $defaults->forAttribute($code, 90, 'select', null, 10);
            $configs[$code] = $config->setLogic($value);
        }
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('getForStore')->willReturn($configs);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return new AndLogic($provider, $stores);
    }
}
