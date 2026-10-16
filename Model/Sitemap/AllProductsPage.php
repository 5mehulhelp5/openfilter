<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Sitemap;

use Iranimij\OpenFilter\Model\AllProducts;
use Iranimij\OpenFilter\Model\Config;
use Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;

/**
 * The All Products page (1.2) in Magento's XML sitemap, where the page is on (with the category settings' priority and
 * frequency).
 */
class AllProductsPage implements ItemProviderInterface
{
    /**
     * @param AllProducts $allProducts
     * @param Config $config
     * @param SitemapItemInterfaceFactory $itemFactory
     * @param ConfigReaderInterface $categoryConfig
     */
    public function __construct(
        private readonly AllProducts $allProducts,
        private readonly Config $config,
        private readonly SitemapItemInterfaceFactory $itemFactory,
        private readonly ConfigReaderInterface $categoryConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getItems($storeId)
    {
        $storeId = (int) $storeId;
        if (!$this->config->isEnabled($storeId) || !$this->allProducts->isEnabled($storeId)) {
            return [];
        }

        return [$this->itemFactory->create([
            'url' => $this->allProducts->urlKey($storeId),
            'priority' => $this->categoryConfig->getPriority($storeId),
            'changeFrequency' => $this->categoryConfig->getChangeFrequency($storeId),
        ])];
    }
}
