<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\ViewModel;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Layer\Filter\Attribute;
use Iranimij\OpenFilter\Model\Layer\Filter\Item;
use Iranimij\OpenFilter\Model\Layer\Filter\Price;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Seo\NofollowPolicy;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\ViewModel\Data\Slider as SliderData;
use Iranimij\OpenFilter\ViewModel\FilterBlock;
use Iranimij\OpenFilter\ViewModel\Slider;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\Item as CoreItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FilterBlockTest extends TestCase
{
    /** @var array<string, FilterConfig> */
    private array $configs = [];

    /** @var array<string, SliderData|null> */
    private array $sliders = [];

    public function testGroupsCarryLabelDisplayAndOptionsWithLinksCountsAndSelection(): void
    {
        $this->configs['color'] = new FilterConfig(['code' => 'color', 'display_mode' => FilterConfigInterface::DISPLAY_SWATCH]);
        $color = $this->openFilter('color', 'Colour', [
            $this->item('Black', '49', 12, '/c?color=black', true),
            $this->item('Blue', '50', 3, '/c?color=black,blue', false),
        ]);

        $groups = $this->block()->groups([$color]);

        self::assertCount(1, $groups);
        $group = $groups[0];
        self::assertSame('color', $group->getCode());
        self::assertSame('color', $group->getKey());
        self::assertSame('Colour', $group->getLabel());
        self::assertSame(FilterConfigInterface::DISPLAY_SWATCH, $group->getDisplay());
        self::assertTrue($group->isMultiselect());
        self::assertSame(1, $group->getSelectedCount());
        self::assertSame(['Black', 'Blue'], array_map(static fn ($o) => $o->getLabel(), $group->getOptions()));
        self::assertSame('/c?color=black,blue', $group->getOptions()[1]->getUrl());
        self::assertSame(3, $group->getOptions()[1]->getCount());
        self::assertSame('50', $group->getOptions()[1]->getValue());
        self::assertTrue($group->getOptions()[0]->isSelected());
        self::assertFalse($group->getOptions()[1]->isSelected());
    }

    public function testFiltersWithoutItemsAreLeftOut(): void
    {
        $this->configs['color'] = new FilterConfig(['code' => 'color']);

        self::assertSame([], $this->block()->groups([$this->openFilter('color', 'Colour', [])]));
    }

    public function testCoreFiltersRenderAsListOrCategoryDrillDown(): void
    {
        $category = $this->coreFilter('cat', 'Category', [$this->coreItem('Jackets', '12', 5, '/c?cat=12')]);
        $other = $this->coreFilter('foo', 'Foo', [$this->coreItem('Bar', '1', 2, '/c?foo=1')]);

        $groups = $this->block()->groups([$category, $other]);

        self::assertSame(['cat', 'foo'], array_map(static fn ($g) => $g->getCode(), $groups));
        self::assertSame([FilterBlock::DISPLAY_CATEGORY, FilterConfigInterface::DISPLAY_LIST], array_map(
            static fn ($g) => $g->getDisplay(),
            $groups
        ));
        self::assertNull($groups[0]->getKey());
        self::assertFalse($groups[0]->isMultiselect());
        self::assertFalse($groups[0]->getOptions()[0]->isSelected());
    }

    public function testFirstGroupsAreOpenByDefaultActiveGroupsAlwaysAndConfiguredGroupsAsSet(): void
    {
        $this->configs['a'] = new FilterConfig(['code' => 'a']);
        $this->configs['b'] = new FilterConfig(['code' => 'b', 'expanded' => false]);
        $this->configs['c'] = new FilterConfig(['code' => 'c']);
        $this->configs['d'] = new FilterConfig(['code' => 'd', 'expanded' => true]);
        $this->configs['e'] = new FilterConfig(['code' => 'e', 'expanded' => false]);
        $filters = [
            $this->openFilter('a', 'A', [$this->item('x', '1', 1, '/a', false)]),
            $this->openFilter('b', 'B', [$this->item('x', '1', 1, '/b', false)]),
            $this->openFilter('c', 'C', [$this->item('x', '1', 1, '/c', false)]),
            $this->openFilter('d', 'D', [$this->item('x', '1', 1, '/d', false)]),
            $this->openFilter('e', 'E', [$this->item('x', '1', 1, '/e', true)]),
        ];

        $groups = $this->block(2)->groups($filters);

        self::assertSame(
            ['a' => true, 'b' => false, 'c' => false, 'd' => true, 'e' => true],
            array_combine(
                array_map(static fn ($g) => $g->getCode(), $groups),
                array_map(static fn ($g) => $g->isExpanded(), $groups)
            )
        );
    }

    public function testVisibleOptionsAndSearchBoxFollowTheFilterSettings(): void
    {
        $this->configs['size'] = new FilterConfig(['code' => 'size', 'visible_options' => 2, 'search_threshold' => 3]);
        $items = [];
        foreach (['XS', 'S', 'M', 'L'] as $i => $label) {
            $items[] = $this->item($label, (string) $i, 1, '/s', false);
        }

        $group = $this->block()->groups([$this->openFilter('size', 'Size', $items)])[0];

        self::assertSame(2, $group->getVisibleOptions());
        self::assertTrue($group->isSearchable(), '4 options are more than the threshold 3');
        self::assertSame(['XS', 'S'], array_map(static fn ($o) => $o->getLabel(), $group->getLeadingOptions()));
        self::assertSame(['M', 'L'], array_map(static fn ($o) => $o->getLabel(), $group->getMoreOptions()));
    }

    public function testSelectedOptionsBeyondTheVisibleCountStayInTheLeadingList(): void
    {
        $this->configs['size'] = new FilterConfig(['code' => 'size', 'visible_options' => 1]);
        $group = $this->block()->groups([$this->openFilter('size', 'Size', [
            $this->item('S', '1', 1, '/s', false),
            $this->item('M', '2', 1, '/m', true),
            $this->item('L', '3', 1, '/l', false),
        ])])[0];

        self::assertSame(['S', 'M'], array_map(static fn ($o) => $o->getLabel(), $group->getLeadingOptions()));
        self::assertSame(['L'], array_map(static fn ($o) => $o->getLabel(), $group->getMoreOptions()));
    }

    public function testRangeFiltersShowASliderWhileTheyHaveBoundsEvenWithoutItems(): void
    {
        $this->configs['price'] = new FilterConfig(['code' => 'price', 'display_mode' => FilterConfigInterface::DISPLAY_SLIDER]);
        $this->configs['width'] = new FilterConfig(['code' => 'width', 'display_mode' => FilterConfigInterface::DISPLAY_SLIDER]);
        $slider = new SliderData('price', 10.0, 90.0, 1.0, null, null, 'EUR', 'en-US', '/c', [], '/c?price=x', '/c');
        $this->sliders = ['price' => $slider, 'width' => null];

        $groups = $this->block()->groups([$this->rangeFilter('price', 'Price'), $this->rangeFilter('width', 'Width')]);

        self::assertCount(1, $groups, 'a range filter without bounds and without items is left out');
        self::assertSame(FilterConfigInterface::DISPLAY_SLIDER, $groups[0]->getDisplay());
        self::assertSame($slider, $groups[0]->getSlider());
        self::assertSame([], $groups[0]->getOptions());
    }

    public function testARangeListShowsItsRangesAsOptionsInsteadOfASlider(): void
    {
        $this->configs['price'] = new FilterConfig(['code' => 'price', 'display_mode' => FilterConfigInterface::DISPLAY_RANGES]);
        $this->sliders = ['price' => new SliderData('price', 10.0, 90.0, 1.0, null, null, 'EUR', 'en-US', '/c', [], '/c?price=x', '/c')];

        $groups = $this->block()->groups([$this->rangeFilter('price', 'Price', true, [
            $this->item('€0.00 - €49.99', '0-50', 7, '/c?price=0-50', false),
            $this->item('€50.00 and above', '50-', 3, '/c?price=50-', true),
        ])]);

        self::assertNull($groups[0]->getSlider());
        self::assertSame(FilterConfigInterface::DISPLAY_RANGES, $groups[0]->getDisplay());
        self::assertSame(['0-50', '50-'], array_map(static fn ($o) => $o->getValue(), $groups[0]->getOptions()));
        self::assertTrue($groups[0]->isMultiselect());
    }

    public function testTotalSelectedCountsEveryGroup(): void
    {
        $this->configs['a'] = new FilterConfig(['code' => 'a']);
        $this->configs['b'] = new FilterConfig(['code' => 'b']);
        $block = $this->block();
        $groups = $block->groups([
            $this->openFilter('a', 'A', [$this->item('x', '1', 1, '/a', true), $this->item('y', '2', 1, '/a', true)]),
            $this->openFilter('b', 'B', [$this->item('x', '1', 1, '/b', true)]),
        ]);

        self::assertSame(3, $block->selectedCount($groups));
    }

    public function testOptionLinksGetNofollowFromTheThresholdButRemoveLinksDoNot(): void
    {
        $this->configs['a'] = new FilterConfig(['code' => 'a']);
        $filter = $this->openFilter('a', 'A', [
            $this->item('x', '1', 1, '/a', true),
            $this->item('y', '2', 1, '/a', true),
            $this->item('z', '3', 4, '/a?a=x,y,z', false),
        ]);

        $below = $this->block(3, new FilterState(['a' => ['1']]))->groups([$filter])[0]->getOptions();
        $at = $this->block(3, new FilterState(['a' => ['1', '2']]))->groups([$filter])[0]->getOptions();

        self::assertSame(['', '', ''], array_map(static fn ($o): string => $o->getRel(), $below));
        self::assertSame(['', '', 'nofollow'], array_map(static fn ($o): string => $o->getRel(), $at));
    }

    private function block(int $expandedGroups = 3, ?FilterState $state = null): FilterBlock
    {
        $context = $this->createStub(LayerContext::class);
        $context->method('config')->willReturnCallback(fn (string $key) => $this->configs[$key] ?? null);
        $context->method('state')->willReturn($state ?? new FilterState());
        $config = $this->createStub(Config::class);
        $config->method('getExpandedGroups')->willReturn($expandedGroups);
        $config->method('getNofollowThreshold')->willReturn(2);
        $slider = $this->createStub(Slider::class);
        $slider->method('forFilter')->willReturnCallback(fn ($filter) => $this->sliders[$filter->getOpenFilterKey()] ?? null);

        return new FilterBlock($context, $config, $slider, new NofollowPolicy($config));
    }

    /**
     * @param Item[] $items
     */
    private function openFilter(string $key, string $name, array $items): Attribute
    {
        $filter = $this->getMockBuilder(Attribute::class)->disableOriginalConstructor()
            ->onlyMethods(['getOpenFilterKey', 'isMultiselect', 'getName', 'getItems', 'getItemsCount', 'getRequestVar'])
            ->getMock();
        $filter->method('getOpenFilterKey')->willReturn($key);
        $filter->method('isMultiselect')->willReturn(true);
        $filter->method('getName')->willReturn($name);
        $filter->method('getItems')->willReturn($items);
        $filter->method('getItemsCount')->willReturn(count($items));
        $filter->method('getRequestVar')->willReturn($key);

        return $filter;
    }

    /**
     * @param CoreItem[] $items
     */
    private function rangeFilter(string $key, string $name, bool $rangeList = false, array $items = []): Price
    {
        $filter = $this->getMockBuilder(Price::class)->disableOriginalConstructor()
            ->onlyMethods(['getOpenFilterKey', 'getName', 'getItems', 'getItemsCount', 'getRequestVar', 'isRangeList', 'isMultiselect'])
            ->getMock();
        $filter->method('getOpenFilterKey')->willReturn($key);
        $filter->method('getName')->willReturn($name);
        $filter->method('getItems')->willReturn($items);
        $filter->method('getItemsCount')->willReturn(count($items));
        $filter->method('isRangeList')->willReturn($rangeList);
        $filter->method('isMultiselect')->willReturn($rangeList);
        $filter->method('getRequestVar')->willReturn($key);

        return $filter;
    }

    /**
     * @param CoreItem[] $items
     */
    private function coreFilter(string $requestVar, string $name, array $items): AbstractFilter&Stub
    {
        $filter = $this->createStub(AbstractFilter::class);
        $filter->method('getName')->willReturn($name);
        $filter->method('getItems')->willReturn($items);
        $filter->method('getItemsCount')->willReturn(count($items));
        $filter->method('getRequestVar')->willReturn($requestVar);

        return $filter;
    }

    private function item(string $label, string $value, int $count, string $url, bool $selected): Item
    {
        $item = $this->getMockBuilder(Item::class)->disableOriginalConstructor()
            ->onlyMethods(['getUrl', 'isSelected'])->getMock();
        $item->setData(['label' => $label, 'value' => $value, 'count' => $count]);
        $item->method('getUrl')->willReturn($url);
        $item->method('isSelected')->willReturn($selected);

        return $item;
    }

    private function coreItem(string $label, string $value, int $count, string $url): CoreItem
    {
        $item = $this->getMockBuilder(CoreItem::class)->disableOriginalConstructor()->onlyMethods(['getUrl'])->getMock();
        $item->setData(['label' => $label, 'value' => $value, 'count' => $count]);
        $item->method('getUrl')->willReturn($url);

        return $item;
    }
}
