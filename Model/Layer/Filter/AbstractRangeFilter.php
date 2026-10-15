<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer\Filter;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Layer\RangeListItems;
use Iranimij\OpenFilter\Model\Url\Range;
use Iranimij\OpenFilter\Plugin\Search\DynamicBucketPlugin;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as FulltextCollection;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Slider filter (price, decimal attributes): one "from-to" value, min and max from the same search request with the
 * filter's own range excluded. Until the slider template (M3) the list shows the active range only. Falls back to
 * Magento's own filter when OpenFilter is off.
 */
abstract class AbstractRangeFilter extends AbstractFilter implements OpenFilterInterface
{
    private ?AbstractFilter $core = null;

    /**
     * @param ItemFactory $filterItemFactory
     * @param StoreManagerInterface $storeManager
     * @param Layer $layer
     * @param DataBuilder $itemDataBuilder
     * @param LayerContext $context
     * @param Range $range
     * @param RangeListItems $rangeListItems the "ranges" display type (1.1)
     * @param array<string, mixed> $data
     */
    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        protected readonly LayerContext $context,
        protected readonly Range $range,
        protected readonly RangeListItems $rangeListItems,
        array $data = []
    ) {
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $data);
    }

    /**
     * Search field of the filter (request field name).
     *
     * @return string
     */
    abstract protected function field(): string;

    /**
     * Magento's own filter, used while OpenFilter is off.
     *
     * @return AbstractFilter
     */
    abstract protected function createCoreFilter(): AbstractFilter;

    /**
     * Index value for a displayed bound.
     *
     * @param float $display
     * @return float
     */
    abstract protected function toIndex(float $display): float;

    /**
     * Displayed range of indexed min and max.
     *
     * @param float $min
     * @param float $max
     * @return array{float, float}
     */
    abstract protected function toDisplayRange(float $min, float $max): array;

    /**
     * Label of a displayed range.
     *
     * @param float|null $from
     * @param float|null $to
     * @return string
     */
    abstract protected function label(?float $from, ?float $to): string;

    /**
     * @inheritDoc
     */
    public function isMultiselect(): bool
    {
        return $this->isRangeList() && ($this->openFilterConfig()?->isMultiselect() ?? false);
    }

    /**
     * Shown as a list of ranges ("ranges" display type, 1.1) instead of a slider.
     *
     * @return bool
     */
    public function isRangeList(): bool
    {
        return $this->openFilterConfig()?->getDisplayMode() === Filter::DISPLAY_RANGES;
    }

    /**
     * Applies the selected range (converted to index units) and adds it to the active filters.
     *
     * @param RequestInterface $request
     * @return $this
     */
    public function apply(RequestInterface $request)
    {
        if (!$this->context->isActive()) {
            $this->core()->apply($request);

            return $this;
        }
        if ($this->isRangeList()) {
            return $this->applyRangeList();
        }
        $key = $this->getOpenFilterKey();
        $value = $key === null ? null : ($this->context->state()->get($key)[0] ?? null);
        $range = $value === null ? null : $this->range->parse($value);
        if ($range === null) {
            return $this;
        }
        [$from, $to] = $range;
        $condition = [];
        if ($from !== null) {
            $condition['from'] = $this->toIndex($from);
        }
        if ($to !== null) {
            $condition['to'] = $this->toIndex($to);
        }
        $this->getLayer()->getProductCollection()->addFieldToFilter($this->field(), $condition);
        $this->getLayer()->getState()->addFilter($this->_createItem($this->label($from, $to), $value));

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getItems()
    {
        return $this->context->isActive() ? parent::getItems() : $this->core()->getItems();
    }

    /**
     * Displayed min and max of the products in the current selection without this filter's own range.
     *
     * @return array{min: float, max: float, count: int}|null
     */
    public function getStats(): ?array
    {
        if (!$this->context->isActive()) {
            return null;
        }
        /** @var FulltextCollection $collection */
        $collection = $this->getLayer()->getProductCollection();
        $stats = $collection->getFacetedData($this->field())[DynamicBucketPlugin::STATS] ?? null;
        if (!is_array($stats) || !isset($stats['min'], $stats['max'])) {
            return null;
        }
        [$min, $max] = $this->toDisplayRange((float) $stats['min'], (float) $stats['max']);

        return ['min' => $min, 'max' => $max, 'count' => (int) ($stats['count'] ?? 0)];
    }

    /**
     * The active range as the only option (selected, so its link removes it).
     *
     * @return array<int, array{label: string, value: string, count: int}>
     */
    protected function _getItemsData()
    {
        if ($this->isRangeList()) {
            return $this->rangeListItemsData();
        }
        $key = $this->getOpenFilterKey();
        $value = $key === null ? null : ($this->context->state()->get($key)[0] ?? null);
        $range = $value === null ? null : $this->range->parse($value);
        if ($range === null) {
            return [];
        }

        return [[
            'label' => $this->label($range[0], $range[1]),
            'value' => (string) $value,
            'count' => (int) ($this->getStats()['count'] ?? 0),
        ]];
    }

    /**
     * @return AbstractFilter
     */
    /**
     * Picked ranges narrow core's request to their outer bounds; the search mapper puts the exact "any of" clause
     * in its place and counts the ranges in the same request.
     *
     * @return $this
     */
    private function applyRangeList(): self
    {
        $key = (string) $this->getOpenFilterKey();
        $values = $this->context->state()->get($key);
        $picked = $this->rangeListItems->picked($values);
        $this->rangeListItems->register(
            $this->field(),
            $values,
            $this->openFilterConfig()?->getRanges(),
            fn (float $display): float => $this->toIndex($display)
        );
        if ($picked === []) {
            return $this;
        }
        $froms = array_column($picked, 0);
        $tos = array_column($picked, 1);
        $condition = [];
        if (!in_array(null, $froms, true)) {
            $condition['from'] = $this->toIndex((float) min($froms));
        }
        if (!in_array(null, $tos, true)) {
            $condition['to'] = $this->toIndex((float) max($tos));
        }
        if ($condition !== []) {
            $this->getLayer()->getProductCollection()->addFieldToFilter($this->field(), $condition);
        }
        foreach ($values as $value) {
            $range = $this->range->parse($value);
            if ($range !== null) {
                $this->getLayer()->getState()->addFilter($this->_createItem($this->rangeLabel($range[0], $range[1]), $value));
            }
        }

        return $this;
    }

    /**
     * @return array<int, array{label: string, value: string, count: int}>
     */
    private function rangeListItemsData(): array
    {
        $key = (string) $this->getOpenFilterKey();
        $stats = $this->getStats();
        $items = $this->rangeListItems->items(
            $this->field(),
            $this->context->state()->get($key),
            $this->openFilterConfig()?->getRanges(),
            $stats === null ? null : ['min' => (float) $stats['min'], 'max' => (float) $stats['max']],
            fn (float $index): float => $this->toDisplay($index)
        );

        return array_map(fn (array $item): array => [
            'label' => $this->rangeLabel($item['from'], $item['to']),
            'value' => $item['value'],
            'count' => $item['count'],
        ], $items);
    }

    /**
     * Label of a [from, to) range of the list ("€0.00 - €49.99" for prices).
     *
     * @param float|null $from
     * @param float|null $to
     * @return string
     */
    protected function rangeLabel(?float $from, ?float $to): string
    {
        return $this->label($from, $to);
    }

    /**
     * Index value as shoppers see it (currency and tax for prices).
     *
     * @param float $index
     * @return float
     */
    protected function toDisplay(float $index): float
    {
        return $this->toDisplayRange($index, $index)[0];
    }

    /**
     * @return Filter|null
     */
    private function openFilterConfig(): ?Filter
    {
        $key = $this->getOpenFilterKey();

        return $key === null ? null : $this->context->config($key);
    }

    private function core(): AbstractFilter
    {
        return $this->core ??= $this->createCoreFilter();
    }

    /**
     * Number without trailing zeros.
     *
     * @param float $value
     * @return string
     */
    protected function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * The filter's label: the merchant's label for this store view (Catalog > OpenFilter > Filters), else core's.
     *
     * @return string
     */
    public function getName()
    {
        $key = $this->getOpenFilterKey();
        $label = $key === null ? null : $this->context->config($key)?->getLabel();

        return $label !== null && $label !== '' ? $label : (string) parent::getName();
    }
}
