<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;

/**
 * Applies search criteria to the filter list in memory: the list is small (filterable attributes plus six
 * built-ins) and comes from the per-store cache, so the admin grid runs no SQL for it.
 *
 * Fields are the keys of FilterConfig::toArray() (code, label, display_mode, active, builtin, position, …) plus
 * "key", the unique map key ("attribute:new" for an attribute named like a built-in). Filters
 * of one group are alternatives (OR), groups all apply (AND). Conditions: eq, neq, like (% wildcards,
 * case-insensitive), in, nin, gt, gteq, lt, lteq, null, notnull. An unknown field matches nothing.
 */
class FilterConfigList
{
    /**
     * @param FilterConfig[] $configs
     * @param SearchCriteriaInterface $criteria
     * @return array{0: FilterConfig[], 1: int} the page of items and the number of matches before paging
     */
    public function apply(array $configs, SearchCriteriaInterface $criteria): array
    {
        $items = array_values($configs);
        foreach ($criteria->getFilterGroups() as $group) {
            $filters = $group->getFilters() ?? [];
            if ($filters === []) {
                continue;
            }
            $items = array_values(array_filter(
                $items,
                fn (FilterConfig $config): bool => $this->matchesAny($this->row($config), $filters)
            ));
        }
        $items = $this->sort($items, $criteria->getSortOrders() ?? []);
        $total = count($items);
        $size = (int) $criteria->getPageSize();
        if ($size > 0) {
            $pages = max(1, (int) ceil($total / $size));
            $page = min(max(1, (int) $criteria->getCurrentPage()), $pages);
            $items = array_slice($items, ($page - 1) * $size, $size);
        }

        return [$items, $total];
    }

    /**
     * @param FilterConfig $config
     * @return array<string, mixed>
     */
    private function row(FilterConfig $config): array
    {
        return $config->toArray() + ['key' => $config->getKey()];
    }

    /**
     * @param array<string, mixed> $row
     * @param Filter[] $filters
     * @return bool
     */
    private function matchesAny(array $row, array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($this->matches($row, $filter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     * @param Filter $filter
     * @return bool
     */
    private function matches(array $row, Filter $filter): bool
    {
        $field = (string) $filter->getField();
        if (!array_key_exists($field, $row)) {
            return false;
        }
        $actual = $this->scalar($row[$field]);
        $expected = $filter->getValue();

        return match ((string) ($filter->getConditionType() ?: 'eq')) {
            'eq' => $actual === $this->scalar($expected),
            'neq' => $actual !== $this->scalar($expected),
            'like' => $this->like((string) $actual, (string) $expected),
            'in' => in_array($actual, $this->values($expected), true),
            'nin' => !in_array($actual, $this->values($expected), true),
            'gt' => $actual !== null && (float) $actual > (float) $expected,
            'gteq' => $actual !== null && (float) $actual >= (float) $expected,
            'lt' => $actual !== null && (float) $actual < (float) $expected,
            'lteq' => $actual !== null && (float) $actual <= (float) $expected,
            'null' => $actual === null || $actual === '',
            'notnull' => $actual !== null && $actual !== '',
            default => false,
        };
    }

    /**
     * Compares like SQL would: booleans as 1/0, everything as strings.
     *
     * @param mixed $value
     * @return string|null
     */
    private function scalar(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => null,
        };
    }

    /**
     * @param mixed $value array or comma-separated list
     * @return list<string|null>
     */
    private function values(mixed $value): array
    {
        $list = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_map(fn ($v): ?string => $this->scalar(is_string($v) ? trim($v) : $v), $list));
    }

    /**
     * @param string $actual
     * @param string $pattern SQL LIKE pattern (% and _)
     * @return bool
     */
    private function like(string $actual, string $pattern): bool
    {
        $regex = '/^' . strtr(preg_quote($pattern, '/'), ['%' => '.*', '_' => '.']) . '$/iu';

        return preg_match($regex, $actual) === 1;
    }

    /**
     * Stable sort by the given orders; nulls first when ascending.
     *
     * @param FilterConfig[] $items
     * @param SortOrder[] $orders
     * @return FilterConfig[]
     */
    private function sort(array $items, array $orders): array
    {
        if ($orders === []) {
            return $items;
        }
        $rows = array_map(fn (FilterConfig $c): array => $this->row($c), $items);
        $indexes = array_keys($items);
        usort($indexes, function (int $a, int $b) use ($rows, $orders): int {
            foreach ($orders as $order) {
                $field = (string) $order->getField();
                $left = $this->sortable($rows[$a][$field] ?? null);
                $right = $this->sortable($rows[$b][$field] ?? null);
                $result = $left <=> $right;
                if ($result !== 0) {
                    return strtoupper((string) $order->getDirection()) === SortOrder::SORT_DESC ? -$result : $result;
                }
            }

            return $a <=> $b;
        });

        return array_map(static fn (int $i): FilterConfig => $items[$i], $indexes);
    }

    /**
     * @param mixed $value
     * @return array{int, int|float|string}
     */
    private function sortable(mixed $value): array
    {
        return match (true) {
            $value === null => [0, ''],
            is_bool($value) => [1, (int) $value],
            is_int($value), is_float($value) => [1, $value],
            default => [1, mb_strtolower((string) $value)],
        };
    }
}
