<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Converts slider bounds for the current store view: currency rate and the default product tax class rate
 * (per-product tax classes are not covered; 07 · Build Brief decision, logged in the M1 ledger).
 */
class PriceBoundsConverter
{
    private const XML_DEFAULT_PRODUCT_TAX_CLASS = 'tax/classes/default_product_tax_class';

    /** @var array<int, array{float, float}> rate and tax factor by store */
    private array $factors = [];

    /**
     * @param PriceBounds $bounds
     * @param StoreManagerInterface $storeManager
     * @param TaxConfig $taxConfig
     * @param TaxCalculation $taxCalculation
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly PriceBounds $bounds,
        private readonly StoreManagerInterface $storeManager,
        private readonly TaxConfig $taxConfig,
        private readonly TaxCalculation $taxCalculation,
        private readonly ScopeConfigInterface $scopeConfig
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
            $request = $this->taxCalculation->getRateRequest(null, null, null, $storeId);
            $request->setData('product_class_id', (int) $this->scopeConfig->getValue(
                self::XML_DEFAULT_PRODUCT_TAX_CLASS,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ));
            $displayType = (int) $this->taxConfig->getPriceDisplayType($storeId);
            $this->factors[$storeId] = [
                $rate > 0 ? $rate : 1.0,
                $this->bounds->taxFactor(
                    (float) $this->taxCalculation->getRate($request),
                    $this->taxConfig->priceIncludesTax($storeId),
                    $displayType !== TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX
                ),
            ];
        }

        return $this->factors[$storeId];
    }
}
