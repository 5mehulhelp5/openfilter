<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Insights;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Layer\CategoryTree;
use Iranimij\OpenFilter\Model\ResourceModel\InsightStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * What the insight counts of one store view say (1.2, Catalog › OpenFilter › Insights): filters by use, the most
 * used values, filters nobody used, filter combinations that found no products, filters worth letting search engines
 * index, and a filter order by use for "Sort filters". Built from the daily counts only.
 */
class Report
{
    /** Below this many page views the page says there is not enough data yet. */
    public const MIN_VIEWS = 50;

    /** A filter is worth indexing when used on at least this share of pages, and at least MIN_INDEXABLE_USES times. */
    public const INDEXABLE_SHARE = 0.1;
    public const MIN_INDEXABLE_USES = 20;

    /** Rows in the "most used values" and "no products" lists. */
    public const LIST_SIZE = 20;

    /** @var array<int, array<int, string>> attribute id => option id => label */
    private array $optionLabels = [];

    /**
     * @param InsightStorage $storage
     * @param FilterConfigProvider $configProvider
     * @param Defaults $defaults
     * @param SlugStorage $slugStorage
     * @param CategoryTree $categoryTree
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly InsightStorage $storage,
        private readonly FilterConfigProvider $configProvider,
        private readonly Defaults $defaults,
        private readonly SlugStorage $slugStorage,
        private readonly CategoryTree $categoryTree,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @param int $storeId
     * @param int $days the last days, today included
     * @return array{views: int, enough: bool,
     *     filters: array<int, array{key: string, label: string, hits: int, share: float}>,
     *     values: array<int, array{filter: string, label: string, hits: int}>,
     *     unused: string[],
     *     zero: array<int, array{label: string, hits: int}>,
     *     indexable: array<int, array{key: string, label: string, hits: int, share: float}>,
     *     order: string[]}
     */
    public function build(int $storeId, int $days): array
    {
        $from = $this->from($days);
        $views = array_sum(array_column($this->storage->sums(Recorder::KIND_VIEW, $from, $storeId), 'hits'));
        $all = $this->configProvider->getForStore($storeId);
        $active = array_filter($all, static fn (FilterConfig $config): bool => $config->isActive());
        $uses = array_column($this->storage->sums(Recorder::KIND_FILTER, $from, $storeId), 'hits', 'filter_key');

        $filters = [];
        foreach ($active as $key => $config) {
            $hits = (int) ($uses[$key] ?? 0);
            $filters[] = ['key' => (string) $key, 'label' => $this->label($config), 'hits' => $hits, 'share' => $views > 0 ? (float) $hits / $views : 0.0];
        }
        usort($filters, static fn (array $a, array $b): int => $b['hits'] <=> $a['hits']);

        $indexable = array_values(array_filter($filters, fn (array $row): bool => $this->isOptionFilter($active[$row['key']])
            && !$active[$row['key']]->isSeoIndexable()
            && $row['hits'] >= self::MIN_INDEXABLE_USES && $row['share'] >= self::INDEXABLE_SHARE));

        return [
            'views' => $views,
            'enough' => $views >= self::MIN_VIEWS,
            'filters' => $filters,
            'values' => $this->values($from, $storeId, $active),
            'unused' => array_values(array_map(
                static fn (array $row): string => $row['label'],
                array_filter($filters, static fn (array $row): bool => $row['hits'] === 0)
            )),
            'zero' => $this->zero($from, $storeId, $active),
            'indexable' => $indexable,
            'order' => $this->order($all, $uses),
        ];
    }

    /**
     * The filter order by use, for "Sort filters" (see order()).
     *
     * @param int $storeId
     * @param int $days
     * @return string[] filter keys
     */
    public function suggestedOrder(int $storeId, int $days): array
    {
        $uses = array_column($this->storage->sums(Recorder::KIND_FILTER, $this->from($days), $storeId), 'hits', 'filter_key');

        return $this->order($this->configProvider->getForStore($storeId), $uses);
    }

    /**
     * @param int $days the last days, today included
     * @return string first day, Y-m-d
     */
    private function from(int $days): string
    {
        return $this->dateTime->gmtDate('Y-m-d', $this->dateTime->gmtTimestamp() - (max(1, $days) - 1) * 86400);
    }

