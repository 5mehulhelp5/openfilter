<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Search;

use Iranimij\OpenFilter\Model\Search\QueryState;
use Magento\Elasticsearch\SearchAdapter\Aggregation\Builder\Dynamic;
use Magento\Framework\Search\Dynamic\DataProviderInterface;
use Magento\Framework\Search\Request\BucketInterface;

/**
 * Price and decimal buckets of layered requests: core's interval algorithms send one or two extra search requests;
 * OpenFilter's sliders only need min, max and count, which the main request already returns (extended_stats, with
 * the attribute's own range excluded by the count strategy). Result: one bucket value "stats".
 */
class DynamicBucketPlugin
{
    public const STATS = 'stats';

    /**
     * @param QueryState $state
     */
    public function __construct(
        private readonly QueryState $state
    ) {
    }

    /**
     * @param Dynamic $subject
     * @param callable $proceed
     * @param BucketInterface $bucket
     * @param array<string, mixed> $dimensions
     * @param array<string, mixed> $queryResult
     * @param DataProviderInterface $dataProvider
     * @return array<string, array<string, mixed>>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundBuild(
        Dynamic $subject,
        callable $proceed,
        BucketInterface $bucket,
        array $dimensions,
        array $queryResult,
        DataProviderInterface $dataProvider
    ) {
        if (!$this->state->isHandling()) {
            return $proceed($bucket, $dimensions, $queryResult, $dataProvider);
        }
        $stats = $queryResult['aggregations'][$bucket->getName()] ?? null;
        if (!is_array($stats) || (int) ($stats['count'] ?? 0) === 0 || !isset($stats['min'], $stats['max'])) {
            return [];
        }

        return [self::STATS => [
            'value' => self::STATS,
            'min' => (float) $stats['min'],
            'max' => (float) $stats['max'],
            'count' => (int) $stats['count'],
        ]];
    }
}
