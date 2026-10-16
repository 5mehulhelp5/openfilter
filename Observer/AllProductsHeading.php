<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Observer;

use Iranimij\OpenFilter\Model\AllProducts;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Theme\Block\Html\Title;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Page\Config as PageConfig;

/**
 * The All Products page (1.2) keeps its own title, H1 and canonical URL: Magento's category view block names the page
 * after the category (the store's root) while the layout is built, so this runs once the blocks exist (before
 * ApplySeoHead, which replaces the canonical URL on filtered pages).
 */
class AllProductsHeading implements ObserverInterface
{
    /**
     * @param AllProducts $allProducts
     * @param PageConfig $pageConfig
     */
    public function __construct(
        private readonly AllProducts $allProducts,
        private readonly PageConfig $pageConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        if ((string) $observer->getEvent()->getData('full_action_name') !== AllProducts::ACTION) {
            return;
        }
        $title = $this->allProducts->title();
        $this->pageConfig->getTitle()->set($title);
        $layout = $observer->getEvent()->getData('layout');
        $heading = $layout instanceof LayoutInterface ? $layout->getBlock('page.main.title') : false;
        if ($heading instanceof Title) {
            $heading->setPageTitle($title);
        }
        $assets = $this->pageConfig->getAssetCollection();
        foreach ($assets->getAll() as $identifier => $asset) {
            if ($asset->getContentType() === 'canonical') {
                $assets->remove($identifier);
            }
        }
        $this->pageConfig->addRemotePageAsset($this->allProducts->url(), 'canonical', ['attributes' => ['rel' => 'canonical']]);
    }
}
