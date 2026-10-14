<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Url\FilterStateProvider;
use Magento\CatalogInventory\Model\Configuration as InventoryConfiguration;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * The built-in filters of the current storefront request: which are switched on, their clauses and aggregations.
 * The stock filter only exists when out-of-stock products are displayed (otherwise every product is in stock).
 */
class BuiltinFilters
{
    private const CODES = [Filter::BUILTIN_RATING, Filter::BUILTIN_STOCK, Filter::BUILTIN_SALE, Filter::BUILTIN_NEW];

    /**
     * @param FilterConfigProvider $configProvider
     * @param FilterStateProvider $stateProvider
     * @param BuiltinClauses $clauses
     * @param StoreManagerInterface $storeManager
     * @param CustomerSession $customerSession
     * @param TimezoneInterface $timezone
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly FilterConfigProvider $configProvider,
        private readonly FilterStateProvider $stateProvider,
        private readonly BuiltinClauses $clauses,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
        private readonly TimezoneInterface $timezone,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Active built-in codes of the current store view.
     *
     * @return string[]
     */
    public function activeCodes(): array
    {
        $store = $this->storeManager->getStore();
        $active = $this->configProvider->getActive((int) $store->getId());
        $codes = array_values(array_filter(self::CODES, static fn (string $code): bool => isset($active[$code])));
        if (!$this->scopeConfig->isSetFlag(InventoryConfiguration::XML_PATH_SHOW_OUT_OF_STOCK, ScopeInterface::SCOPE_STORE, $store->getId())) {
            $codes = array_values(array_diff($codes, [Filter::BUILTIN_STOCK]));
        }

        return $codes;
    }

    /**
     * @return array<string, array{bucket: string, field: string, aggregation: array<string, mixed>,
     *     clause: array<string, mixed>|null}>
     */
    public function forRequest(): array
    {
        $codes = $this->activeCodes();
        if ($codes === []) {
            return [];
        }
        $store = $this->storeManager->getStore();

        return $this->clauses->build(
            $codes,
            $this->stateProvider->get(),
            (int) $this->customerSession->getCustomerGroupId(),
            (int) $store->getWebsiteId(),
            $this->timezone->scopeDate($store, null, true)->format('Y-m-d H:i:s')
        );
    }
}
