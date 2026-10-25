<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sends a landing page's URL key (1.2) to its controller. Runs after Magento's URL rewrite router (an address another
 * page has keeps it) and reads the pages from the "openfilter" cache.
 */
class LandingRouter implements RouterInterface
{
    /**
     * @param ActionFactory $actionFactory
     * @param LandingPages $pages
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly LandingPages $pages,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
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
        $storeId = (int) $this->storeManager->getStore()->getId();
        if ($path === '' || !$this->config->isEnabled($storeId)) {
            return null;
        }
        $page = $this->pages->byUrlKey($path, $storeId);
        if ($page === null) {
            return null;
        }
        $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, $path);
        $request->setModuleName('openfilter')->setControllerName('landing')->setActionName('view');
        $request->setParam('page_id', (string) $page->getId());
        $request->setPathInfo('/openfilter/landing/view');

        return $this->actionFactory->create(Forward::class);
    }
}
