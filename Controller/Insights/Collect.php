<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Insights;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Insights\Beacon;
use Iranimij\OpenFilter\Model\Insights\Recorder;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * POST /openfilter/insights/collect (1.2): the insight beacon of filter pages and headless clients. Answers 204 for a
 * counted beacon, 400 for one that does not match the store view's filters, 404 where insights are off. Reads only
 * the body (no cookies, headers or addresses) and needs no form key: the beacon changes nothing but anonymous
 * counts, and a page in the full page cache has no form key to send.
 */
class Collect implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @param RequestInterface $request
     * @param RawFactory $rawFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param Beacon $beacon
     * @param Recorder $recorder
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly Beacon $beacon,
        private readonly Recorder $recorder
    ) {
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->rawFactory->create();
        $result->setHeader('Cache-Control', 'no-store', true);
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId) || !$this->config->isInsightsEnabled($storeId)) {
            return $this->status($result, 404);
        }
        $body = $this->request instanceof HttpRequest ? (string) $this->request->getContent() : '';
        $beacon = $this->beacon->read($body, $storeId);
        if ($beacon === null) {
            return $this->status($result, 400);
        }
        $this->recorder->record($beacon, $storeId);

        return $this->status($result, 204);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param Raw $result
     * @param int $code
     * @return Raw
     */
    private function status(Raw $result, int $code): Raw
    {
        $result->setHttpResponseCode($code);
        $result->setContents('');

        return $result;
    }
}
