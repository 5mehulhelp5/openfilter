<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Migration\OldUrlResolver;
use Magento\Framework\App\Action\Redirect;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Route\ConfigInterface as RouteConfig;
use Magento\Framework\App\RouterInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Old filter URLs of another layered navigation module (1.2, opt-in): runs after Magento's URL rewrites, the landing
 * pages and the All Products page, so it sees only addresses nothing else has, and answers an old filter URL with a
 * permanent redirect to the OpenFilter URL of the same category and filters. Addresses that start with a route's
 * front name (checkout/, customer/ …) are left to Magento without a lookup.
 */
class MigrationRouter implements RouterInterface
{
    /**
     * @param ActionFactory $actionFactory
     * @param ResponseInterface $response
     * @param OldUrlResolver $resolver
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param RouteConfig $routeConfig
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly ResponseInterface $response,
        private readonly OldUrlResolver $resolver,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly RouteConfig $routeConfig
    ) {
    }

    /**
     * @param RequestInterface $request
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        if (!$request instanceof HttpRequest || $request->getModuleName() || !$request->isGet()) {
            return null;
        }
        $storeId = (int) $this->storeManager->getStore()->getId();
        $path = trim((string) $request->getPathInfo(), '/');
        if (!str_contains($path, '/') || !$this->config->isEnabled($storeId) || !$this->resolver->isEnabled($storeId)
            || $this->routeConfig->getRouteByFrontName(strtok($path, '/') ?: '', 'frontend')
        ) {
            return null;
        }
        $query = array_filter((array) $request->getQuery()->toArray(), 'is_scalar');
        $url = $this->resolver->resolve($path, array_map('strval', $query), $storeId);
        if ($url === null || !$this->response instanceof HttpResponse) {
            return null;
        }
        $this->response->setRedirect($url, 301);
        $request->setDispatched(true);

        return $this->actionFactory->create(Redirect::class);
    }
}
