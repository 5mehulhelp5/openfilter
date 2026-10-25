<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Insights;

use Iranimij\OpenFilter\Model\ResourceModel\InsightStorage;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Adds one valid beacon to the day's counts (1.2): one "view", one "filter" row per filter used, one "value" row per
 * value, and for a page without products the filter combination ("zero"). Counts only: one upsert per beacon, no row
 * per shopper or request.
 */
class Recorder
{
    public const KIND_VIEW = 'view';
    public const KIND_FILTER = 'filter';
    public const KIND_VALUE = 'value';
    public const KIND_ZERO = 'zero';

    /** Longest combination kept (the column's length). */
    private const MAX_COMBINATION = 255;

    /**
     * @param InsightStorage $storage
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly InsightStorage $storage,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @param array{category: int, picks: array<string, string[]>, bucket: int} $beacon from Beacon::read()
     * @param int $storeId
     * @return void
     */
    public function record(array $beacon, int $storeId): void
    {
        $this->storage->add($this->rows($beacon, $storeId, $this->dateTime->gmtDate('Y-m-d')));
    }

    /**
     * @param array{category: int, picks: array<string, string[]>, bucket: int} $beacon
     * @param int $storeId
     * @param string $day Y-m-d
     * @return array<int, array{day: string, store_id: int, category_id: int, kind: string, filter_key: string,
     *     value: string, hits: int}>
     */
    public function rows(array $beacon, int $storeId, string $day): array
    {
        $row = static fn (string $kind, string $key = '', string $value = ''): array => [
            'day' => $day,
            'store_id' => $storeId,
            'category_id' => $beacon['category'],
            'kind' => $kind,
            'filter_key' => $key,
            'value' => $value,
            'hits' => 1,
        ];
        $rows = [$row(self::KIND_VIEW)];
        $combination = [];
        foreach ($beacon['picks'] as $key => $values) {
            $rows[] = $row(self::KIND_FILTER, $key);
            foreach ($values as $value) {
                $rows[] = $row(self::KIND_VALUE, $key, $value);
            }
            $combination[] = $values === [] ? $key : $key . '=' . implode(',', $values);
        }
        $zero = implode(';', $combination);
        if ($beacon['bucket'] === Beacon::BUCKET_NONE && $zero !== '' && strlen($zero) <= self::MAX_COMBINATION) {
            $rows[] = $row(self::KIND_ZERO, '', $zero);
        }

        return $rows;
    }
}
