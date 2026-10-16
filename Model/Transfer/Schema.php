<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Transfer;

/**
 * The JSON format of `openfilter:config:export` / `openfilter:config:import` (version 1): filters keyed by filter key
 * (attribute code, built-in code or "attribute:<code>"), store views by code ("admin" = all store views), options by
 * their admin label, so a file moves between installations whose ids differ.
 *
 * {"schema": 1, "filters": {"color": {"settings": {...}, "labels": {"de": "Farbe"},
 *   "url_name": {"admin": "color"}, "options": {"Red": {"admin": "red", "de": "rot"}}}}}
 */
class Schema
{
    public const VERSION = 3;

    /**
     * Versions this module reads: 1 (OpenFilter 1.0), 2 (1.1: logic "and", ranges, tree depth, tooltips, where it
     * shows) and 3 (1.2: dependent filters, quick filter bar). Values a filter waits for are the parent's option keys.
     */
    public const READS = [1, 2, 3];

    public const ADMIN = 'admin';

    /** Settings and their kind. */
    public const SETTINGS = [
        'display_mode' => 'string',
        'multiselect' => 'bool',
        'logic' => 'string',
        'visible_options' => 'int',
        'search_threshold' => 'int',
        'expanded' => '?bool',
        'sort_by' => 'string',
        'hide_single_option' => 'bool',
        'seo_indexable' => 'bool',
        'position_override' => '?int',
        'active' => 'bool',
        'ranges' => '?string',
        'tree_depth' => 'int',
        'category_mode' => 'string',
        'category_ids' => 'int[]',
        'depends_on' => '?string',
        'depends_values' => 'string[]',
        'quick_bar' => 'bool',
    ];

    private const SECTIONS = ['settings', 'labels', 'tooltips', 'url_name', 'options'];

    /** Option URL names: lowercase letters, digits, single dashes. */
    private const URL_NAME = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Filter parameter names may also keep the attribute code's underscores. */
    private const PARAMETER = '/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/';

    /**
     * Problems with a file before anything is written (empty = valid).
     *
     * @param mixed $data decoded JSON
     * @param array<string, array{builtin: bool, options: string[]}> $filters known filters by key, with their
     *     option keys (Schema::optionKeys)
     * @param string[] $stores known store codes, "admin" included
     * @return string[]
     */
    public function validate(mixed $data, array $filters, array $stores): array
    {
        if (!is_array($data)) {
            return ['The file is not a JSON object.'];
        }
        if (!in_array($data['schema'] ?? null, self::READS, true)) {
            $schema = json_encode($data['schema'] ?? null);

            $last = self::READS[array_key_last(self::READS)];
            $versions = implode(', ', array_slice(self::READS, 0, -1)) . ' and ' . $last;

            return [sprintf('Unsupported schema "%s" (this version reads %s).', $schema, $versions)];
        }
        $filtersData = $data['filters'] ?? null;
        if (!is_array($filtersData) || (array_is_list($filtersData) && $filtersData !== [])) {
            return ['"filters" must be an object keyed by filter.'];
        }
        $errors = [];
        foreach ($data['filters'] as $key => $filter) {
            $key = (string) $key;
            if (!isset($filters[$key])) {
                $errors[] = sprintf('%s: no such filter in this store.', $key);
                continue;
            }
            if (!is_array($filter)) {
                $errors[] = sprintf('%s: must be an object.', $key);
                continue;
            }
            foreach (array_diff(array_keys($filter), self::SECTIONS) as $unknown) {
                $errors[] = sprintf('%s: unknown section "%s".', $key, $unknown);
            }
            array_push(
                $errors,
                ...$this->settings($key, $filter['settings'] ?? []),
                ...$this->storeValues($key, 'labels', $filter['labels'] ?? [], $stores, null),
                ...$this->storeValues($key, 'tooltips', $filter['tooltips'] ?? [], $stores, null),
                ...$this->storeValues($key, 'url_name', $filter['url_name'] ?? [], $stores, self::PARAMETER)
            );
            $options = $filter['options'] ?? [];
            if ($options !== [] && $filters[$key]['builtin']) {
                $errors[] = sprintf('%s: built-in filters have no option URL names.', $key);
                continue;
            }
            if (!is_array($options)) {
                $errors[] = sprintf('%s.options: must be an object.', $key);
                continue;
            }
            foreach ($options as $label => $names) {
                $label = (string) $label;
                if (!in_array($label, $filters[$key]['options'], true)) {
                    $errors[] = sprintf('%s.options: no option "%s".', $key, $label);
                } else {
                    array_push($errors, ...$this->storeValues("$key.options", $label, $names, $stores, self::URL_NAME));
                }
            }
        }

        return $errors;
    }

