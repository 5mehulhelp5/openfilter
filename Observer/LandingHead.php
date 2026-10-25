<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Observer;

use Iranimij\OpenFilter\Model\Landing\CurrentPage;
use Iranimij\OpenFilter\Model\Landing\LandingUrl;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Theme\Block\Html\Title;

/**
 * The head and heading of a landing page (1.2), once the blocks exist (Magento's category view block names the page
 * after the category while the layout is built): title, meta description, robots, H1 and the page's own canonical URL.
 */
class LandingHead implements ObserverInterface
{
    /**
     * @param CurrentPage $current
     * @param LandingUrl $landingUrl
     * @param PageConfig $pageConfig
     */
    public function __construct(
        private readonly CurrentPage $current,
        private readonly LandingUrl $landingUrl,
        private readonly PageConfig $pageConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $page = $this->current->get();
        if ($page === null) {
            return;
        }
        $this->pageConfig->getTitle()->set($page->getMetaTitle());
        if ($page->getMetaDescription() !== null) {
            $this->pageConfig->setDescription($page->getMetaDescription());
        }
        $this->pageConfig->setRobots($page->getRobots());
        $layout = $observer->getEvent()->getData('layout');
        $heading = $layout instanceof LayoutInterface ? $layout->getBlock('page.main.title') : false;
        if ($heading instanceof Title) {
            $heading->setPageTitle($page->getHeading());
        }
        $assets = $this->pageConfig->getAssetCollection();
        foreach ($assets->getAll() as $identifier => $asset) {
            if ($asset->getContentType() === 'canonical') {
                $assets->remove($identifier);
            }
        }
        $this->pageConfig->addRemotePageAsset($this->landingUrl->url($page), 'canonical', ['attributes' => ['rel' => 'canonical']]);
    }
}
