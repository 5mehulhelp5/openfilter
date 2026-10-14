<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenFilter\Model\Coexistence\Detector;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * OpenFilter settings (section `openfilter` under the `iranimij` tab), read through the shared base module.
 */
class Config
{
    public const XML_PATH_ENABLED = 'openfilter/general/enabled';
    public const XML_PATH_DEBUG = 'openfilter/general/debug';

    /**
     * @param TypedReader $reader
     * @param Detector|null $detector another layered navigation (ElasticSuite, Live Search) switches OpenFilter off
     */
    public function __construct(
        private readonly TypedReader $reader,
        private readonly ?Detector $detector = null
    ) {
    }

    /**
     * Whether OpenFilter replaces the layered navigation in the given store view (current store when null): enabled
     * in the configuration and no other layered navigation active.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->reader->getBool(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)
            && $this->detector?->getActiveReplacement() === null;
    }

    /**
     * Debug mode: data-of-* attributes in the markup and the facet count strategy in var/log/openfilter.log.
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->reader->getBool(self::XML_PATH_DEBUG, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null);
    }
}
