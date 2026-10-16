<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Landing;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The address of a landing page in a store view (1.2).
 */
class LandingUrl
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param LandingPage $page
     * @param int|null $storeId null = the current store view
     * @return string
     */
    public function url(LandingPage $page, ?int $storeId = null): string
    {
        $store = $this->storeManager->getStore($storeId);
        $base = $store instanceof Store ? $store->getBaseUrl() : '/';

        return rtrim($base, '/') . '/' . $page->getUrlKey();
    }
}
