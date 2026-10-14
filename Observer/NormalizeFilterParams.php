<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Observer;

use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Url\ParamNormalizer;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Answers non-canonical filter URLs on category and search pages with a 301 to the canonical URL (fewer duplicate
 * URLs, better cache hit rate). GET and HEAD only.
 */
class NormalizeFilterParams implements ObserverInterface
{
    /**
     * @param ParamNormalizer $normalizer
     * @param LayerContext $context
     * @param ActionFlag $actionFlag
     * @param ResponseInterface $response
     */
    public function __construct(
        private readonly ParamNormalizer $normalizer,
        private readonly LayerContext $context,
        private readonly ActionFlag $actionFlag,
        private readonly ResponseInterface $response
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $request = $observer->getEvent()->getData('request');
        if (!$request instanceof HttpRequest || !($request->isGet() || $request->isHead()) || !$this->context->isActive()) {
            return;
        }
        $result = $this->normalizer->check($request->getQuery()->toArray(), $this->context->storeId());
        if (!$result['redirect'] || !$this->response instanceof HttpResponse) {
            return;
        }
        $page = $request->getQuery('p');
        $override = array_fill_keys($result['drop'] ?? [], null);
        if (!$result['changed'] && is_scalar($page)) {
            $override['p'] = (string) $page;
        }
        $url = $this->context->urlFor($result['state'], $override);
        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, '1');
        $this->response->setRedirect($url, 301);
    }
}
