<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Layer;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Layer\Filter\BuiltinFactory;
use Iranimij\OpenFilter\Model\Layer\Filter\OpenFilterInterface;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Search\BuiltinFilters;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\FilterList;

/**
 * Adds the switched-on built-in filters to category and search filter lists, and orders every filter by
 * OpenFilter's positions (category first, toggles after the attributes unless moved).
 */
class FilterListPlugin
{
    /** @var array<int, AbstractFilter[]> built-in filter objects per layer */
    private array $builtins = [];

    /**
     * @param LayerContext $context
     * @param BuiltinFilters $builtinFilters
     * @param BuiltinFactory $builtinFactory
     */
    public function __construct(
        private readonly LayerContext $context,
        private readonly BuiltinFilters $builtinFilters,
        private readonly BuiltinFactory $builtinFactory
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
        $all = array_merge($filters, $this->builtins[$id]);
        $positions = [];
        foreach ($all as $index => $filter) {
            $positions[$index] = [$this->position($filter), $index];
        }
        uksort($all, static fn (int $a, int $b): int => $positions[$a] <=> $positions[$b]);

        return array_values($all);
    }

    /**
     * @param AbstractFilter $filter
     * @return int
     */
    private function position(AbstractFilter $filter): int
    {
        $key = $filter instanceof OpenFilterInterface ? $filter->getOpenFilterKey() : null;
        if ($key === null) {
            $key = $filter->hasData('attribute_model') ? null : Filter::BUILTIN_CATEGORY;
        }

        return $key === null ? PHP_INT_MAX - 1 : ($this->context->config($key)?->getPosition() ?? PHP_INT_MAX - 1);
    }
}
