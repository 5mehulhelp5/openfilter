<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Model\Index\Fields;

/**
 * Engine clauses and count aggregations of the built-in filters (rating, in stock, on sale, new). They join the
 * facet filters, so both count strategies treat them like any option filter: each count ignores its own filter.
 */
class BuiltinClauses
{
    public const BUCKET_PREFIX = 'of_builtin_';
    /** Pseudo field of a built-in facet filter (never an index field). */
    public const FIELD_PREFIX = 'of_builtin:';
    /** "N stars and up" options. */
    public const STARS = [4, 3, 2, 1];

    /**
     * @param Fields $fields
     */
    public function __construct(
        private readonly Fields $fields
    ) {
    }

    /**
     * Built-ins of a request.
     *
     * @param string[] $codes active built-in codes (rating, stock, sale, new)
     * @param FilterStateInterface $state
     * @param int $groupId
     * @param int $websiteId
     * @param string $now store-local "Y-m-d H:i:s"
     * @return array<string, array{bucket: string, field: string, aggregation: array<string, mixed>,
     *     clause: array<string, mixed>|null}>
     */
    public function build(array $codes, FilterStateInterface $state, int $groupId, int $websiteId, string $now): array
    {
        $built = [];
        foreach ($codes as $code) {
            $value = $state->get($code)[0] ?? null;
            $toggle = $this->toggleClause($code, $groupId, $websiteId, $now);
            if ($code === Filter::BUILTIN_RATING) {
                $aggregation = ['range' => ['field' => Fields::RATING, 'keyed' => true, 'ranges' => array_map(
                    static fn (int $stars): array => ['key' => (string) $stars, 'from' => $stars * 20],
                    self::STARS
                )]];
                $clause = $value === null ? null : ['range' => [Fields::RATING => ['gte' => (int) $value * 20]]];
            } elseif ($toggle !== null) {
                $aggregation = ['filter' => $toggle];
                $clause = $value === '1' ? $toggle : null;
            } else {
                continue;
            }
            $built[$code] = [
                'bucket' => self::BUCKET_PREFIX . $code,
                'field' => self::FIELD_PREFIX . $code,
                'aggregation' => $aggregation,
                'clause' => $clause,
            ];
        }

        return $built;
    }

    /**
     * Counts from a raw engine response (after the strategy unwrapped its aggregations).
     *
     * @param array<string, mixed> $aggregations
     * @return array<string, int|array<int, int>> code => count, rating => stars => count
     */
    public function counts(array $aggregations): array
    {
        $counts = [];
        foreach ($aggregations as $name => $aggregation) {
            if (!is_string($name) || !str_starts_with($name, self::BUCKET_PREFIX) || !is_array($aggregation)) {
                continue;
            }
            $code = substr($name, strlen(self::BUCKET_PREFIX));
            if (isset($aggregation['buckets']) && is_array($aggregation['buckets'])) {
                $stars = [];
                foreach ($aggregation['buckets'] as $key => $bucket) {
                    $stars[(int) ($bucket['key'] ?? $key)] = (int) ($bucket['doc_count'] ?? 0);
                }
                $counts[$code] = $stars;
            } else {
                $counts[$code] = (int) ($aggregation['doc_count'] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * @param string $code
     * @param int $groupId
     * @param int $websiteId
     * @param string $now
     * @return array<string, mixed>|null
     */
    private function toggleClause(string $code, int $groupId, int $websiteId, string $now): ?array
    {
        return match ($code) {
            Filter::BUILTIN_STOCK => ['term' => [Fields::IN_STOCK => 1]],
            Filter::BUILTIN_SALE => ['term' => [$this->fields->onSale($groupId, $websiteId) => 1]],
            Filter::BUILTIN_NEW => ['bool' => ['must' => [
                ['range' => [Fields::NEWS_FROM => ['lte' => $now, 'format' => Fields::ENGINE_DATE_FORMAT]]],
                ['range' => [Fields::NEWS_TO => ['gte' => $now, 'format' => Fields::ENGINE_DATE_FORMAT]]],
            ]]],
            default => null,
        };
    }
}
