<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer\Filter;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Layer\CategoryTree as Tree;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Search\CategoryPicks;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection as FulltextCollection;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The category filter as a tree (1.1, display type "Category tree"): the categories below the page's category (the
 * store's root on search pages) down to the configured levels, with counts; picked categories narrow the page with
 * "any of" (?cat=12,15) instead of leaving it, as core's drill-down does.
 */
class CategoryTree extends AbstractFilter implements OpenFilterInterface
{
    public const REQUEST_VAR = 'cat';

    /** @var array<int, array{id: int, name: string, depth: int}>|null */
    private ?array $nodes = null;

    /**
     * @param ItemFactory $filterItemFactory
     * @param StoreManagerInterface $storeManager
     * @param Layer $layer
     * @param DataBuilder $itemDataBuilder
     * @param LayerContext $context
     * @param Tree $tree
     * @param CategoryPicks $picks
     * @param array<string, mixed> $data
     */
    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        private readonly LayerContext $context,
        private readonly Tree $tree,
        private readonly CategoryPicks $picks,
        array $data = []
    ) {
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $data);
        $this->_requestVar = self::REQUEST_VAR;
    }

    /**
     * @inheritDoc
     */
    public function getOpenFilterKey(): ?string
    {
        return Filter::BUILTIN_CATEGORY;
    }

    /**
     * @inheritDoc
     */
    public function isMultiselect(): bool
    {
        return $this->context->config(Filter::BUILTIN_CATEGORY)?->isMultiselect() ?? false;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->context->config(Filter::BUILTIN_CATEGORY)?->getLabel() ?? (string) __('Category');
    }

    /**
     * Picks below the page's category narrow the products ("any of"); the tree's categories are counted in the same
     * search request.
     *
     * @param RequestInterface $request
     * @return $this
     */
    public function apply(RequestInterface $request)
    {
        $nodes = $this->nodes();
        $names = array_column($nodes, 'name', 'id');
        $picked = array_values(array_filter(
            array_map('intval', $this->context->state()->get(Filter::BUILTIN_CATEGORY)),
            fn (int $id): bool => $this->tree->isBelow($id, $this->rootId(), $this->storeId())
        ));
        if ($picked !== []) {
            $this->picks->register($picked);
            foreach ($picked as $id) {
                $name = $names[$id] ?? (string) ($this->tree->forStore($this->storeId())[$id]['name'] ?? $id);
                $this->getLayer()->getState()->addFilter($this->_createItem($name, (string) $id));
            }
        }
        if ($nodes !== []) {
            $this->getLayer()->getProductCollection()->addFieldToFilter(
                'category_ids_to_aggregate',
                array_values(array_unique(array_merge(array_column($nodes, 'id'), $picked)))
            );
        }

        return $this;
    }

    /**
     * Tree nodes with products (picked ones always), depth first, each with its depth.
     *
     * @return array<int, array{label: string, value: string, count: int, depth: int}>
     */
    protected function _getItemsData()
    {
        /** @var FulltextCollection $collection layered navigation runs on the search collection */
        $collection = $this->getLayer()->getProductCollection();
        $counts = $collection->getFacetedData('category');
        $picked = array_flip($this->context->state()->get(Filter::BUILTIN_CATEGORY));
        $items = [];
        foreach ($this->nodes() as $node) {
            $count = (int) ($counts[$node['id']]['count'] ?? 0);
            if ($count === 0 && !isset($picked[(string) $node['id']])) {
                continue;
            }
            $items[] = ['label' => $node['name'], 'value' => (string) $node['id'], 'count' => $count, 'depth' => $node['depth']];
        }

        return $items;
    }

    /**
     * Items keep their depth (core's item data builder knows label, value and count only).
     *
     * @return $this
     */
    protected function _initItems()
    {
        $items = [];
        foreach ($this->_getItemsData() as $data) {
            $item = $this->_createItem($data['label'], $data['value'], $data['count']);
            $item->setData('depth', $data['depth']);
            $items[] = $item;
        }
        $this->_items = $items;

        return $this;
    }

    /**
     * @return array<int, array{id: int, name: string, depth: int}>
     */
    private function nodes(): array
    {
        $depth = $this->context->config(Filter::BUILTIN_CATEGORY)?->getTreeDepth() ?? Filter::TREE_DEPTH_DEFAULT;

        return $this->nodes ??= $this->tree->below($this->rootId(), $depth, $this->storeId());
    }

    /**
     * @return int the page's category (the store's root category on search pages)
     */
    private function rootId(): int
    {
        return (int) $this->getLayer()->getCurrentCategory()->getId();
    }

    /**
     * @return int
     */
    private function storeId(): int
    {
        return (int) $this->_storeManager->getStore()->getId();
    }
}
