<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer\Filter;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Search\BuiltinClauses;
use Iranimij\OpenFilter\Model\Search\BuiltinCounts;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Built-in filter (rating "N stars and up", in stock, on sale, new). The search request applies and counts it
 * (BuiltinFilters in the mapper); this model lists the options and the active value.
 */
class Builtin extends AbstractFilter implements OpenFilterInterface
{
    /**
     * @param ItemFactory $filterItemFactory
     * @param StoreManagerInterface $storeManager
     * @param Layer $layer
     * @param DataBuilder $itemDataBuilder
     * @param LayerContext $context
     * @param BuiltinCounts $counts
     * @param Defaults $defaults
     * @param array<string, mixed> $data needs "code"
     */
    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        private readonly LayerContext $context,
        private readonly BuiltinCounts $counts,
        private readonly Defaults $defaults,
        array $data = []
    ) {
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $data);
        $this->_requestVar = $this->code();
    }

    /**
     * @inheritDoc
     */
    public function getOpenFilterKey(): ?string
    {
        return $this->code();
    }

    /**
     * @inheritDoc
     */
    public function isMultiselect(): bool
    {
        return false;
    }

    /**
     * Store label, or the translated default ("In stock").
     *
     * @return string
     */
    public function getName()
    {
        return $this->context->config($this->code())?->getLabel()
            ?? (string) __($this->defaults->builtinLabel($this->code()));
    }

    /**
     * Adds the active value to the active filters (the search request applies the filter itself).
     *
     * @param RequestInterface $request
     * @return $this
     */
    public function apply(RequestInterface $request)
    {
        $value = $this->context->state()->get($this->code())[0] ?? null;
        if ($value !== null) {
            $this->getLayer()->getState()->addFilter($this->_createItem($this->label($value), $value));
        }

        return $this;
    }

    /**
     * Options with counts; options without products are hidden unless selected.
     *
     * @return array<int, array{label: string, value: string, count: int}>
     */
    protected function _getItemsData()
    {
        $this->getLayer()->getProductCollection()->getSize();
        $counts = $this->counts->get($this->code());
        $selected = $this->context->state()->get($this->code());
        $values = $this->code() === Filter::BUILTIN_RATING
            ? array_map('strval', BuiltinClauses::STARS)
            : ['1'];
        $items = [];
        foreach ($values as $value) {
            $count = is_array($counts) ? (int) ($counts[(int) $value] ?? 0) : (int) $counts;
            if ($count === 0 && !in_array($value, $selected, true)) {
                continue;
            }
            $items[] = ['label' => $this->label($value), 'value' => $value, 'count' => $count];
        }

        return $items;
    }

    /**
     * @param string $value
     * @return string
     */
    private function label(string $value): string
    {
        if ($this->code() !== Filter::BUILTIN_RATING) {
            return $this->getName();
        }

        return $value === '1' ? (string) __('%1 star & up', $value) : (string) __('%1 stars & up', $value);
    }

    /**
     * @return string
     */
    private function code(): string
    {
        return (string) $this->getData('code');
    }
}
