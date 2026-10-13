<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer\Filter;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\CatalogSearch\Model\Layer\Filter\Attribute as CoreAttribute;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as FulltextCollection;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Filter\StripTags;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Attribute filter with several values per attribute (OR) and options that stay listed when selected, with
 * disjunctive counts from the search engine. Falls back to core behaviour when OpenFilter is off.
 */
class Attribute extends CoreAttribute implements OpenFilterInterface
{
    /** core is_filterable: 1 = options with results only, 2 = all options */
    private const FILTERABLE_WITH_RESULTS = 1;

    private ?string $key = null;

    private bool $resolved = false;

    /**
     * @param ItemFactory $filterItemFactory
     * @param StoreManagerInterface $storeManager
     * @param Layer $layer
     * @param DataBuilder $itemDataBuilder
     * @param StripTags $stripTags
     * @param LayerContext $context
     * @param array<string, mixed> $data
     */
    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        private readonly StripTags $stripTags,
        private readonly LayerContext $context,
        array $data = []
    ) {
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $stripTags, $data);
        $key = $this->getOpenFilterKey();
        if ($key !== null) {
            $this->setRequestVar($this->context->parameterFor($key) ?? $this->getRequestVar());
        }
    }

    /**
     * @inheritDoc
     */
    public function getOpenFilterKey(): ?string
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $attribute = $this->hasData('attribute_model') ? $this->getAttributeModel() : null;
            $this->key = $attribute !== null && $this->context->isActive()
                ? $this->context->keyForAttribute((int) $attribute->getAttributeId())
                : null;
        }

        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function isMultiselect(): bool
    {
        $key = $this->getOpenFilterKey();

        return $key !== null && ($this->context->config($key)?->isMultiselect() ?? false);
    }

    /**
     * Applies every selected option (OR within the attribute) and adds one active-filter entry per value.
     *
     * @param RequestInterface $request
     * @return $this
     */
    public function apply(RequestInterface $request)
    {
        $key = $this->getOpenFilterKey();
        if ($key === null) {
            return parent::apply($request);
        }
        $values = $this->context->state()->get($key);
        if ($values === []) {
            return $this;
        }
        $attribute = $this->getAttributeModel();
        $ids = array_map('intval', $values);
        $this->getLayer()->getProductCollection()->addFieldToFilter(
            $attribute->getAttributeCode(),
            count($ids) === 1 ? $ids[0] : $ids
        );
        foreach ($values as $value) {
            $label = $this->getOptionText((int) $value);
            $label = is_array($label) ? implode(', ', $label) : (string) $label;
            $this->getLayer()->getState()->addFilter($this->_createItem($label, $value));
        }

        return $this;
    }

    /**
     * Options with their disjunctive counts; options without results are hidden unless selected.
     *
     * @return array<int, array{label: string, value: string, count: int}>
     */
    protected function _getItemsData()
    {
        $key = $this->getOpenFilterKey();
        if ($key === null) {
            return parent::_getItemsData();
        }
        $config = $this->context->config($key);
        $attribute = $this->getAttributeModel();
        /** @var FulltextCollection $collection */
        $collection = $this->getLayer()->getProductCollection();
        $faceted = $collection->getFacetedData($attribute->getAttributeCode());
        $hideEmpty = (int) $this->getAttributeIsFilterable($attribute) === self::FILTERABLE_WITH_RESULTS;
        $selected = $this->context->state()->get($key);

        $items = [];
        foreach ($attribute->getFrontend()->getSelectOptions() as $option) {
            $value = $option['value'] ?? '';
            if ($value === '' || !is_scalar($value)) {
                continue;
            }
            $value = (string) $value;
            $count = (int) ($faceted[$value]['count'] ?? 0);
            if ($hideEmpty && $count === 0 && !in_array($value, $selected, true)) {
                continue;
            }
            $items[] = ['label' => $this->stripTags->filter((string) $option['label']), 'value' => $value, 'count' => $count];
        }
        $items = $this->sort($items, $config?->getSortBy() ?? Filter::SORT_POSITION);
        if (($config?->isHideSingleOption() ?? true) && count($items) === 1 && $selected === []) {
            return [];
        }

        return $items;
    }

    /**
     * @param array<int, array{label: string, value: string, count: int}> $items
     * @param string $sortBy
     * @return array<int, array{label: string, value: string, count: int}>
     */
    private function sort(array $items, string $sortBy): array
    {
        if ($sortBy === Filter::SORT_NAME) {
            usort($items, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
        } elseif ($sortBy === Filter::SORT_COUNT) {
            usort($items, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        }

        return $items;
    }
}
