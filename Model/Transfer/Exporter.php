<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Transfer;

use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reads OpenFilter's settings, labels and URL names into the portable format of Schema.
 */
class Exporter
{
    /**
     * @param FilterConfigProvider $provider
     * @param FilterConfigStorage $configStorage
     * @param SlugStorage $slugStorage
     * @param StoreManagerInterface $storeManager
     * @param Schema $schema
     */
    public function __construct(
        private readonly FilterConfigProvider $provider,
        private readonly FilterConfigStorage $configStorage,
        private readonly SlugStorage $slugStorage,
        private readonly StoreManagerInterface $storeManager,
        private readonly Schema $schema
    ) {
    }

    /**
     * @return array{schema: int, filters: array<string, array<string, mixed>>}
     */
    public function export(): array
    {
        $codes = $this->storeCodes();
        $attributeSlugs = [];
        foreach ($codes as $storeId => $code) {
            $attributeSlugs[$code] = $this->slugStorage->attributeSlugsForStore($storeId);
        }
        $filters = [];
        foreach ($this->provider->getForStore(Store::DEFAULT_STORE_ID) as $key => $config) {
            $attributeId = $config->isBuiltin() ? null : $config->getAttributeId();
            $filter = ['settings' => $this->settings($config)];
            $labels = $this->byCode($this->configStorage->labelOverrides($attributeId, $config->getCode()), $codes);
            if ($labels !== []) {
                $filter['labels'] = $labels;
            }
            if ($attributeId !== null) {
                $urlName = [];
                foreach ($attributeSlugs as $code => $slugs) {
                    if (isset($slugs[$attributeId])) {
                        $urlName[$code] = $slugs[$attributeId];
                    }
                }
                if ($urlName !== []) {
                    $filter['url_name'] = $urlName;
                }
                $options = $this->options($attributeId, $codes);
                if ($options !== []) {
                    $filter['options'] = $options;
                }
            }
            $filters[(string) $key] = $filter;
        }

        return ['schema' => Schema::VERSION, 'filters' => $filters];
    }

    /**
     * Known filters for Schema::validate: key => built-in flag and the keys of its options.
     *
     * @return array<string, array{builtin: bool, options: string[]}>
     */
    public function knownFilters(): array
    {
        $known = [];
        foreach ($this->provider->getForStore(Store::DEFAULT_STORE_ID) as $key => $config) {
            $attributeId = $config->isBuiltin() ? null : $config->getAttributeId();
            $known[(string) $key] = [
                'builtin' => $attributeId === null,
                'options' => $attributeId === null ? [] : array_values($this->optionKeys($attributeId)),
            ];
        }

        return $known;
    }

    /**
     * @param int $attributeId
     * @return array<int, string> option id => key in the file (Schema::optionKeys)
     */
    public function optionKeys(int $attributeId): array
    {
        return $this->schema->optionKeys($this->slugStorage->optionLabels($attributeId)['admin']);
    }

    /**
     * @return array<int, string> store id => code, admin (0) first
     */
    public function storeCodes(): array
    {
        $codes = [Store::DEFAULT_STORE_ID => Schema::ADMIN];
        foreach ($this->storeManager->getStores() as $store) {
            $codes[(int) $store->getId()] = (string) $store->getCode();
        }

        return $codes;
    }

    /**
     * @param FilterConfig $config
     * @return array<string, mixed>
     */
    private function settings(FilterConfig $config): array
    {
        return [
            'display_mode' => $config->getDisplayMode(),
            'multiselect' => $config->isMultiselect(),
            'logic' => $config->getLogic(),
            'visible_options' => $config->getVisibleOptions(),
            'search_threshold' => $config->getSearchThreshold(),
            'expanded' => $config->getExpanded(),
            'sort_by' => $config->getSortBy(),
            'hide_single_option' => $config->isHideSingleOption(),
            'seo_indexable' => $config->isSeoIndexable(),
            'position_override' => $config->getPositionOverride(),
            'active' => $config->isActive(),
        ];
    }

    /**
     * @param int $attributeId
     * @param array<int, string> $codes
     * @return array<string, array<string, string>> option key => store code => URL name (stored rows only)
     */
    private function options(int $attributeId, array $codes): array
    {
        $rows = $this->slugStorage->optionSlugRows($attributeId);
        $options = [];
        foreach ($this->optionKeys($attributeId) as $optionId => $label) {
            foreach ($rows as $storeId => $slugs) {
                if (isset($slugs[$optionId], $codes[$storeId])) {
                    $options[$label][$codes[$storeId]] = $slugs[$optionId];
                }
            }
        }

        return $options;
    }

    /**
     * @param array<int, string> $byStoreId
     * @param array<int, string> $codes
     * @return array<string, string>
     */
    private function byCode(array $byStoreId, array $codes): array
    {
        $byCode = [];
        foreach ($byStoreId as $storeId => $value) {
            if (isset($codes[$storeId])) {
                $byCode[$codes[$storeId]] = $value;
            }
        }

        return $byCode;
    }
}
