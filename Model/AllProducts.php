<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model;

use Iranimij\Base\Model\Config\TypedReader;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The All Products page (1.2): every product of the store view with the filters, at a URL key of the merchant's
 * choice. It shows the store's root category through Magento's category page, so templates, SEO rules and AJAX work
 * as on any category page.
 */
class AllProducts
{
    public const XML_PATH_ENABLED = 'openfilter/all_products/enabled';
    public const XML_PATH_URL_KEY = 'openfilter/all_products/url_key';
    public const XML_PATH_TITLE = 'openfilter/all_products/title';

    /** Full action name of the page (layout handle, events); Controller\Router sends its URL key here. */
    public const ACTION = 'openfilter_all_index';

    private const DEFAULT_URL_KEY = 'all-products';

    /**
     * @param TypedReader $reader
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly TypedReader $reader,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->reader->getBool(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * URL key below the store's base URL: lower case, slashes between parts, no leading or trailing slash.
     *
     * @param int|null $storeId
     * @return string
     */
    public function urlKey(?int $storeId = null): string
    {
        $key = strtolower(trim($this->reader->getString(self::XML_PATH_URL_KEY, ScopeInterface::SCOPE_STORE, $storeId), " /"));

        return preg_match('#^[a-z0-9][a-z0-9._-]*(?:/[a-z0-9][a-z0-9._-]*)*$#', $key) === 1 ? $key : self::DEFAULT_URL_KEY;
    }

    /**
     * Page title and heading.
     *
     * @param int|null $storeId
     * @return string
     */
    public function title(?int $storeId = null): string
    {
        $title = trim($this->reader->getString(self::XML_PATH_TITLE, ScopeInterface::SCOPE_STORE, $storeId));

        return $title !== '' ? $title : (string) __('All products');
    }

    /**
     * The page's URL in a store view (the canonical URL of the unfiltered page).
     *
     * @param int|null $storeId
     * @return string
     */
    public function url(?int $storeId = null): string
    {
        $store = $this->storeManager->getStore($storeId);
        $base = $store instanceof Store ? $store->getBaseUrl() : '/';

        return rtrim($base, '/') . '/' . $this->urlKey((int) $store->getId());
    }
}
