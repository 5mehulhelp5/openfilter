<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Seo;

use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Api\UrlCodecInterface;

/**
 * Canonical URL of a filtered category page: an indexable page points to itself (canonical filter parameters and the
 * page number; sort, limit and view mode dropped), every other filtered page points to the category URL.
 */
class CanonicalResolver
{
    /**
     * @param UrlCodecInterface $codec
     */
    public function __construct(
        private readonly UrlCodecInterface $codec
    ) {
    }

    /**
     * @param FilterStateInterface $state
     * @param bool $indexable
     * @param string $categoryUrl the category's URL without a query
     * @param int $page current page number (1 when absent)
     * @param int $storeId
     * @return string
     */
    public function resolve(
        FilterStateInterface $state,
        bool $indexable,
        string $categoryUrl,
        int $page,
        int $storeId
    ): string {
        if (!$indexable) {
            return $categoryUrl;
        }

        return $this->codec->urlFor($state, $categoryUrl, $page > 1 ? ['p' => (string) $page] : [], $storeId);
    }
}
