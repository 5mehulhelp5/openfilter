<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Insights;

use Iranimij\Base\Serializer\SafeJson;
use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Layer\CategoryTree;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reads one insight beacon (1.2): `{"s": store code, "c": category id (0 = search), "f": {filter key: [values]},
 * "b": product count bucket}`, as the storefront script and headless clients send it. Every part is checked against
 * the store view: its code, an active category, active filters and their values (through the URL codec, so only
 * values a filter link could carry count). Anything else, or more than MAX_VALUES values, makes the beacon invalid.
 * The beacon holds nothing about the shopper, and nothing else is read.
 */
class Beacon
{
    /** Largest body read, in bytes. */
    public const MAX_BYTES = 4096;

    /** Most filter values in one beacon. */
    public const MAX_VALUES = 20;

    /** Product count buckets: unknown, none, 1–10, 11–100, more. */
    public const BUCKET_UNKNOWN = -1;
    public const BUCKET_NONE = 0;
    public const BUCKET_MAX = 3;

    /** Longest value accepted (option ids, ranges, category ids are much shorter). */
    private const MAX_VALUE_LENGTH = 64;

    /**
     * @param SafeJson $json
     * @param FilterConfigProvider $configProvider
     * @param UrlCodecInterface $codec
     * @param CategoryTree $categoryTree
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly SafeJson $json,
        private readonly FilterConfigProvider $configProvider,
        private readonly UrlCodecInterface $codec,
        private readonly CategoryTree $categoryTree,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param string $body the request body
     * @param int $storeId the store view the request reached
     * @return array{category: int, picks: array<string, string[]>, bucket: int}|null null when the beacon is not
     *     valid; picks: filter key => values in the codec's form (option ids, ranges), empty for range filters
     *     (only their use counts)
     */
    public function read(string $body, int $storeId): ?array
    {
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }
        try {
            $data = $this->json->decode($body);
        } catch (\InvalidArgumentException) {
            return null;
        }
        $filters = $data['f'] ?? [];
        if (($data['s'] ?? null) !== $this->storeManager->getStore($storeId)->getCode()
            || !is_int($data['c'] ?? null) || !$this->isCategory($data['c'], $storeId)
            || !is_int($data['b'] ?? null) || $data['b'] < self::BUCKET_UNKNOWN || $data['b'] > self::BUCKET_MAX
            || !is_array($filters)) {
            return null;
        }
        $picks = $this->picks($filters, $storeId);

        return $picks === null ? null : ['category' => $data['c'], 'picks' => $picks, 'bucket' => $data['b']];
    }

    /**
     * @param int $categoryId
     * @param int $storeId
     * @return bool 0 (search results) or an active category of the store view, its root included
     */
    private function isCategory(int $categoryId, int $storeId): bool
    {
        return $categoryId === 0 || isset($this->categoryTree->forStore($storeId)[$categoryId]);
    }

    /**
     * @param array<int|string, mixed> $filters
     * @param int $storeId
     * @return array<string, string[]>|null
     */
    private function picks(array $filters, int $storeId): ?array
    {
        $active = $this->configProvider->getActive($storeId);
        $params = array_flip($this->codec->getParameterNames($storeId));
        $query = [];
        $sizes = [];
        $total = 0;
        foreach ($filters as $key => $values) {
            $key = (string) $key;
            if (!isset($active[$key], $params[$key]) || !is_array($values) || $values === []) {
                return null;
            }
            $tokens = [];
            foreach ($values as $value) {
                if (!is_string($value) && !is_int($value)) {
                    return null;
                }
                $value = (string) $value;
                if ($value === '' || strlen($value) > self::MAX_VALUE_LENGTH || str_contains($value, ',')) {
                    return null;
                }
                $tokens[$value] = $value;
            }
            $total += count($tokens);
            if ($total > self::MAX_VALUES) {
                return null;
            }
            $query[$params[$key]] = implode(',', $tokens);
            $sizes[$key] = count($tokens);
        }
        $state = $this->codec->decode($query, $storeId);
        $picks = [];
        foreach ($sizes as $key => $size) {
            $values = $state->get($key);
            if (count($values) !== $size) {
                return null;
            }
            sort($values);
            $picks[$key] = $this->isRange($active[$key]) ? [] : $values;
        }
        ksort($picks);

        return $picks;
    }

    /**
     * @param Filter $config
     * @return bool price and decimal filters: only their use counts, not each range
     */
    private function isRange(Filter $config): bool
    {
        return $config->isBuiltin()
            ? $config->getCode() === Filter::BUILTIN_PRICE
            : $config->getInputType() === 'decimal';
    }
}
