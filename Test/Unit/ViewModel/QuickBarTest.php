<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\ViewModel;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\ViewModel\Data\FilterGroup;
use Iranimij\OpenFilter\ViewModel\Data\FilterOption;
use Iranimij\OpenFilter\ViewModel\Data\QuickItem;
use Iranimij\OpenFilter\ViewModel\FilterBlock;
use Iranimij\OpenFilter\ViewModel\QuickBar;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\FilterList;
use Magento\Catalog\Model\Layer\Resolver;
use PHPUnit\Framework\TestCase;

class QuickBarTest extends TestCase
{
    public function testPickedValuesFirstThenTheMostProductsOfTheMarkedFiltersOnly(): void
    {
        $items = $this->bar(5)->items();

        self::assertSame(
            ['Colour: Blue', 'Size: M', 'Colour: Black', 'Size: S', 'Colour: Red'],
            array_map(static fn (QuickItem $item): string => $item->getFilterLabel() . ': ' . $item->getOption()->getLabel(), $items),
            'Blue is picked; then by count, ties by name; material is not marked, Green has no products'
        );
    }

    public function testTheSizeLimitsTheBarAndZeroMeansNoBar(): void
    {
        self::assertCount(2, $this->bar(2)->items());
        self::assertSame([], $this->bar(0)->items());
    }

    private function bar(int $size): QuickBar
    {
        $config = $this->createStub(Config::class);
        $config->method('getQuickBarSize')->willReturn($size);
        $configs = [
            'color' => new FilterConfig(['code' => 'color', 'quick_bar' => true]),
            'size' => new FilterConfig(['code' => 'size', 'quick_bar' => true]),
            'material' => new FilterConfig(['code' => 'material']),
        ];
        $context = $this->createStub(LayerContext::class);
        $context->method('isActive')->willReturn(true);
        $context->method('storeId')->willReturn(1);
        $context->method('config')->willReturnCallback(static fn (string $key) => $configs[$key] ?? null);
        $resolver = $this->createStub(Resolver::class);
        $resolver->method('get')->willReturn($this->createStub(Layer::class));
        $list = $this->createStub(FilterList::class);
        $list->method('getFilters')->willReturn([]);
        $option = static fn (string $label, int $count, bool $selected = false): FilterOption
            => new FilterOption($label, strtolower($label), '/c?' . strtolower($label), $count, $selected);
        $block = $this->createStub(FilterBlock::class);
        $block->method('groups')->willReturn([
            new FilterGroup('color', 'color', 'Colour', 'swatch', true, true, 6, false, [
                $option('Black', 9), $option('Blue', 2, true), $option('Red', 5), $option('Green', 0),
            ]),
            new FilterGroup('size', 'size', 'Size', 'list', true, true, 6, false, [$option('S', 7), $option('M', 12)]),
            new FilterGroup('material', 'material', 'Material', 'list', true, true, 6, false, [$option('Cotton', 30)]),
        ]);

        return new QuickBar($config, $context, $resolver, $list, $list, $block);
    }
}
