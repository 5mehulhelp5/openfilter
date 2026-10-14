<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Cache;

use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Iranimij\OpenFilter\Model\Url\UrlCodec;

/**
 * Drops OpenFilter's cached settings and URL names after a rolled-back transaction: checks inside the transaction
 * (URL name collisions read the current names) refill the cache with values that were never committed.
 */
class AfterRollback
{
    /**
     * @param Type $cache
     * @param FilterConfigProvider $configProvider
     * @param SlugProvider $slugProvider
     * @param UrlCodec $codec
     */
    public function __construct(
        private readonly Type $cache,
        private readonly FilterConfigProvider $configProvider,
        private readonly SlugProvider $slugProvider,
        private readonly UrlCodec $codec
    ) {
    }

    /**
     * @return void
     */
    public function forget(): void
    {
        $this->cache->clean();
        $this->configProvider->reset();
        $this->slugProvider->reset();
        $this->codec->reset();
    }
}
