<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer\Filter;

use Iranimij\OpenFilter\Model\Layer\LayerContext;
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
     * @param array<string, mixed> $data
     */
    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        protected readonly LayerContext $context,
        protected readonly Range $range,
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
        return false;
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
}
