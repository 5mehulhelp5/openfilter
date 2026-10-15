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
     * Option ids picked per attribute code (select and multiple-select filters), for theme scripts that read the
     * filter from the URL and expect option ids, such as Hyvä's product card swatches.
     *
     * @param Item[] $activeFilters
     * @return array<string, string[]>
     */
    public function picks(array $activeFilters): array
    {
        $picks = [];
        foreach ($activeFilters as $item) {
            $filter = $item->getFilter();
            $attribute = $filter->hasData('attribute_model') ? $filter->getAttributeModel() : null;
            $value = $item->getData('value');
            if ($attribute === null || !in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)
                || !is_scalar($value) || !ctype_digit((string) $value)) {
                continue;
            }
            $picks[(string) $attribute->getAttributeCode()][] = (string) $value;
        }

        return $picks;
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
