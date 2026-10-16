<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Landing;

use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Iranimij\OpenFilter\Model\Landing\CurrentPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * A landing page (1.2): its category on Magento's layered category page with its filters applied (as request values,
 * so the layer, the counts and the chips work as on a filtered category page); Observer\LandingHead sets its title,
 * meta tags, heading, robots and canonical URL, layout openfilter_landing_view its content.
 */
class View implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param LandingPages $pages
     * @param CurrentPage $current
     * @param UrlCodecInterface $codec
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categories
     * @param Registry $registry Magento's category page and layer read the current category from it
     * @param Resolver $layerResolver
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly LandingPages $pages,
        private readonly CurrentPage $current,
        private readonly UrlCodecInterface $codec,
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
        if (!$this->request instanceof HttpRequest) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        $storeId = (int) $this->storeManager->getStore()->getId();
        $page = $this->pages->byUrlKey(
            (string) $this->request->getAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS),
            $storeId
        );
        if ($page === null || $page->getId() !== (int) $this->request->getParam('page_id')) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        try {
            $category = $this->categories->get($page->getCategoryId(), $storeId);
        } catch (NoSuchEntityException $e) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        if (!$category->getIsActive()) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        $this->current->set($page);
        foreach ($this->codec->encode(new FilterState($page->getFilters()), $storeId) as $name => $value) {
            $this->request->setQueryValue($name, $value);
        }
        $this->registry->register('current_category', $category, true);
        $this->layerResolver->create(Resolver::CATALOG_LAYER_CATEGORY);

        /** @var Page $result */
        $result = $this->pageFactory->create();
        $result->addHandle(['catalog_category_view', 'catalog_category_view_type_layered']);
        $result->getConfig()->getTitle()->set($page->getMetaTitle());

        return $result;
    }
}
