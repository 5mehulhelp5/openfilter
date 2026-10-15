<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigList;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SortOrder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilterConfigListTest extends TestCase
{
    /** @var FilterConfig[] */
    private array $configs;

    protected function setUp(): void
    {
        $defaults = new Defaults();
        $this->configs = [
            $defaults->forBuiltin('category'),
            $defaults->forAttribute('color', 93, 'select', 'visual', 10)->with(['label' => 'Color']),
            $defaults->forAttribute('size', 144, 'select', 'text', 20)->with(['label' => 'Size']),
            $defaults->forAttribute('material', 140, 'multiselect', null, 30)
                ->with(['label' => 'Material', 'active' => false]),
            $defaults->forBuiltin('rating'),
            $defaults->forBuiltin('stock'),
        ];
        $this->configs[] = $defaults->forAttribute('new', 150, 'boolean', null, 40)->with(['label' => 'New?']);
        $this->configs[] = $defaults->forBuiltin('new');
    }

    /**
     * @return array<string, array{list<array{string, mixed, string}>, list<string>}>
     */
    public static function filters(): array
    {
        return [
            'eq on a boolean' => [[['active', '0', 'eq']], ['material', 'rating', 'stock', 'new']],
            'eq without a condition' => [[['display_mode', 'swatch', '']], ['color', 'size']],
            'neq' => [[['builtin', '1', 'neq']], ['color', 'size', 'material', 'new']],
            'key tells an attribute from the built-in of the same name' => [[['key', 'attribute:new', 'eq']], ['new']],
            'like is case-insensitive' => [[['label', '%OL%', 'like']], ['color']],
            'like without wildcards is exact' => [[['code', 'size', 'like']], ['size']],
            'in' => [[['code', ['stock', 'color', 'brand'], 'in']], ['color', 'stock']],
            'in from a comma list' => [[['code', 'stock,color', 'in']], ['color', 'stock']],
            'nin' => [[['code', ['category', 'rating', 'stock', 'new'], 'nin']], ['color', 'size', 'material']],
            'range' => [[['position', '10', 'gteq'], ['position', '20', 'lteq']], ['color', 'size']],
            'gt and lt' => [[['visible_options', '5', 'gt'], ['visible_options', '7', 'lt']], ['material', 'rating', 'stock', 'category', 'new', 'new']],
            'null' => [[['label', null, 'null']], ['category', 'rating', 'stock', 'new']],
            'notnull' => [[['label', null, 'notnull']], ['color', 'size', 'material', 'new']],
            'unknown field matches nothing' => [[['brand', 'x', 'eq']], []],
        ];
    }

    /**
     * Each filter is its own group (AND).
     *
     * @param list<array{string, mixed, string}> $filters
     * @param list<string> $expected
     * @dataProvider filters
     */
    #[DataProvider('filters')]
    public function testFilters(array $filters, array $expected): void
    {
        $groups = array_map(
            static fn (array $f): FilterGroup => new FilterGroup(['filters' => [
                new Filter(['field' => $f[0], 'value' => $f[1], 'condition_type' => $f[2]]),
            ]]),
            $filters
        );

        [$items] = (new FilterConfigList())->apply($this->configs, (new SearchCriteria())->setFilterGroups($groups));

        self::assertEqualsCanonicalizing($expected, $this->codes($items));
    }

    public function testFiltersInOneGroupAreAlternatives(): void
    {
        $group = new FilterGroup(['filters' => [
            new Filter(['field' => 'code', 'value' => 'size', 'condition_type' => 'eq']),
            new Filter(['field' => 'code', 'value' => 'rating', 'condition_type' => 'eq']),
        ]]);

        [$items, $total] = (new FilterConfigList())->apply($this->configs, (new SearchCriteria())->setFilterGroups([$group]));

        self::assertSame(['size', 'rating'], $this->codes($items));
        self::assertSame(2, $total);
    }

    public function testSortsBySeveralFieldsAndKeepsTheGivenOrderOtherwise(): void
    {
        $criteria = (new SearchCriteria())->setSortOrders([
            new SortOrder(['field' => 'builtin', 'direction' => 'DESC']),
            new SortOrder(['field' => 'label', 'direction' => 'ASC']),
        ]);

        [$items] = (new FilterConfigList())->apply($this->configs, $criteria);

        self::assertSame(['category', 'rating', 'stock', 'new', 'color', 'material', 'new', 'size'], $this->codes($items));
        self::assertSame(
            $this->codes($this->configs),
            $this->codes((new FilterConfigList())->apply($this->configs, new SearchCriteria())[0])
        );
    }

    public function testPagesAfterCountingAndTreatsNoPageSizeAsAll(): void
    {
        $list = new FilterConfigList();

        [$items, $total] = $list->apply($this->configs, (new SearchCriteria())->setPageSize(3)->setCurrentPage(2));
        self::assertSame(['material', 'rating', 'stock'], $this->codes($items));
        self::assertSame(8, $total);

        [$items] = $list->apply($this->configs, (new SearchCriteria())->setPageSize(3)->setCurrentPage(9));
        self::assertSame(['new', 'new'], $this->codes($items), 'a page past the end shows the last page');

        self::assertCount(8, $list->apply($this->configs, (new SearchCriteria())->setPageSize(0))[0]);
    }

    /**
     * @param FilterConfig[] $items
     * @return list<string>
     */
    private function codes(array $items): array
    {
        return array_values(array_map(static fn (FilterConfig $c): string => $c->getCode(), $items));
    }
}
