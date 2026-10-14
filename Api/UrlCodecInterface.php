<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api;

/**
 * Turns query parameters into filter state and back (Spec UrlCodec). Readable, store-scoped URL names; unknown
 * names are ignored, numeric option ids still work, numbers are clamped.
 *
 * @api
 */
interface UrlCodecInterface
{
    /**
     * @param array<string, mixed> $params request query parameters
     * @param int $storeId
     * @return FilterStateInterface
     */
    public function decode(array $params, int $storeId): FilterStateInterface;

    /**
     * Canonical query parameters: filters in position order, values in option order.
     *
     * @param FilterStateInterface $state
     * @param int $storeId
     * @return array<string, string> parameter => value
     */
    public function encode(FilterStateInterface $state, int $storeId): array;

    /**
     * Parameter names of every active filter in the store view (to tell filter parameters from others).
     *
     * @param int $storeId
     * @return array<string, string> parameter => filter key
     */
    public function getParameterNames(int $storeId): array;

    /**
     * URL of a filter state on a page.
     *
     * @param FilterStateInterface $state
     * @param string $baseUrl page URL without query
     * @param array<string, string|int> $keep other parameters to keep (sort order, limit, mode)
     * @param int $storeId
     * @return string
     */
    public function urlFor(FilterStateInterface $state, string $baseUrl, array $keep, int $storeId): string;
}
