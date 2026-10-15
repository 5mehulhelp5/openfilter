<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Layer;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\CategoryRule;
use Iranimij\OpenFilter\Model\Layer\Filter\BuiltinFactory;
use Iranimij\OpenFilter\Model\Layer\Filter\CategoryTree;
use Iranimij\OpenFilter\Model\Layer\Filter\CategoryTreeFactory;
use Iranimij\OpenFilter\Model\Layer\Filter\OpenFilterInterface;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Search\BuiltinFilters;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\FilterList;
use Magento\Catalog\Model\Layer\Search as SearchLayer;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

/**
 * Adds the switched-on built-in filters to category and search filter lists, leaves out the filters the merchant
 * hid, and orders every filter by OpenFilter's positions (category first, toggles after the attributes unless moved).
 */
class FilterListPlugin
{
    /** @var array<int, AbstractFilter[]> built-in filter objects per layer */
    private array $builtins = [];

    /** @var array<int, CategoryTree> category tree filter per layer */
    private array $trees = [];

    /**
     * @param LayerContext $context
     * @param BuiltinFilters $builtinFilters
     * @param BuiltinFactory $builtinFactory
     * @param CategoryTreeFactory $treeFactory the category tree (1.1) in place of core's drill-down
     * @param CategoryRule $categoryRule "Where it shows" (1.1)
     */
    public function __construct(
        private readonly LayerContext $context,
        private readonly BuiltinFilters $builtinFilters,
        private readonly BuiltinFactory $builtinFactory,
        private readonly CategoryTreeFactory $treeFactory,
        private readonly CategoryRule $categoryRule
    ) {
    }

    /**
     * @param FilterList $subject
     * @param AbstractFilter[] $filters
     * @param Layer $layer
     * @return AbstractFilter[]
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetFilters(FilterList $subject, $filters, Layer $layer)
    {
        if (!$this->context->isActive() || !is_array($filters)) {
            return $filters;
        }
        $id = spl_object_id($layer);
        if (!isset($this->builtins[$id])) {
            $this->builtins[$id] = [];
            foreach ($this->builtinFilters->activeCodes() as $code) {
                $this->builtins[$id][] = $this->builtinFactory->create(['layer' => $layer, 'data' => ['code' => $code]]);
            }
        }
        if ($this->context->config(Filter::BUILTIN_CATEGORY)?->getDisplayMode() === Filter::DISPLAY_TREE) {
            $this->trees[$id] ??= $this->treeFactory->create(['layer' => $layer]);
            $filters = array_map(
                fn (AbstractFilter $filter): AbstractFilter => $filter->hasData('attribute_model') ? $filter : $this->trees[$id],
                $filters
            );
        }
        $category = $layer instanceof SearchLayer ? null : $layer->getCurrentCategory();
        $path = $category === null ? null : array_map('intval', (array) $category->getPathIds());
        $all = array_filter(
            array_merge(
                array_filter($filters, fn (AbstractFilter $filter): bool => !$this->isHidden($filter)),
                $this->builtins[$id]
            ),
            fn (AbstractFilter $filter): bool => $path === null || $this->showsIn($filter, $path)
        );
        $positions = [];
        foreach ($all as $index => $filter) {
            $positions[$index] = [$this->position($filter), $index];
        }
        uksort($all, static fn (int $a, int $b): int => $positions[$a] <=> $positions[$b]);

        return array_values($all);
    }

    /**
     * Attribute, price and category filters the merchant hid (built-ins come from activeCodes() already).
     *
     * @param AbstractFilter $filter
     * @return bool
     */
    private function isHidden(AbstractFilter $filter): bool
    {
        if (!$filter->hasData('attribute_model')) {
            return $this->context->isHidden(null, Filter::BUILTIN_CATEGORY);
        }
        $attribute = $filter->getData('attribute_model');
        if (!$attribute instanceof AbstractAttribute) {
            return false;
        }
        if ($attribute->getAttributeCode() === Filter::BUILTIN_PRICE) {
            return $this->context->isHidden(null, Filter::BUILTIN_PRICE);
        }

        return $this->context->isHidden((int) $attribute->getAttributeId());
    }

    /**
     * The merchant's "Where it shows" (1.1).
     *
     * @param AbstractFilter $filter
     * @param int[] $path ids of the page's category and the categories above it
     * @return bool
     */
    private function showsIn(AbstractFilter $filter, array $path): bool
    {
        $key = $this->key($filter);
        $config = $key === null ? null : $this->context->config($key);

        return $config === null || $this->categoryRule->showsIn($config, $path);
    }

    /**
     * @param AbstractFilter $filter
     * @return string|null
     */
    private function key(AbstractFilter $filter): ?string
    {
        if ($filter instanceof OpenFilterInterface) {
            return $filter->getOpenFilterKey();
        }

        return $filter->hasData('attribute_model') ? null : Filter::BUILTIN_CATEGORY;
    }

    /**
     * @param AbstractFilter $filter
     * @return int
     */
    private function position(AbstractFilter $filter): int
    {
        $key = $this->key($filter);

        return $key === null ? PHP_INT_MAX - 1 : ($this->context->config($key)?->getPosition() ?? PHP_INT_MAX - 1);
    }
}
