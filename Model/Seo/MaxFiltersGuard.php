<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Seo;

use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Iranimij\OpenFilter\Model\Config;

/**
 * Bots and crawlers build URLs with ever more filter values. Above the configured limit (default 5) the page is
 * answered with a temporary redirect to the first values in canonical order and marked not indexable.
 */
class MaxFiltersGuard
{
    /**
     * @param Config $config
     * @param UrlCodecInterface $codec
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlCodecInterface $codec
    ) {
    }

    /**
     * The state cut to the limit, or null when the state is within it (or the limit is off).
     *
     * @param FilterStateInterface $state
     * @param int $storeId
     * @return FilterStateInterface|null
     */
    public function truncate(FilterStateInterface $state, int $storeId): ?FilterStateInterface
    {
        $limit = $this->config->getMaxActiveValues($storeId);
        if ($limit === 0 || $state->count() <= $limit) {
            return null;
        }
        $kept = [];
        $left = $limit;
        foreach ($this->codec->encode($state, $storeId) as $param => $value) {
            if ($left === 0) {
                break;
            }
            $tokens = array_slice(explode(',', $value), 0, $left);
            $left -= count($tokens);
            $kept[$param] = implode(',', $tokens);
        }

        return $this->codec->decode($kept, $storeId);
    }
}
