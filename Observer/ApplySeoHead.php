<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Observer;

use Iranimij\OpenFilter\Model\AllProducts;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Landing\LandingLinks;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Seo\CanonicalResolver;
use Iranimij\OpenFilter\Model\Seo\CategoryUrl;
use Iranimij\OpenFilter\Model\Seo\RobotsResolver;
use Iranimij\OpenFilter\Model\Seo\SeoTemplates;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Title;

/**
 * Robots and canonical of filtered category and search pages, set while the layout is built, so the HTML (and every
 * cached copy of it, also the one the AJAX layer fetches) carries the tags a crawler should see for that URL.
 * Pages without filters keep the store's own tags.
 */
class ApplySeoHead implements ObserverInterface
{
    /** Full action names of layered pages => search page. */
    private const PAGES = [
        'catalog_category_view' => false,
        'catalogsearch_result_index' => true,
        AllProducts::ACTION => false,
    ];

    /** Core's category filter parameter (not handled by the codec). */
    private const CATEGORY_PARAM = 'cat';

    private const CANONICAL = 'canonical';

    /**
     * @param LayerContext $context
     * @param RobotsResolver $robots
     * @param CanonicalResolver $canonical
     * @param PageConfig $pageConfig
     * @param RequestInterface $request
     * @param LayerResolver $layerResolver
     * @param Escaper $escaper
     * @param CategoryUrl $categoryUrl
     * @param Config $config
     * @param SeoTemplates $templates title, description and H1 templates (1.1)
     * @param StoreManagerInterface $storeManager
     * @param AllProducts $allProducts the All Products page (1.2): its own URL and title instead of the root category's
     * @param LandingLinks $landingLinks a filter state that is a landing page's has the landing page as canonical (1.2)
     */
    public function __construct(
        private readonly LayerContext $context,
        private readonly RobotsResolver $robots,
        private readonly CanonicalResolver $canonical,
        private readonly PageConfig $pageConfig,
        private readonly RequestInterface $request,
        private readonly LayerResolver $layerResolver,
        private readonly Escaper $escaper,
        private readonly CategoryUrl $categoryUrl,
        private readonly Config $config,
        private readonly SeoTemplates $templates,
        private readonly StoreManagerInterface $storeManager,
        private readonly AllProducts $allProducts,
        private readonly LandingLinks $landingLinks
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $action = (string) $observer->getEvent()->getData('full_action_name');
        if (!isset(self::PAGES[$action]) || !$this->context->isActive()) {
            return;
        }
        $searchPage = self::PAGES[$action];
        $state = $this->context->state();
        $category = $this->request->getParam(self::CATEGORY_PARAM);
        $otherValues = is_scalar($category) && trim((string) $category) !== '' ? 1 : 0;
        if ($state->count() + $otherValues === 0) {
            return;
        }
        $storeId = $this->context->storeId();
        $indexable = $this->robots->isIndexable($state, $otherValues, $searchPage, $storeId);
        if (!$indexable) {
            $this->pageConfig->setRobots(RobotsResolver::NOINDEX_FOLLOW);
        }
        if ($searchPage) {
            return;
        }
        $layer = $this->layerResolver->get();
        $allProducts = $action === AllProducts::ACTION;
        $this->applyTemplates($observer, $layer, $storeId, $allProducts ? $this->allProducts->title($storeId) : null);
        $categoryUrl = $allProducts
            ? $this->allProducts->url($storeId)
            : $this->categoryUrl->get($layer->getCurrentCategory(), $storeId);
        $page = $this->request->getParam('p');
        $url = $this->canonical->resolve(
            $state,
            $indexable,
            $categoryUrl,
            is_scalar($page) ? max(1, (int) $page) : 1,
            $storeId
        );
        $url = $allProducts ? $url : ($this->landingLinks->urlFor($state, [], $storeId) ?? $url);
        $this->replaceCanonical($this->escaper->escapeUrl($url));
    }

    /**
     * The merchant's title, description and H1 templates for filtered category pages (1.1); empty = Magento's.
     *
     * @param Observer $observer
     * @param Layer $layer
     * @param int $storeId
     * @param string|null $pageName the page's name for {category} (the All Products page's title); null = the category's
     * @return void
     */
    private function applyTemplates(Observer $observer, Layer $layer, int $storeId, ?string $pageName = null): void
    {
        $title = $this->config->getMetaTitleTemplate($storeId);
        $description = $this->config->getMetaDescriptionTemplate($storeId);
        $heading = $this->config->getH1Template($storeId);
        if ($title === '' && $description === '' && $heading === '') {
            return;
        }
        $values = [
            'category' => $pageName ?? (string) $layer->getCurrentCategory()->getName(),
            'filters' => array_values(array_filter(array_map(
                static fn ($item): string => trim(strip_tags((string) $item->getData('label'))),
                $layer->getState()->getFilters()
            ))),
            'store' => (string) $this->storeManager->getStore($storeId)->getName(),
        ];
        if ($title !== '') {
            $this->pageConfig->getTitle()->set($this->templates->render($title, $values));
        }
        if ($description !== '') {
            $this->pageConfig->setDescription($this->templates->render($description, $values));
        }
        $layout = $observer->getEvent()->getData('layout');
        $block = $heading !== '' && $layout instanceof LayoutInterface ? $layout->getBlock('page.main.title') : false;
        if ($block instanceof Title) {
            // Core's category block set the category name with setPageTitle(); the block reads only that.
            $block->setPageTitle($this->templates->render($heading, $values));
        }
    }

    /**
     * Removes the canonical links already registered (core's category canonical) and adds this one.
     *
     * @param string $url escaped URL
     * @return void
     */
    private function replaceCanonical(string $url): void
    {
        $assets = $this->pageConfig->getAssetCollection();
        foreach ($assets->getAll() as $identifier => $asset) {
            if ($asset->getContentType() === self::CANONICAL) {
                $assets->remove($identifier);
            }
        }
        $this->pageConfig->addRemotePageAsset($url, self::CANONICAL, ['attributes' => ['rel' => self::CANONICAL]]);
    }
}
