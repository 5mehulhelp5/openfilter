<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Converts slider bounds for the current store view: currency rate and the default product tax class rate
 * (per-product tax classes are not covered; M1 ledger). The tax rate is only looked up when prices are shown
 * differently from how they are entered, and then cached for an hour per store view and customer group, so a page
 * runs no extra SQL for it.
 */
class PriceBoundsConverter
{
    private const XML_DEFAULT_PRODUCT_TAX_CLASS = 'tax/classes/default_product_tax_class';
    private const TAX_CACHE_LIFETIME = 3600;

    /** @var array<int, array{float, float}> rate and tax factor by store */
    private array $factors = [];

    /**
     * @param PriceBounds $bounds
     * @param StoreManagerInterface $storeManager
     * @param TaxConfig $taxConfig
     * @param TaxCalculation $taxCalculation
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerSession $customerSession
     * @param FrontendInterface $cache the "openfilter" cache type
     */
    public function __construct(
        private readonly PriceBounds $bounds,
        private readonly StoreManagerInterface $storeManager,
        private readonly TaxConfig $taxConfig,
        private readonly TaxCalculation $taxCalculation,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CustomerSession $customerSession,
        private readonly FrontendInterface $cache
    ) {
    }

    /**
     * @param float $base
     * @return float
     */
    public function toDisplay(float $base): float
    {
        [$rate, $tax] = $this->factors();

        return $this->bounds->toDisplay($base, $rate, $tax);
    }

    /**
     * @param float $display
     * @return float
     */
    public function toBase(float $display): float
    {
        [$rate, $tax] = $this->factors();

        return $this->bounds->toBase($display, $rate, $tax);
    }

    /**
     * Display range of indexed min and max, rounded to the step.
     *
     * @param float $min
     * @param float $max
     * @param float $step
     * @return array{float, float}
     */
    public function displayRange(float $min, float $max, float $step = 1.0): array
    {
        return $this->bounds->round($this->toDisplay($min), $this->toDisplay($max), $step);
    }

    /**
     * @return array{float, float}
     */
    private function factors(): array
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        if (!isset($this->factors[$storeId])) {
            $rate = (float) $store->getCurrentCurrencyRate();
            $enteredWithTax = $this->taxConfig->priceIncludesTax($storeId);
            $displayedWithTax = (int) $this->taxConfig->getPriceDisplayType($storeId) !== TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX;
            $taxRate = $enteredWithTax === $displayedWithTax ? 0.0 : $this->taxRate($storeId);
            $this->factors[$storeId] = [
                $rate > 0 ? $rate : 1.0,
                $this->bounds->taxFactor($taxRate, $enteredWithTax, $displayedWithTax),
            ];
        }

        return $this->factors[$storeId];
    }

    /**
     * Rate of the default product tax class for the shopper's group, cached per store view and group.
     *
     * @param int $storeId
     * @return float
     */
    private function taxRate(int $storeId): float
    {
        $key = sprintf('openfilter_tax_%d_%d', $storeId, (int) $this->customerSession->getCustomerGroupId());
        $cached = $this->cache->load($key);
        if (is_string($cached) && is_numeric($cached)) {
            return (float) $cached;
        }
        $request = $this->taxCalculation->getRateRequest(null, null, null, $storeId);
        $request->setData('product_class_id', (int) $this->scopeConfig->getValue(
            self::XML_DEFAULT_PRODUCT_TAX_CLASS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $rate = (float) $this->taxCalculation->getRate($request);
        $this->cache->save((string) $rate, $key, [CacheType::CACHE_TAG], self::TAX_CACHE_LIFETIME);

        return $rate;
    }
}
