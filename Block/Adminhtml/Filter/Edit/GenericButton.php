<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Block\Adminhtml\Filter\Edit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;

/**
 * Shared URL helpers of the filter form buttons.
 */
class GenericButton
{
    /**
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     */
    public function __construct(
        protected readonly UrlInterface $urlBuilder,
        protected readonly RequestInterface $request
    ) {
    }

    /**
     * @param string $route
     * @param array<string, mixed> $params
     * @return string
     */
    protected function url(string $route, array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }

    /**
     * Key of the filter being edited.
     *
     * @return string
     */
    protected function code(): string
    {
        return (string) $this->request->getParam('code');
    }
}