    /**
     * Keys of an attribute's options in the file: the admin label, or "label #n" (n-th of that label in option
     * order) when several options share a label.
     *
     * @param array<int, string> $labels option id => admin label, in option order
     * @return array<int, string> option id => key
     */
    public function optionKeys(array $labels): array
    {
        $counts = array_count_values(array_map('strval', $labels));
        $seen = [];
        $keys = [];
        foreach ($labels as $optionId => $label) {
            $label = (string) $label;
            $seen[$label] = ($seen[$label] ?? 0) + 1;
            $keys[$optionId] = $counts[$label] > 1 ? sprintf('%s #%d', $label, $seen[$label]) : $label;
        }

        return $keys;
    }

    /**
     * Changed values between two exports, as "key.section.field: old → new" lines (only filters in $new).
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @return string[]
     */
    public function diff(array $old, array $new): array
    {
        $before = $this->flatten((array) ($old['filters'] ?? []));
        $after = $this->flatten((array) ($new['filters'] ?? []));
        $lines = [];
        foreach ($after as $path => $value) {
            $previous = array_key_exists($path, $before) ? $before[$path] : null;
            if ($previous !== $value) {
                $lines[] = sprintf('%s: %s → %s', $path, json_encode($previous), json_encode($value));
            }
        }

        return $lines;
    }

    /**
     * @param string $key
     * @param mixed $settings
     * @return string[]
     */
    private function settings(string $key, mixed $settings): array
    {
        if (!is_array($settings)) {
            return [sprintf('%s.settings: must be an object.', $key)];
        }
        $errors = [];
        foreach ($settings as $name => $value) {
            $kind = self::SETTINGS[$name] ?? null;
            if ($kind === null) {
                $errors[] = sprintf('%s.settings: unknown setting "%s".', $key, $name);
                continue;
            }
            if ($value === null && str_starts_with($kind, '?')) {
                continue;
            }
            $ok = match (ltrim($kind, '?')) {
                'bool' => is_bool($value),
                'int' => is_int($value),
                'int[]' => is_array($value) && array_is_list($value) && array_filter($value, 'is_int') === $value,
                'string[]' => is_array($value) && array_is_list($value) && array_filter($value, 'is_string') === $value,
                default => is_string($value) && $value !== '',
            };
            if (!$ok) {
                $errors[] = sprintf('%s.settings.%s: expected %s, got %s.', $key, $name, $kind, json_encode($value));
            }
        }

        return $errors;
    }

    /**
     * @param string $key
     * @param string $section
     * @param mixed $values
     * @param string[] $stores
     * @param string|null $pattern
     * @return string[]
     */
    private function storeValues(string $key, string $section, mixed $values, array $stores, ?string $pattern): array
    {
        if (!is_array($values)) {
            return [sprintf('%s.%s: must be an object keyed by store view code.', $key, $section)];
        }
        $errors = [];
        foreach ($values as $store => $value) {
            if (!in_array((string) $store, $stores, true)) {
                $errors[] = sprintf('%s.%s: no store view "%s".', $key, $section, $store);
            } elseif (!is_string($value) || ($pattern !== null && preg_match($pattern, $value) !== 1)) {
                $errors[] = sprintf('%s.%s.%s: invalid value %s.', $key, $section, $store, json_encode($value));
            }
        }

        return $errors;
    }

    /**
     * @param array<string|int, mixed> $value
     * @param string $prefix
     * @return array<string, mixed>
     */
    private function flatten(array $value, string $prefix = ''): array
    {
        $flat = [];
        foreach ($value as $name => $item) {
            $path = $prefix === '' ? (string) $name : $prefix . '.' . $name;
            // Lists (category ids) are one value, so a removed id shows as a change.
            if (is_array($item) && $item !== [] && !array_is_list($item)) {
                $flat += $this->flatten($item, $path);
            } else {
                $flat[$path] = $item;
            }
        }

        return $flat;
    }
}
