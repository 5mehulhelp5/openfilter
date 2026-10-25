<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Layer\Search as SearchLayer;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Merchant insights on the storefront (1.2, opt-in): whether the page reports filter use, where to, and what the page
 * shows (store view, category, active filters), printed into the page so the static script only reads it. Nothing
 * about the shopper is part of it, so the page stays the same for everyone and in the full page cache.
 */
class Insights implements ArgumentInterface
{
    public const ROUTE = 'openfilter/insights/collect';

    /**
     * @param Config $config
     * @param LayerContext $context
     * @param Resolver $layerResolver
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly Config $config,
        private readonly LayerContext $context,
        private readonly Resolver $layerResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        $storeId = $this->context->storeId();

        return $this->config->isEnabled($storeId) && $this->config->isInsightsEnabled($storeId);
    }

    /**
     * @return string the store view's beacon address
     */
    public function endpoint(): string
    {
        return $this->url->getUrl(self::ROUTE, ['_nosid' => true]);
    }

    /**
     * The beacon without its product count bucket (the script adds it from the product count after each load).
     *
     * @return string JSON {"s": store code, "c": category id (0 = search results), "f": filter key => values}
     */
    public function payload(): string
    {
        $layer = $this->layerResolver->get();
        $category = $layer instanceof SearchLayer ? 0 : (int) $layer->getCurrentCategory()->getId();

        return (string) json_encode([
            's' => (string) $this->storeManager->getStore()->getCode(),
            'c' => $category,
            'f' => (object) $this->context->state()->getValues(),
        ]);
    }
}
