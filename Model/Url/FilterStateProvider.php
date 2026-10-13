<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The filter state of the current request, decoded once.
 */
class FilterStateProvider
{
    private ?FilterStateInterface $state = null;

    /**
     * @param RequestInterface $request
     * @param UrlCodecInterface $codec
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlCodecInterface $codec,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return FilterStateInterface
     */
    public function get(): FilterStateInterface
    {
        return $this->state ??= $this->codec->decode(
            (array) $this->request->getParams(),
            (int) $this->storeManager->getStore()->getId()
        );
    }

    /**
     * Replaces the state (tests, sub-requests).
     *
     * @param FilterStateInterface|null $state null decodes the request again
     * @return void
     */
    public function set(?FilterStateInterface $state): void
    {
        $this->state = $state;
    }
}
