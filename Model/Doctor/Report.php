<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Doctor;

use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;

/**
 * Everything `openfilter:doctor` checks for one store view: the installation once, then each filter with the same
 * checks as the admin's "Why is this filter not showing?" panel (store view checks shown once, not per filter).
 */
class Report
{
    /** Checks FilterDiagnosis repeats for every filter; the report keeps the first. */
    private const ONCE = ['enabled', 'coexistence'];

    /** Failed checks that describe a merchant's choice or the catalog, not a fault: reported, never fatal. */
    public const WARNINGS = ['shown', 'search_pages', 'out_of_stock_shown', 'products', 'single_option', 'search_page_filters'];

    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /**
     * @param FilterDiagnosis $diagnosis
     * @param SystemChecks $system
     * @param FilterConfigProvider $provider
     */
    public function __construct(
        private readonly FilterDiagnosis $diagnosis,
        private readonly SystemChecks $system,
        private readonly FilterConfigProvider $provider
    ) {
    }

    /**
     * @param int $storeId
     * @param int|null $categoryId also check the filters on this category page
     * @param string|null $filter only this filter key
     * @return array<string, Check[]> section ("OpenFilter" or a filter key) => checks
     */
    public function run(int $storeId, ?int $categoryId = null, ?string $filter = null): array
    {
        $configs = $this->provider->getForStore($storeId);
        $keys = $filter !== null ? [$filter] : array_keys($configs);
        $optionAttributes = [];
        foreach ($configs as $key => $config) {
            if (!$config->isBuiltin() && in_array($config->getInputType(), ['select', 'multiselect'], true)
                && in_array($key, $keys, true)) {
                $optionAttributes[(int) $config->getAttributeId()] = (string) $key;
            }
        }
        $sections = ['OpenFilter' => []];
        foreach ($keys as $key) {
            foreach ($this->diagnosis->diagnose((string) $key, $storeId, $categoryId) as $check) {
                $code = $check->toArray()['code'];
                if (in_array($code, self::ONCE, true)) {
                    $sections['OpenFilter'][$code] ??= $check;
                } elseif ($code !== 'url_names') {
                    $sections[(string) $key][] = $check;
                }
            }
        }
        $sections['OpenFilter'] = [
            ...array_values($sections['OpenFilter']),
            ...$this->system->run($storeId, $optionAttributes),
        ];

        return $sections;
    }

    /**
     * Whether no check failed (warnings allowed).
     *
     * @param array<string, Check[]> $sections
     * @return bool
     */
    public function passed(array $sections): bool
    {
        foreach ($sections as $checks) {
            foreach ($checks as $check) {
                if ($this->level($check) === self::FAIL) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param Check $check
     * @return string ok, warn or fail
     */
    public function level(Check $check): string
    {
        $row = $check->toArray();
        if ($row['ok']) {
            return self::OK;
        }

        return in_array($row['code'], self::WARNINGS, true) ? self::WARN : self::FAIL;
    }
}
