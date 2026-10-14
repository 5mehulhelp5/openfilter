<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Model\Doctor\Check;
use Iranimij\OpenFilter\Model\Doctor\FilterDiagnosis;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * "Why is this filter not showing?" for one store view and, optionally, one category page (JSON).
 */
class Diagnose extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param JsonFactory $jsonFactory
     * @param FilterDiagnosis $diagnosis
     * @param StoreManagerInterface $storeManager
     * @param UrlFinderInterface $urlFinder
     */
    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FilterDiagnosis $diagnosis,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlFinderInterface $urlFinder
    ) {
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $request = $this->getRequest();
        $category = (int) $request->getParam('category');
        $checks = $this->diagnosis->diagnose(
            (string) $request->getParam('code'),
            max(1, (int) $request->getParam('store')),
            $category > 0 ? $category : null
        );

        $store = max(1, (int) $request->getParam('store'));

        return $this->jsonFactory->create()->setData([
            'checks' => array_map(static fn (Check $check): array => $check->toArray(), $checks),
            'preview' => $category > 0 ? $this->preview($category, $store) : null,
        ]);
    }

    /**
     * The category page on the storefront of the store view, to look at the filter there (1.1).
     *
     * @param int $categoryId
     * @param int $storeId
     * @return string|null
     */
    private function preview(int $categoryId, int $storeId): ?string
    {
        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::ENTITY_TYPE => 'category',
            UrlRewrite::ENTITY_ID => $categoryId,
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);
        if ($rewrite === null) {
            return null;
        }

        $store = $this->storeManager->getStore($storeId);
        if (!$store instanceof Store) {
            return null;
        }

        return rtrim((string) $store->getBaseUrl(), '/') . '/' . ltrim($rewrite->getRequestPath(), '/');
    }
}
