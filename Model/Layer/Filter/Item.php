<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer\Filter;

use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Magento\Catalog\Model\Layer\Filter\Item as CoreItem;
use Magento\Framework\UrlInterface;
use Magento\Theme\Block\Html\Pager;

/**
 * Filter option with canonical links: selecting adds the value (or replaces it in single-select mode), removing
 * takes only this value away.
 */
class Item extends CoreItem
{
    /**
     * @param UrlInterface $url
     * @param Pager $htmlPagerBlock
     * @param LayerContext $context
     * @param array<string, mixed> $data
     */
    public function __construct(
        UrlInterface $url,
        Pager $htmlPagerBlock,
        private readonly LayerContext $context,
        array $data = []
    ) {
        parent::__construct($url, $htmlPagerBlock, $data);
    }

    /**
     * Link that selects this option (or deselects it when it is selected already). Core filters (category drill-down)
     * get their own parameter on top of the canonical filter state.
     *
     * @return string
     */
    public function getUrl()
    {
        $key = $this->key();
        if ($key !== null) {
            return $this->context->toggleUrl($key, (string) $this->getData('value'));
        }
        if ($this->context->isActive()) {
            $filter = $this->getFilter();

            return $this->context->urlFor(
                $this->context->state(),
                [(string) $filter->getRequestVar() => $this->valueString()]
            );
        }

        return parent::getUrl();
    }

    /**
     * Link that removes this value only.
     *
     * @return string
     */
    public function getRemoveUrl()
    {
        $key = $this->key();
        if ($key !== null) {
            return $this->context->removeUrl($key, (string) $this->getData('value'));
        }
        if ($this->context->isActive()) {
            return $this->context->urlFor(
                $this->context->state(),
                [(string) $this->getFilter()->getRequestVar() => $this->getFilter()->getResetValue()]
            );
        }

        return parent::getRemoveUrl();
    }

    /**
     * Link that removes every value of this filter.
     *
     * @return string|false
     */
    public function getClearLinkUrl()
    {
        $key = $this->key();
        if ($key === null) {
            return parent::getClearLinkUrl();
        }

        return $this->context->urlFor($this->context->state()->without($key));
    }

    /**
     * Whether this option is part of the current filter state.
     *
     * @return bool
     */
    public function isSelected(): bool
    {
        $key = $this->key();

        return $key !== null && in_array((string) $this->getData('value'), $this->context->state()->get($key), true);
    }

    /**
     * @return string|null the OpenFilter key of the item's filter, null for core filters
     */
    private function key(): ?string
    {
        $filter = $this->getFilter();

        return $filter instanceof OpenFilterInterface ? $filter->getOpenFilterKey() : null;
    }

    /**
     * Core filter values may be arrays (price ranges as [from, to]).
     *
     * @return string
     */
    private function valueString(): string
    {
        $value = $this->getData('value');

        return is_array($value) ? implode('-', $value) : (string) $value;
    }
}
