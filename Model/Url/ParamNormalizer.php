<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Api\UrlCodecInterface;

/**
 * Decides whether a layered page's filter parameters are canonical (Spec UrlCodec, "Normalisation"): same filters
 * in position order, values in option order, URL names instead of ids, lower case, no duplicates or junk.
 * Other parameters (sort, limit, mode, tracking) may sit anywhere.
 */
class ParamNormalizer
{
    /**
     * @param UrlCodecInterface $codec
     */
    public function __construct(
        private readonly UrlCodecInterface $codec
    ) {
    }

    /**
     * @param array<string, mixed> $query request query parameters in request order (decoded)
     * @param int $storeId
     * @return array{redirect: bool, state: FilterStateInterface, changed: bool} changed: the filter state differs
     *     from what the URL asked for (unknown or duplicate values dropped), so the page number resets
     */
    public function check(array $query, int $storeId): array
    {
        $names = $this->codec->getParameterNames($storeId);
        $actual = [];
        $tokens = 0;
        foreach ($query as $name => $value) {
            if (!isset($names[$name])) {
                continue;
            }
            $actual[$name] = is_scalar($value) ? (string) $value : null;
            $raw = is_array($value) ? $value : explode(',', (string) $value);
            $tokens += count(array_filter($raw, static fn ($t): bool => is_scalar($t) && trim((string) $t) !== ''));
        }
        $state = $this->codec->decode($query, $storeId);
        if ($actual === []) {
            return ['redirect' => false, 'state' => $state, 'changed' => false];
        }
        $canonical = $this->codec->encode($state, $storeId);

        return [
            'redirect' => $actual !== $canonical,
            'state' => $state,
            'changed' => $state->count() !== $tokens,
        ];
    }
}