    /**
     * Every filter of the store view: the active ones by use (most first, ties in their current order), then the
     * hidden ones in their current order.
     *
     * @param array<string, FilterConfig> $all in position order
     * @param array<string, int|string> $uses filter key => hits
     * @return string[]
     */
    private function order(array $all, array $uses): array
    {
        $keys = array_map('strval', array_keys($all));
        $place = array_flip($keys);
        usort($keys, static fn (string $a, string $b): int => [
            !$all[$a]->isActive(), $all[$a]->isActive() ? -(int) ($uses[$a] ?? 0) : 0, $place[$a],
        ] <=> [
            !$all[$b]->isActive(), $all[$b]->isActive() ? -(int) ($uses[$b] ?? 0) : 0, $place[$b],
        ]);

        return $keys;
    }

    /**
     * @param string $from
     * @param int $storeId
     * @param array<string, FilterConfig> $active
     * @return array<int, array{filter: string, label: string, hits: int}>
     */
    private function values(string $from, int $storeId, array $active): array
    {
        $values = [];
        foreach ($this->storage->sums(Recorder::KIND_VALUE, $from, $storeId) as $row) {
            $config = $active[$row['filter_key']] ?? null;
            if ($config === null) {
                continue;
            }
            $values[] = [
                'filter' => $this->label($config),
                'label' => $this->valueLabel($config, $row['value'], $storeId),
                'hits' => $row['hits'],
            ];
            if (count($values) === self::LIST_SIZE) {
                break;
            }
        }

        return $values;
    }

    /**
     * @param string $from
     * @param int $storeId
     * @param array<string, FilterConfig> $active
     * @return array<int, array{label: string, hits: int}>
     */
    private function zero(string $from, int $storeId, array $active): array
    {
        $rows = [];
        foreach ($this->storage->sums(Recorder::KIND_ZERO, $from, $storeId, self::LIST_SIZE) as $row) {
            $parts = [];
            foreach (explode(';', $row['value']) as $pick) {
                [$key, $values] = array_pad(explode('=', $pick, 2), 2, '');
                $config = $active[$key] ?? null;
                if ($config === null) {
                    continue 2;
                }
                $labels = array_map(
                    fn (string $value): string => $this->valueLabel($config, $value, $storeId),
                    array_filter(explode(',', $values), static fn (string $value): bool => $value !== '')
                );
                $parts[] = $this->label($config) . ($labels === [] ? '' : ': ' . implode(', ', $labels));
            }
            $rows[] = ['label' => implode(' + ', $parts), 'hits' => $row['hits']];
        }

        return $rows;
    }

    /**
     * @param FilterConfig $config
     * @return string
     */
    private function label(FilterConfig $config): string
    {
        return $config->getLabel()
            ?? ($config->isBuiltin() ? (string) __($this->defaults->builtinLabel($config->getCode())) : $config->getCode());
    }

    /**
     * @param FilterConfig $config
     * @param string $value
     * @param int $storeId
     * @return string
     */
    private function valueLabel(FilterConfig $config, string $value, int $storeId): string
    {
        if ($config->isBuiltin()) {
            return match ($config->getCode()) {
                Filter::BUILTIN_RATING => $value === '1' ? (string) __('%1 star & up', $value) : (string) __('%1 stars & up', $value),
                Filter::BUILTIN_CATEGORY => ($this->categoryTree->forStore($storeId)[(int) $value]['name'] ?? '') ?: $value,
                default => (string) __('Yes'),
            };
        }
        if ($config->getInputType() === 'boolean') {
            return $value === '1' ? (string) __('Yes') : (string) __('No');
        }
        $attributeId = (int) $config->getAttributeId();
        if (!isset($this->optionLabels[$attributeId])) {
            $labels = $this->slugStorage->optionLabels($attributeId);
            $this->optionLabels[$attributeId] = ($labels['stores'][$storeId] ?? []) + $labels['admin'];
        }

        return $this->optionLabels[$attributeId][(int) $value] ?? $value;
    }

    /**
     * @param FilterConfig $config
     * @return bool attribute filters with options (the ones "Let Google index" applies to)
     */
    private function isOptionFilter(FilterConfig $config): bool
    {
        return !$config->isBuiltin() && !in_array($config->getInputType(), ['decimal', 'boolean', 'price'], true);
    }
}
