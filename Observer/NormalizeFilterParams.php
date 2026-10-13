<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Observer;

use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Seo\MaxFiltersGuard;
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
 * URLs, better cache hit rate), and URLs with more values than allowed with a 302 to the first ones (not indexed).
 * Both redirects go straight to the final canonical URL (one hop). GET and HEAD only.
 */
class NormalizeFilterParams implements ObserverInterface
{
    /**
     * @param ParamNormalizer $normalizer
     * @param LayerContext $context
     * @param ActionFlag $actionFlag
     * @param ResponseInterface $response
     * @param MaxFiltersGuard $guard
     */
    public function __construct(
        private readonly ParamNormalizer $normalizer,
        private readonly LayerContext $context,
        private readonly ActionFlag $actionFlag,
        private readonly ResponseInterface $response,
        private readonly MaxFiltersGuard $guard
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
        if (!$this->response instanceof HttpResponse) {
            return;
        }
        $truncated = $this->guard->truncate($result['state'], $this->context->storeId());
        if ($truncated !== null) {
            $override = array_fill_keys($result['drop'] ?? [], null) + ['p' => null];
            $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, '1');
            $this->response->setRedirect($this->context->urlFor($truncated, $override), 302);
            $this->response->setHeader('X-Robots-Tag', 'noindex', true);
            return;
        }
        if (!$result['redirect']) {
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
