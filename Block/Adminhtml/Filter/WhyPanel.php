<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Block\Adminhtml\Filter;

use Iranimij\OpenFilter\Model\Doctor\Check;
use Iranimij\OpenFilter\Model\Doctor\FilterDiagnosis;
use Magento\Backend\Block\Template;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The "Why is this filter not showing?" panel of the filter form: checks for the default store view, then any store
 * view and category the merchant picks.
 */
class WhyPanel extends Template
{
    /** Categories offered in the picker (deeper catalogs type the ID). */
    private const MAX_CATEGORIES = 1000;

    /**
     * @param Template\Context $context
     * @param FilterDiagnosis $diagnosis
     * @param StoreManagerInterface $storeManager
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly FilterDiagnosis $diagnosis,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getFilterKey(): string
    {
        return (string) $this->getRequest()->getParam('code');
    }

    /**
     * @return int
     */
    public function getDefaultStoreId(): int
    {
        return (int) $this->storeManager->getDefaultStoreView()?->getId();
    }

    /**
     * @return array<int, string> store view id => name
     */
    public function getStores(): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            $stores[(int) $store->getId()] = (string) $store->getName();
        }
        ksort($stores);

        return $stores;
    }

    /**
     * Categories below the root categories, as "Parent / Child" paths.
     *
     * @return array<int, string>
     */
    public function getCategories(): array
    {
        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addFieldToFilter('level', ['gteq' => 2])
            ->setOrder('path', 'ASC')
            ->setPageSize(self::MAX_CATEGORIES);
        $names = [];
        foreach ($collection as $category) {
            $names[(int) $category->getId()] = (string) $category->getName();
        }
        $paths = [];
        foreach ($collection as $category) {
            $parts = [];
            foreach (array_slice(explode('/', (string) $category->getPath()), 2) as $id) {
                $parts[] = $names[(int) $id] ?? $id;
            }
            $paths[(int) $category->getId()] = implode(' / ', $parts);
        }

        return $paths;
    }

    /**
     * Checks for the default store view, rendered before any JavaScript runs.
     *
     * @return Check[]
     */
    public function getInitialChecks(): array
    {
        return $this->diagnosis->diagnose($this->getFilterKey(), $this->getDefaultStoreId());
    }

    /**
     * @return string
     */
    public function getDiagnoseUrl(): string
    {
        return $this->getUrl('openfilter/filter/diagnose', ['code' => $this->getFilterKey()]);
    }
}
