<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Block\Adminhtml\Config;

use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Magento\Backend\Block\Template;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Stores > Configuration > OpenFilter > SEO: the character count and (1.2) a live preview under the title,
 * description and H1 templates, with a real category, two option labels and the store name of the default store
 * view, so the merchant sees what a filtered page would show before saving.
 */
class TemplatePreview extends Template
{
    /** Field id => characters search results show (0 = no count). */
    private const FIELDS = ['openfilter_seo_meta_title' => 60, 'openfilter_seo_meta_description' => 160, 'openfilter_seo_h1' => 0];

    /**
     * @param Template\Context $context
     * @param StoreManagerInterface $storeManager
     * @param CategoryCollectionFactory $categories
     * @param FilterConfigProvider $filters
     * @param EavConfig $eavConfig
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryCollectionFactory $categories,
        private readonly FilterConfigProvider $filters,
        private readonly EavConfig $eavConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Only the OpenFilter section has the templates (the layout handle is every configuration page).
     *
     * @return bool
     */
    public function isOpenFilterSection(): bool
    {
        return $this->getRequest()->getParam('section') === 'openfilter';
    }

    /**
     * @return array{fields: array<string, int>, label: string, preview: string,
     *     sample: array{category: string, filters: string[], store: string}}
     */
    public function getOptions(): array
    {
        return [
            'fields' => self::FIELDS,
            'label' => (string) __('%1 of about %2 characters shown in search results'),
            'preview' => (string) __('Preview: %1'),
            'sample' => $this->sample(),
        ];
    }

    /**
     * @return array{category: string, filters: string[], store: string}
     */
    private function sample(): array
    {
        $store = $this->storeManager->getDefaultStoreView();
        $storeId = $store instanceof Store ? (int) $store->getId() : 0;
        $category = $this->categories->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addLevelFilter(2)
            ->addIsActiveFilter()
            ->setPageSize(1)
            ->getFirstItem()
            ->getData('name');
        $labels = [];
        foreach ($this->filters->getActive($storeId) as $config) {
            if ($config->isBuiltin() || !in_array($config->getInputType(), ['select', 'multiselect'], true)) {
                continue;
            }
            $attribute = $this->eavConfig->getAttribute('catalog_product', $config->getCode());
            $attribute->setData('store_id', $storeId);
            foreach ($attribute->getSource()->getAllOptions() as $option) {
                if ((string) ($option['value'] ?? '') === '') {
                    continue;
                }
                $labels[] = (string) $option['label'];
                if (count($labels) === 2) {
                    break 2;
                }
            }
        }

        return [
            'category' => (string) ($category ?: __('Tops')),
            'filters' => $labels ?: [(string) __('Black'), (string) __('Blue')],
            'store' => $store instanceof Store ? (string) $store->getName() : '',
        ];
    }
}
