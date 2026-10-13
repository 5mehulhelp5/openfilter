<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer\Filter;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Search\PriceBoundsConverter;
use Iranimij\OpenFilter\Model\Url\Range;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\CatalogSearch\Model\Layer\Filter\PriceFactory as CorePriceFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Price slider filter: bounds in the display currency and tax display of the store view, per customer group and
 * website through Magento's price field.
 */
class Price extends AbstractRangeFilter
{
    /**
     * @param ItemFactory $filterItemFactory
     * @param StoreManagerInterface $storeManager
     * @param Layer $layer
     * @param DataBuilder $itemDataBuilder
     * @param LayerContext $context
     * @param Range $range
     * @param PriceBoundsConverter $converter
     * @param PriceCurrencyInterface $priceCurrency
     * @param CorePriceFactory $corePriceFactory
     * @param array<string, mixed> $data
     */
    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        LayerContext $context,
        Range $range,
        private readonly PriceBoundsConverter $converter,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly CorePriceFactory $corePriceFactory,
        array $data = []
    ) {
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $context, $range, $data);
        $this->_requestVar = Filter::BUILTIN_PRICE;
    }

    /**
     * @inheritDoc
     */
    public function getOpenFilterKey(): ?string
    {
        return $this->context->isActive() && $this->context->config(Filter::BUILTIN_PRICE) !== null
            ? Filter::BUILTIN_PRICE
            : null;
    }

    /**
     * @inheritDoc
     */
    protected function field(): string
    {
        return 'price';
    }

    /**
     * @inheritDoc
     */
    protected function createCoreFilter(): AbstractFilter
    {
        return $this->corePriceFactory->create(['layer' => $this->getLayer(), 'data' => $this->getData()]);
    }

    /**
     * @inheritDoc
     */
    protected function toIndex(float $display): float
    {
        return $this->converter->toBase($display);
    }

    /**
     * @inheritDoc
     */
    protected function toDisplayRange(float $min, float $max): array
    {
        return $this->converter->displayRange($min, $max);
    }

    /**
     * @inheritDoc
     */
    protected function label(?float $from, ?float $to): string
    {
        $format = fn (float $value): string => (string) $this->priceCurrency->format($value, false);
        if ($to === null) {
            return (string) __('%1 and above', $format((float) $from));
        }
        if ($from === null) {
            return (string) __('up to %1', $format($to));
        }

        return (string) __('%1 - %2', $format($from), $format($to));
    }
}
