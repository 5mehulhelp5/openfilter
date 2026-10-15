<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Layer\CategoryTree;
use Iranimij\OpenFilter\Model\Layer\Filter\CategoryTree as CategoryTreeFilter;

/**
 * Query parameters <-> filter state (Spec UrlCodec). Reads only the cached config and URL names: no SQL.
 */
class UrlCodec implements UrlCodecInterface
{
    public const TYPE_OPTIONS = 'options';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_RANGE = 'range';
    public const TYPE_RATING = 'rating';
    public const TYPE_TOGGLE = 'toggle';
    /** Category ids of the category tree (1.1); in the list display core's drill-down keeps `cat`. */
    public const TYPE_CATEGORY = 'category';

    /**
     * @var array<int, array<string, array{param: string, type: string, multiselect: bool, ranges: bool,
     *     options: array<int, string|int>, bySlug: array<string, int>}>>
     */
    private array $lookups = [];

    /**
     * @param FilterConfigProvider $configProvider
     * @param SlugProvider $slugProvider
     * @param Range $range
     * @param CategoryTree $categoryTree category ids and their tree order (category tree display)
     */
    public function __construct(
        private readonly FilterConfigProvider $configProvider,
        private readonly SlugProvider $slugProvider,
        private readonly Range $range,
        private readonly CategoryTree $categoryTree
    ) {
    }

    /**
     * @inheritDoc
     */
    public function decode(array $params, int $storeId): FilterStateInterface
    {
        $values = [];
        foreach ($this->lookup($storeId) as $key => $filter) {
            $raw = $params[$filter['param']] ?? null;
            if (is_array($raw)) {
                $raw = implode(',', array_filter($raw, 'is_scalar'));
            }
            if (!is_scalar($raw)) {
                continue;
            }
            $tokens = array_values(array_filter(
                array_map(static fn (string $t): string => strtolower(trim($t)), explode(',', (string) $raw)),
                static fn (string $t): bool => $t !== ''
            ));
            $decoded = $this->decodeTokens($filter, $tokens);
            if ($decoded !== []) {
                $values[$key] = $decoded;
            }
        }

        return new FilterState($values);
    }

    /**
     * @inheritDoc
     */
    public function encode(FilterStateInterface $state, int $storeId): array
    {
        $params = [];
        foreach ($this->lookup($storeId) as $key => $filter) {
            $values = $state->get($key);
            if ($values === []) {
                continue;
            }
            if ($filter['type'] === self::TYPE_OPTIONS) {
                $position = array_flip(array_keys($filter['options']));
                usort($values, static fn (string $a, string $b): int =>
                    [$position[(int) $a] ?? PHP_INT_MAX, (int) $a] <=> [$position[(int) $b] ?? PHP_INT_MAX, (int) $b]);
                $values = array_map(static fn (string $id): string => $filter['options'][(int) $id] ?? $id, $values);
            }
            if ($filter['type'] === self::TYPE_CATEGORY) {
                usort($values, static fn (string $a, string $b): int =>
                    ($filter['options'][(int) $a] ?? PHP_INT_MAX) <=> ($filter['options'][(int) $b] ?? PHP_INT_MAX));
            }
            if ($filter['type'] === self::TYPE_RANGE && $filter['ranges']) {
                $values = $this->sortRanges($values);
            }
            $params[$filter['param']] = implode(',', $values);
        }

        return $params;
    }

