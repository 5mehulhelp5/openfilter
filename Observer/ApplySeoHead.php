<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Observer;

use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Seo\CanonicalResolver;
use Iranimij\OpenFilter\Model\Seo\CategoryUrl;
use Iranimij\OpenFilter\Model\Seo\RobotsResolver;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Page\Config as PageConfig;

/**
 * Robots and canonical of filtered category and search pages, set while the layout is built, so the HTML (and every
 * cached copy of it, also the one the AJAX layer fetches) carries the tags a crawler should see for that URL.
 * Pages without filters keep the store's own tags.
 */
class ApplySeoHead implements ObserverInterface
{
    /** Full action names of layered pages => search page. */
    private const PAGES = ['catalog_category_view' => false, 'catalogsearch_result_index' => true];

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
     */
    public function __construct(
        private readonly LayerContext $context,
        private readonly RobotsResolver $robots,
        private readonly CanonicalResolver $canonical,
        private readonly PageConfig $pageConfig,
        private readonly RequestInterface $request,
        private readonly LayerResolver $layerResolver,
        private readonly Escaper $escaper,
        private readonly CategoryUrl $categoryUrl
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
        $categoryUrl = $this->categoryUrl->get($this->layerResolver->get()->getCurrentCategory(), $storeId);
        $page = $this->request->getParam('p');
        $url = $this->canonical->resolve(
            $state,
            $indexable,
            $categoryUrl,
            is_scalar($page) ? max(1, (int) $page) : 1,
            $storeId
        );
        $this->replaceCanonical($this->escaper->escapeUrl($url));
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
