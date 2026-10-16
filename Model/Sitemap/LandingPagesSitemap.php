<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Sitemap;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;

/**
 * Active, indexable landing pages (1.2) of a store view in Magento's XML sitemap (category priority and frequency).
 */
class LandingPagesSitemap implements ItemProviderInterface
{
    /**
     * @param LandingPages $pages
     * @param Config $config
     * @param SitemapItemInterfaceFactory $itemFactory
     * @param ConfigReaderInterface $categoryConfig
     */
    public function __construct(
        private readonly LandingPages $pages,
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
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }
        $pages = array_filter(
            $this->pages->forStore($storeId),
            static fn (LandingPage $page): bool => str_starts_with($page->getRobots(), 'INDEX')
        );

        return array_values(array_map(fn (LandingPage $page) => $this->itemFactory->create([
            'url' => $page->getUrlKey(),
            'updatedAt' => $page->toArray()['updated_at'],
            'priority' => $this->categoryConfig->getPriority($storeId),
            'changeFrequency' => $this->categoryConfig->getChangeFrequency($storeId),
        ]), $pages));
    }
}
