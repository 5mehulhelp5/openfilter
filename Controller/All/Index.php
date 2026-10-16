<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\All;

use Iranimij\OpenFilter\Model\AllProducts;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The All Products page (1.2): the store's root category on Magento's layered category page (its layout handles, so
 * the theme's category templates and OpenFilter's Hyvä package render it); Observer\AllProductsHeading gives it its
 * own title, heading and canonical URL.
 * Reached through the URL rewrite of its URL key; a 404 where the page is off.
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param AllProducts $allProducts
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categories
     * @param Registry $registry Magento's category page and layer read the current category from it
     * @param Resolver $layerResolver
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     */
    public function __construct(
        private readonly AllProducts $allProducts,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryRepositoryInterface $categories,
        private readonly Registry $registry,
        private readonly Resolver $layerResolver,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory
    ) {
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        if (!$this->allProducts->isEnabled($storeId)) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        $rootId = $store instanceof Store ? (int) $store->getRootCategoryId() : 0;
        $category = $this->categories->get($rootId, $storeId);
        $this->registry->register('current_category', $category, true);
        $this->layerResolver->create(Resolver::CATALOG_LAYER_CATEGORY);

        /** @var Page $page */
        $page = $this->pageFactory->create();
        $page->addHandle(['catalog_category_view', 'catalog_category_view_type_layered']);
        $page->getConfig()->getTitle()->set($this->allProducts->title($storeId));

        return $page;
    }
}
