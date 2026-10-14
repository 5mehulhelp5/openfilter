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
    public const XML_PATH_AJAX = 'openfilter/general/ajax';
    public const XML_PATH_EXPANDED_GROUPS = 'openfilter/display/expanded_groups';
    public const XML_PATH_PRICE_STEP = 'openfilter/display/price_step';
    public const XML_PATH_NOFOLLOW_THRESHOLD = 'openfilter/seo/nofollow_threshold';
    public const XML_PATH_MAX_ACTIVE_VALUES = 'openfilter/seo/max_active_values';

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

    /**
     * Filter clicks update the page without a reload (links and forms work either way).
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAjaxEnabled(?int $storeId = null): bool
    {
        return $this->reader->getBool(self::XML_PATH_AJAX, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Filter groups open by default when a filter's own setting is "automatic".
     *
     * @param int|null $storeId
     * @return int
     */
    public function getExpandedGroups(?int $storeId = null): int
    {
        return max(0, $this->reader->getInt(self::XML_PATH_EXPANDED_GROUPS, ScopeInterface::SCOPE_STORE, $storeId));
    }

    /**
     * Step of the price slider in the display currency.
     *
     * @param int|null $storeId
     * @return float
     */
    public function getPriceStep(?int $storeId = null): float
    {
        return max(0.0, $this->reader->getFloat(self::XML_PATH_PRICE_STEP, ScopeInterface::SCOPE_STORE, $storeId));
    }

    /**
     * Option links get rel="nofollow" once this many values are active.
     *
     * @param int|null $storeId
     * @return int
     */
    public function getNofollowThreshold(?int $storeId = null): int
    {
        return max(0, $this->reader->getInt(self::XML_PATH_NOFOLLOW_THRESHOLD, ScopeInterface::SCOPE_STORE, $storeId));
    }

    /**
     * More active values than this redirect to the first ones (protects crawl budget and the page cache).
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMaxActiveValues(?int $storeId = null): int
    {
        return max(0, $this->reader->getInt(self::XML_PATH_MAX_ACTIVE_VALUES, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
