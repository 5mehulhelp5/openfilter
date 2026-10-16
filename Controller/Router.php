<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller;

use Iranimij\OpenFilter\Model\AllProducts;
use Iranimij\OpenFilter\Model\Config;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\UrlInterface;

/**
 * Sends the All Products page's URL key (1.2) to its controller. Runs after Magento's URL rewrite router, so a page
 * that already has the address keeps it; sets the same request alias that router sets, so filter links keep the
 * URL key.
 */
class Router implements RouterInterface
{
    /**
     * @param ActionFactory $actionFactory
     * @param AllProducts $allProducts
     * @param Config $config
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly AllProducts $allProducts,
        private readonly Config $config
    ) {
    }

    /**
     * @param RequestInterface $request
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        if (!$request instanceof HttpRequest || $request->getModuleName()) {
            return null;
        }
        $path = trim((string) $request->getPathInfo(), '/');
        if ($path === '' || !$this->allProducts->isEnabled() || !$this->config->isEnabled()
            || $path !== $this->allProducts->urlKey()
        ) {
            return null;
        }
        $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, $path);
        $request->setModuleName('openfilter')->setControllerName('all')->setActionName('index');
        $request->setPathInfo('/openfilter/all/index');

        return $this->actionFactory->create(Forward::class);
    }
}
