<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Seo;

use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;

/**
 * Decides whether a filtered page may be indexed: only a page without filters, or a category page with exactly one
 * value of a filter the merchant marked "Let Google index pages filtered by this". Everything else is NOINDEX,FOLLOW.
 */
class RobotsResolver
{
    public const NOINDEX_FOLLOW = 'NOINDEX,FOLLOW';

    /**
     * @param FilterConfigProvider $configProvider
     */
    public function __construct(
        private readonly FilterConfigProvider $configProvider
    ) {
    }

    /**
     * Whether the page may be indexed.
     *
     * @param FilterStateInterface $state OpenFilter's active values
     * @param int $otherValues active values of filters OpenFilter does not own (core's category filter `cat`)
     * @param bool $searchPage search result pages are never indexable once filtered
     * @param int $storeId
     * @return bool
     */
    public function isIndexable(FilterStateInterface $state, int $otherValues, bool $searchPage, int $storeId): bool
    {
        $total = $state->count() + $otherValues;
        if ($total === 0) {
            return true;
        }
        if ($searchPage || $total > 1 || $otherValues > 0) {
            return false;
        }
        $key = (string) array_key_first($state->getValues());

        return ($this->configProvider->getActive($storeId)[$key] ?? null)?->isSeoIndexable() ?? false;
    }

    /**
     * Robots value to set, or null to keep the store's own setting (indexable pages).
     *
     * @param FilterStateInterface $state
     * @param int $otherValues
     * @param bool $searchPage
     * @param int $storeId
     * @return string|null
     */
    public function robots(FilterStateInterface $state, int $otherValues, bool $searchPage, int $storeId): ?string
    {
        return $this->isIndexable($state, $otherValues, $searchPage, $storeId) ? null : self::NOINDEX_FOLLOW;
    }
}
