<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Magento\Catalog\Model\Layer\FilterList;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Layer\Search as SearchLayer;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The quick filter bar (1.2): one-click links to values of the filters the merchant marked, above the products.
 * Built from the page's own filters (Magento's filter lists keep them per layer), so it costs no search request.
 */
class QuickBar implements ArgumentInterface
{
    /**
     * @param Config $config
     * @param LayerContext $context
     * @param Resolver $layerResolver
     * @param FilterList $categoryFilters core's categoryFilterList (etc/di.xml)
     * @param FilterList $searchFilters core's searchFilterList
     * @param FilterBlock $filterBlock
     */
    public function __construct(
        private readonly Config $config,
        private readonly LayerContext $context,
        private readonly Resolver $layerResolver,
        private readonly FilterList $categoryFilters,
        private readonly FilterList $searchFilters,
        private readonly FilterBlock $filterBlock
    ) {
    }

    /**
     * The bar's values: picked ones first, then by product count, at most the configured number.
     *
     * @return Data\QuickItem[]
     */
    public function items(): array
    {
        $size = $this->config->getQuickBarSize($this->context->storeId());
        if ($size === 0 || !$this->context->isActive()) {
            return [];
        }
        $layer = $this->layerResolver->get();
        $filters = ($layer instanceof SearchLayer ? $this->searchFilters : $this->categoryFilters)->getFilters($layer);
        $items = [];
        foreach ($this->filterBlock->groups($filters) as $group) {
            $key = $group->getKey();
            if ($key === null || !($this->context->config($key)?->isQuickBar() ?? false)) {
                continue;
            }
            foreach ($group->getOptions() as $option) {
                if ($option->getCount() > 0 || $option->isSelected()) {
                    $items[] = new Data\QuickItem($group->getLabel(), $option);
                }
            }
        }
        usort($items, static fn (Data\QuickItem $a, Data\QuickItem $b): int => [
            !$a->getOption()->isSelected(), -$a->getOption()->getCount(), $a->getOption()->getLabel(),
        ] <=> [
            !$b->getOption()->isSelected(), -$b->getOption()->getCount(), $b->getOption()->getLabel(),
        ]);

        return array_slice($items, 0, $size);
    }
}