    /**
     * @inheritDoc
     */
    public function getParameterNames(int $storeId): array
    {
        $names = [];
        foreach ($this->lookup($storeId) as $key => $filter) {
            $names[$filter['param']] = $key;
        }

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function urlFor(FilterStateInterface $state, string $baseUrl, array $keep, int $storeId): string
    {
        $params = $this->encode($state, $storeId);
        foreach (array_diff_key($keep, $this->getParameterNames($storeId)) as $name => $value) {
            $params[(string) $name] = (string) $value;
        }
        if ($params === []) {
            return $baseUrl;
        }
        $query = [];
        foreach ($params as $name => $value) {
            $query[] = rawurlencode($name) . '=' . str_replace('%2C', ',', rawurlencode($value));
        }

        return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . implode('&', $query);
    }

    /**
     * Forgets the per-store lookups (after a config or URL name change in the same process).
     *
     * @return void
     */
    public function reset(): void
    {
        $this->lookups = [];
    }

    /**
     * @param array{param: string, type: string, multiselect: bool, ranges: bool, options: array<int, string>,
     *     bySlug: array<string, int>} $filter
     * @param string[] $tokens
     * @return string[]
     */
    private function decodeTokens(array $filter, array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }
        switch ($filter['type']) {
            case self::TYPE_OPTIONS:
                $ids = [];
                foreach ($tokens as $token) {
                    $id = $filter['bySlug'][$token]
                        ?? (ctype_digit($token) && isset($filter['options'][(int) $token]) ? (int) $token : null);
                    if ($id !== null) {
                        $ids[(string) $id] = (string) $id;
                    }
                }
                $ids = array_values($ids);

                return $filter['multiselect'] ? $ids : array_slice($ids, 0, 1);
            case self::TYPE_BOOLEAN:
                return in_array($tokens[0], ['0', '1'], true) ? [$tokens[0]] : [];
            case self::TYPE_RANGE:
                if (!$filter['ranges']) {
                    $range = $this->range->normalise($tokens[0]);

                    return $range === null ? [] : [$range];
                }
                $ranges = $this->sortRanges(array_values(array_unique(array_filter(
                    array_map(fn (string $token): ?string => $this->range->normalise($token), $tokens)
                ))));

                return $filter['multiselect'] ? $ranges : array_slice($ranges, 0, 1);
            case self::TYPE_CATEGORY:
                $ids = [];
                foreach ($tokens as $token) {
                    if (ctype_digit($token) && isset($filter['options'][(int) $token])) {
                        $ids[(int) $token] = $filter['options'][(int) $token];
                    }
                }
                asort($ids);
                $ids = array_map('strval', array_keys($ids));

                return $filter['multiselect'] ? $ids : array_slice($ids, 0, 1);
            case self::TYPE_RATING:
                return ctype_digit($tokens[0]) ? [(string) min(5, max(1, (int) $tokens[0]))] : [];
            default:
                return $tokens[0] === '1' ? ['1'] : [];
        }
    }

    /**
     * Parameter, type and option names of every active filter, in position order.
     *
     * @param int $storeId
     * @return array<string, array{param: string, type: string, multiselect: bool, ranges: bool,
     *     options: array<int, string|int>, bySlug: array<string, int>}>
     */
    private function lookup(int $storeId): array
    {
        if (isset($this->lookups[$storeId])) {
            return $this->lookups[$storeId];
        }
        $slugs = $this->slugProvider->forStore($storeId);
        $lookup = [];
        foreach ($this->configProvider->getActive($storeId) as $key => $config) {
            $type = $this->typeOf($config);
            if ($type === null) {
                continue;
            }
            $attributeId = (int) $config->getAttributeId();
            $options = match ($type) {
                self::TYPE_OPTIONS => $slugs['options'][$attributeId] ?? [],
                self::TYPE_CATEGORY => $this->categoryTree->order($storeId),
                default => [],
            };
            $lookup[$key] = [
                'param' => match (true) {
                    $type === self::TYPE_CATEGORY => CategoryTreeFilter::REQUEST_VAR,
                    $config->isBuiltin() => $config->getCode(),
                    default => $slugs['attributes'][$attributeId] ?? $config->getCode(),
                },
                'type' => $type,
                'multiselect' => $config->isMultiselect(),
                'ranges' => $config->getDisplayMode() === Filter::DISPLAY_RANGES,
                'options' => $options,
                'bySlug' => $type === self::TYPE_OPTIONS ? array_flip($options) : [],
            ];
        }

        return $this->lookups[$storeId] = $lookup;
    }

    /**
     * Ranges of the "ranges" display type in canonical order: by lower bound (open first), then upper bound.
     *
     * @param string[] $values normalised ranges
     * @return string[]
     */
    private function sortRanges(array $values): array
    {
        $key = function (string $value): array {
            $range = $this->range->parse($value) ?? [null, null];

            return [$range[0] ?? -1.0, $range[1] ?? PHP_FLOAT_MAX];
        };
        usort($values, static fn (string $a, string $b): int => $key($a) <=> $key($b));

        return $values;
    }

    /**
     * @param Filter $config
     * @return string|null null for filters the codec does not handle (category list: core's `cat` drill-down)
     */
    private function typeOf(Filter $config): ?string
    {
        if ($config->isBuiltin()) {
            return match ($config->getCode()) {
                Filter::BUILTIN_PRICE => self::TYPE_RANGE,
                Filter::BUILTIN_RATING => self::TYPE_RATING,
                Filter::BUILTIN_CATEGORY => $config->getDisplayMode() === Filter::DISPLAY_TREE ? self::TYPE_CATEGORY : null,
                default => self::TYPE_TOGGLE,
            };
        }

        return match ($config->getInputType()) {
            'decimal' => self::TYPE_RANGE,
            'boolean' => self::TYPE_BOOLEAN,
            default => self::TYPE_OPTIONS,
        };
    }
}
