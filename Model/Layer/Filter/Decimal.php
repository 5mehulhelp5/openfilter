<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer\Filter;

use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Search\PriceBounds;
use Iranimij\OpenFilter\Model\Url\Range;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\CatalogSearch\Model\Layer\Filter\DecimalFactory as CoreDecimalFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Slider filter for decimal attributes (weight, width): raw values, no currency or tax.
 */
class Decimal extends AbstractRangeFilter
{
    private ?string $key = null;

    private bool $resolved = false;

    /**
     * @param ItemFactory $filterItemFactory
     * @param StoreManagerInterface $storeManager
     * @param Layer $layer
     * @param DataBuilder $itemDataBuilder
     * @param LayerContext $context
     * @param Range $range
     * @param PriceBounds $bounds
     * @param CoreDecimalFactory $coreDecimalFactory
     * @param array<string, mixed> $data
     */
    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        LayerContext $context,
        Range $range,
        private readonly PriceBounds $bounds,
        private readonly CoreDecimalFactory $coreDecimalFactory,
        array $data = []
    ) {
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $context, $range, $data);
        $key = $this->getOpenFilterKey();
        $this->_requestVar = ($key !== null ? $this->context->parameterFor($key) : null)
            ?? (string) $this->getAttributeModel()->getAttributeCode();
    }

    /**
     * @inheritDoc
     */
    public function getOpenFilterKey(): ?string
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $this->key = $this->context->isActive()
                ? $this->context->keyForAttribute((int) $this->getAttributeModel()->getAttributeId())
                : null;
        }

        return $this->key;
    }

    /**
     * @inheritDoc
     */
    protected function field(): string
    {
        return (string) $this->getAttributeModel()->getAttributeCode();
    }

    /**
     * @inheritDoc
     */
    protected function createCoreFilter(): AbstractFilter
    {
        return $this->coreDecimalFactory->create(['layer' => $this->getLayer(), 'data' => $this->getData()]);
    }

    /**
     * @inheritDoc
     */
    protected function toIndex(float $display): float
    {
        return $display;
    }

    /**
     * @inheritDoc
     */
    protected function toDisplayRange(float $min, float $max): array
    {
        return $this->bounds->round($min, $max);
    }

    /**
     * @inheritDoc
     */
    protected function label(?float $from, ?float $to): string
    {
        if ($to === null) {
            return (string) __('%1 and above', $this->number((float) $from));
        }
        if ($from === null) {
            return (string) __('up to %1', $this->number($to));
        }

        return (string) __('%1 - %2', $this->number($from), $this->number($to));
    }
}
