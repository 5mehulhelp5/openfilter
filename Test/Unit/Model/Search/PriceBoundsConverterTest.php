<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search;

use Iranimij\OpenFilter\Model\Search\PriceBounds;
use Iranimij\OpenFilter\Model\Search\PriceBoundsConverter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\TestCase;

class PriceBoundsConverterTest extends TestCase
{
    public function testUsesTheStoreRateAndTheDefaultProductTaxClass(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(2);
        $store->method('getCurrentCurrencyRate')->willReturn(0.9);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $taxConfig = $this->createStub(TaxConfig::class);
        $taxConfig->method('getPriceDisplayType')->willReturn(TaxConfig::DISPLAY_TYPE_INCLUDING_TAX);
        $taxConfig->method('priceIncludesTax')->willReturn(false);
        $request = new DataObject();
        $calculation = $this->createMock(TaxCalculation::class);
        $calculation->expects(self::once())->method('getRateRequest')->with(null, null, null, 2)->willReturn($request);
        $calculation->expects(self::once())->method('getRate')->willReturnCallback(
            static fn (DataObject $r): float => $r->getData('product_class_id') === 2 ? 19.0 : 0.0
        );
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('2');

        $converter = new PriceBoundsConverter(new PriceBounds(), $storeManager, $taxConfig, $calculation, $scopeConfig);

        self::assertEqualsWithDelta(107.1, $converter->toDisplay(100.0), 1e-9);
        self::assertEqualsWithDelta(100.0, $converter->toBase(107.1), 1e-9);
        self::assertSame([8.0, 54.0], $converter->displayRange(8.0, 50.0));
    }
}
