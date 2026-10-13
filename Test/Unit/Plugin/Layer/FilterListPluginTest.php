<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Plugin\Layer;

use Iranimij\OpenFilter\Model\Layer\Filter\BuiltinFactory;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Search\BuiltinFilters;
use Iranimij\OpenFilter\Plugin\Layer\FilterListPlugin;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\FilterList;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use PHPUnit\Framework\TestCase;

class FilterListPluginTest extends TestCase
{
    public function testFiltersTheMerchantHidLeaveTheListAndTheOthersKeepTheirOrder(): void
    {
        $context = $this->createStub(LayerContext::class);
        $context->method('isActive')->willReturn(true);
        $context->method('isHidden')->willReturnCallback(
            static fn (?int $id, ?string $builtin): bool => $id === 93 || $builtin === 'category'
        );
        $context->method('config')->willReturn(null);
        $builtins = $this->createStub(BuiltinFilters::class);
        $builtins->method('activeCodes')->willReturn([]);
        $plugin = new FilterListPlugin($context, $builtins, $this->createStub(BuiltinFactory::class));

        $color = $this->attributeFilter(93, 'color');
        $size = $this->attributeFilter(144, 'size');
        $price = $this->attributeFilter(77, 'price');
        $category = $this->createStub(AbstractFilter::class);
        $category->method('hasData')->willReturn(false);

        $result = $plugin->afterGetFilters(
            $this->createStub(FilterList::class),
            [$category, $color, $price, $size],
            $this->createStub(Layer::class)
        );

        self::assertSame([$price, $size], $result);
    }

    public function testHiddenPriceIsDroppedByItsBuiltinCode(): void
    {
        $context = $this->createStub(LayerContext::class);
        $context->method('isActive')->willReturn(true);
        $context->method('isHidden')->willReturnCallback(static fn (?int $id, ?string $builtin): bool => $builtin === 'price');
        $builtins = $this->createStub(BuiltinFilters::class);
        $builtins->method('activeCodes')->willReturn([]);
        $plugin = new FilterListPlugin($context, $builtins, $this->createStub(BuiltinFactory::class));
        $price = $this->attributeFilter(77, 'price');
        $size = $this->attributeFilter(144, 'size');

        self::assertSame(
            [$size],
            $plugin->afterGetFilters($this->createStub(FilterList::class), [$price, $size], $this->createStub(Layer::class))
        );
    }

    private function attributeFilter(int $id, string $code): AbstractFilter
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getAttributeId')->willReturn($id);
        $attribute->method('getAttributeCode')->willReturn($code);
        $filter = $this->createStub(AbstractFilter::class);
        $filter->method('hasData')->willReturnCallback(static fn (string $key): bool => $key === 'attribute_model');
        $filter->method('getData')->willReturnCallback(
            static fn (string $key = '') => $key === 'attribute_model' ? $attribute : null
        );

        return $filter;
    }
}
