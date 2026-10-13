<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel;

use Iranimij\OpenFilter\Model\Layer\Filter\OpenFilterInterface;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Active filters as chips, and the "Clear all" link, for the storefront templates (any theme package).
 */
class State implements ArgumentInterface
{
    /**
     * @param LayerContext $context
     */
    public function __construct(
        private readonly LayerContext $context
    ) {
    }

    /**
     * @param Item[] $activeFilters the layer state's items (Navigation\State::getActiveFilters())
     * @return Data\Chip[]
     */
    public function chips(array $activeFilters): array
    {
        $chips = [];
        foreach ($activeFilters as $item) {
            $chips[] = new Data\Chip(
                trim(strip_tags((string) $item->getFilter()->getName())),
                trim(strip_tags((string) $item->getData('label'))),
                (string) $item->getRemoveUrl()
            );
        }

        return $chips;
    }

    /**
     * The current page without any filter value: OpenFilter's parameters (whatever their URL names in this store
     * view) and the active core filters (category drill-down) go, sorting and other parameters stay.
     *
     * @param Item[] $activeFilters
     * @return string
     */
    public function clearUrl(array $activeFilters): string
    {
        $state = $this->context->state();
        foreach (array_keys($state->getValues()) as $key) {
            $state = $state->without((string) $key);
        }
        $core = [];
        foreach ($activeFilters as $item) {
            $filter = $item->getFilter();
            if (!$filter instanceof OpenFilterInterface || $filter->getOpenFilterKey() === null) {
                $core[(string) $filter->getRequestVar()] = null;
            }
        }

        return $this->context->urlFor($state, $core);
    }
}
