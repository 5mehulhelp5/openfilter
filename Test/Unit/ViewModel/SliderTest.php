<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\ViewModel;

use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Layer\Filter\Decimal;
use Iranimij\OpenFilter\Model\Layer\Filter\Price;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\Model\Url\Range;
use Iranimij\OpenFilter\ViewModel\Slider;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SliderTest extends TestCase
{
    public function testPriceSliderSnapsBoundsToTheStepAndKnowsCurrencyAndLocale(): void
    {
        $slider = $this->slider(new FilterState(['color' => ['49']]), 5.0)
            ->forFilter($this->price(['min' => 18.2, 'max' => 92.0, 'count' => 30]));

        self::assertNotNull($slider);
        self::assertSame('price', $slider->getParameter());
        self::assertSame([15.0, 95.0, 5.0], [$slider->getMin(), $slider->getMax(), $slider->getStep()]);
        self::assertNull($slider->getFrom());
        self::assertNull($slider->getTo());
        self::assertSame('EUR', $slider->getCurrency());
        self::assertSame('de-DE', $slider->getLocale());
    }

    public function testCurrentRangeComesFromTheUrl(): void
    {
        $slider = $this->slider(new FilterState(['price' => ['20-50']]), 1.0)
            ->forFilter($this->price(['min' => 10.0, 'max' => 90.0, 'count' => 12]));

        self::assertNotNull($slider);
        self::assertSame([20.0, 50.0], [$slider->getFrom(), $slider->getTo()]);
    }

    public function testTheFormKeepsTheOtherParametersAndTheTemplateHasTheRangePlaceholder(): void
    {
        $slider = $this->slider(new FilterState(['color' => ['49'], 'price' => ['20-50']]), 1.0)
            ->forFilter($this->price(['min' => 10.0, 'max' => 90.0, 'count' => 12]));

        self::assertNotNull($slider);
        self::assertSame('https://shop.test/tops.html', $slider->getAction());
        self::assertSame(['color' => 'black', 'product_list_order' => 'name'], $slider->getHiddenFields());
        self::assertSame('https://shop.test/tops.html?color=black&product_list_order=name', $slider->getClearUrl());
        self::assertSame(
            'https://shop.test/tops.html?color=black&price=' . Slider::PLACEHOLDER . '&product_list_order=name',
            $slider->getUrlTemplate()
        );
    }

    public function testDecimalSlidersHaveNoCurrencyAndAFinerStepOnNarrowRanges(): void
    {
        $decimal = $this->getMockBuilder(Decimal::class)->disableOriginalConstructor()
            ->onlyMethods(['getOpenFilterKey', 'getStats'])->getMock();
        $decimal->method('getOpenFilterKey')->willReturn('width');
        $decimal->method('getStats')->willReturn(['min' => 1.0, 'max' => 7.5, 'count' => 4]);

        $slider = $this->slider(new FilterState([]), 1.0)->forFilter($decimal);

        self::assertNotNull($slider);
        self::assertNull($slider->getCurrency());
        self::assertSame([1.0, 7.5, 0.1], [$slider->getMin(), $slider->getMax(), $slider->getStep()]);
    }

    public function testNoSliderWithoutStatsOrWithoutARange(): void
    {
        self::assertNull($this->slider(new FilterState([]), 1.0)->forFilter($this->price(null)));
        self::assertNull(
            $this->slider(new FilterState([]), 1.0)->forFilter($this->price(['min' => 40.0, 'max' => 40.0, 'count' => 1]))
        );
    }

    /**
     * @param array{min: float, max: float, count: int}|null $stats
     */
    private function price(?array $stats): Price
    {
        $price = $this->getMockBuilder(Price::class)->disableOriginalConstructor()
            ->onlyMethods(['getOpenFilterKey', 'getStats'])->getMock();
        $price->method('getOpenFilterKey')->willReturn('price');
        $price->method('getStats')->willReturn($stats);

        return $price;
    }

    private function slider(FilterStateInterface $state, float $step): Slider
    {
        $context = $this->createStub(LayerContext::class);
        $context->method('state')->willReturn($state);
        $context->method('storeId')->willReturn(1);
        $context->method('parameterFor')->willReturnCallback(static fn (string $key): string => $key);
        $context->method('urlFor')->willReturnCallback(static function (FilterStateInterface $s): string {
            $query = [];
            if ($s->has('color')) {
                $query[] = 'color=black';
            }
            if ($s->has('price')) {
                $query[] = 'price=' . $s->get('price')[0];
            }
            $query[] = 'product_list_order=name';
            return 'https://shop.test/tops.html?' . implode('&', $query);
        });
        $config = $this->createStub(Config::class);
        $config->method('getPriceStep')->willReturn($step);
        $store = $this->createStub(Store::class);
        $store->method('getCurrentCurrencyCode')->willReturn('EUR');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $locale = $this->createStub(ResolverInterface::class);
        $locale->method('getLocale')->willReturn('de_DE');

        return new Slider($context, $config, new Range(), $storeManager, $locale);
    }
}
