<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Setup\Patch\Data;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Turns the "OpenFilter" cache type on. Magento enables cache types only on a fresh install, and with the type off
 * every storefront request would read the filter settings from the database.
 */
class EnableCacheType implements DataPatchInterface
{
    /**
     * @param CacheManager $cacheManager
     */
    public function __construct(
        private readonly CacheManager $cacheManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        if (in_array(CacheType::TYPE_IDENTIFIER, $this->cacheManager->getAvailableTypes(), true)) {
            $this->cacheManager->setEnabled([CacheType::TYPE_IDENTIFIER], true);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
