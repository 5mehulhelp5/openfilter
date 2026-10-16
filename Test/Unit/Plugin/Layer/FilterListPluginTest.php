<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Plugin\Layer;

use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\Model\Config\DependencyRule;
use Iranimij\OpenFilter\Model\Layer\Filter\BuiltinFactory;
use Iranimij\OpenFilter\Model\Layer\Filter\CategoryTree;
use Iranimij\OpenFilter\Model\Layer\Filter\CategoryTreeFactory;
use Iranimij\OpenFilter\Model\Config\CategoryRule;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Search\BuiltinFilters;
use Iranimij\OpenFilter\Plugin\Layer\FilterListPlugin;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\FilterList;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
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
        $plugin = new FilterListPlugin($context, $builtins, $this->createStub(BuiltinFactory::class), $this->createStub(CategoryTreeFactory::class), new CategoryRule(), new DependencyRule());

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
        $plugin = new FilterListPlugin($context, $builtins, $this->createStub(BuiltinFactory::class), $this->createStub(CategoryTreeFactory::class), new CategoryRule(), new DependencyRule());
        $price = $this->attributeFilter(77, 'price');
        $size = $this->attributeFilter(144, 'size');

        self::assertSame(
            [$size],
            $plugin->afterGetFilters($this->createStub(FilterList::class), [$price, $size], $this->createStub(Layer::class))
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTheCategoryTreeTakesTheDrillDownsPlaceInTreeMode(): void
    {
        $context = $this->createStub(LayerContext::class);
        $context->method('isActive')->willReturn(true);
        $context->method('isHidden')->willReturn(false);
        $context->method('config')->willReturn(new FilterConfig(['code' => 'category', 'builtin' => true, 'display_mode' => 'tree']));
        $builtins = $this->createStub(BuiltinFilters::class);
        $builtins->method('activeCodes')->willReturn([]);
        $tree = $this->getMockBuilder(CategoryTree::class)->disableOriginalConstructor()->onlyMethods(['getOpenFilterKey'])->getMock();
        $tree->method('getOpenFilterKey')->willReturn('category');
        $factory = $this->createMock(CategoryTreeFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($tree);
        $plugin = new FilterListPlugin($context, $builtins, $this->createStub(BuiltinFactory::class), $factory, new CategoryRule(), new DependencyRule());
        $layer = $this->createStub(Layer::class);
        $core = $this->createStub(AbstractFilter::class);
        $core->method('hasData')->willReturn(false);
        $size = $this->attributeFilter(144, 'size');

        $first = $plugin->afterGetFilters($this->createStub(FilterList::class), [$core, $size], $layer);
        $again = $plugin->afterGetFilters($this->createStub(FilterList::class), [$core, $size], $layer);

        self::assertContains($tree, $first);
        self::assertNotContains($core, $first);
        self::assertSame($first, $again, 'one tree filter per layer');
    }

    /**
     * @return array<string, array{string, int[], bool, bool}>
     */
    public static function rules(): array
    {
        return [
            'only, in the category' => ['only', [20, 21], true, true],
            'only, below the category' => ['only', [2, 20, 21, 25], true, true],
            'only, elsewhere' => ['only', [2, 11, 12], false, true],
            'except, in the category' => ['except', [2, 20, 21], false, true],
            'except, elsewhere' => ['except', [2, 11], true, true],
            'search page ignores rules' => ['only', [], true, false],
        ];
    }

    /**
     * @dataProvider rules
     * @param int[] $path
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rules')]
    #[AllowMockObjectsWithoutExpectations]
    public function testWhereItShows(string $mode, array $path, bool $shown, bool $categoryPage): void
    {
        $colour = new FilterConfig(['code' => 'color', 'category_mode' => $mode, 'category_ids' => [21]]);
        $context = $this->createStub(LayerContext::class);
        $context->method('isActive')->willReturn(true);
        $context->method('isHidden')->willReturn(false);
        $context->method('config')->willReturnCallback(static fn (string $key) => $key === 'color' ? $colour : null);
        $builtins = $this->createStub(BuiltinFilters::class);
        $builtins->method('activeCodes')->willReturn([]);
        $plugin = new FilterListPlugin($context, $builtins, $this->createStub(BuiltinFactory::class), $this->createStub(CategoryTreeFactory::class), new CategoryRule(), new DependencyRule());
        $filter = $this->getMockBuilder(\Iranimij\OpenFilter\Model\Layer\Filter\Attribute::class)->disableOriginalConstructor()
            ->onlyMethods(['getOpenFilterKey', 'hasData'])->getMock();
        $filter->method('getOpenFilterKey')->willReturn('color');
        $filter->method('hasData')->willReturn(true);
        $category = $this->createStub(\Magento\Catalog\Model\Category::class);
        $category->method('getPathIds')->willReturn($path);
        $layer = $categoryPage
            ? $this->createStub(Layer::class)
            : $this->createStub(\Magento\Catalog\Model\Layer\Search::class);
        $layer->method('getCurrentCategory')->willReturn($category);

        $result = $plugin->afterGetFilters($this->createStub(FilterList::class), [$filter], $layer);

        self::assertSame($shown ? [$filter] : [], $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAFilterThatWaitsShowsOnlyAfterItsParentsPick(): void
    {
        $configs = [
            'color' => new FilterConfig(['code' => 'color']),
            'size' => new FilterConfig(['code' => 'size', 'depends_on' => 'color', 'depends_values' => ['50']]),
        ];
        $shown = [];
        foreach ([[], ['color' => ['49']], ['color' => ['49', '50']]] as $picks) {
            $context = $this->createStub(LayerContext::class);
            $context->method('isActive')->willReturn(true);
            $context->method('isHidden')->willReturn(false);
            $context->method('config')->willReturnCallback(static fn (string $key) => $configs[$key] ?? null);
            $context->method('state')->willReturn(new FilterState($picks));
            $builtins = $this->createStub(BuiltinFilters::class);
            $builtins->method('activeCodes')->willReturn([]);
            $plugin = new FilterListPlugin($context, $builtins, $this->createStub(BuiltinFactory::class), $this->createStub(CategoryTreeFactory::class), new CategoryRule(), new DependencyRule());
            $filters = [];
            foreach (['color', 'size'] as $key) {
                $filter = $this->getMockBuilder(\Iranimij\OpenFilter\Model\Layer\Filter\Attribute::class)->disableOriginalConstructor()
                    ->onlyMethods(['getOpenFilterKey', 'hasData'])->getMock();
                $filter->method('getOpenFilterKey')->willReturn($key);
                $filter->method('hasData')->willReturn(true);
                $filters[$key] = $filter;
            }
            $result = $plugin->afterGetFilters(
                $this->createStub(FilterList::class),
                array_values($filters),
                $this->createStub(\Magento\Catalog\Model\Layer\Search::class)
            );
            $shown[] = array_map(static fn ($filter): string => (string) array_search($filter, $filters, true), $result);
        }

        self::assertSame([['color'], ['color'], ['color', 'size']], $shown, 'no pick, another value, the value it waits for');
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
