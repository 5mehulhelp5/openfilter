<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\Base\Serializer\SafeJson;
use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Magento\Framework\Cache\FrontendInterface;

/**
 * Every filter of one store view with resolved labels and positions, in position order. One cache entry per store
 * (`openfilter_config_<store>`), so a storefront request reads no tables for configuration (02 · Architecture §10).
 */
class FilterConfigProvider
{
    public const CACHE_KEY_PREFIX = 'openfilter_config_';

    /** @var array<int, array<string, FilterConfig>> */
    private array $loaded = [];

    /**
     * @param FilterConfigStorage $storage
     * @param Defaults $defaults
     * @param FrontendInterface $cache the "openfilter" cache type
     * @param SafeJson $json
     */
    public function __construct(
        private readonly FilterConfigStorage $storage,
        private readonly Defaults $defaults,
        private readonly FrontendInterface $cache,
        private readonly SafeJson $json
    ) {
    }

    /**
     * Every filter of the store view, from the cache or built and cached.
     *
     * @param int $storeId
     * @return array<string, FilterConfig> by code, in position order
     */
    public function getForStore(int $storeId): array
    {
        if (isset($this->loaded[$storeId])) {
            return $this->loaded[$storeId];
        }
        $key = self::CACHE_KEY_PREFIX . $storeId;
        $cached = $this->cache->load($key);
        if (is_string($cached) && $cached !== '') {
            $configs = [];
            foreach ($this->json->decode($cached) as $data) {
                $config = new FilterConfig((array) $data);
                $configs[$config->getCode()] = $config;
            }

            return $this->loaded[$storeId] = $configs;
        }
        $configs = $this->build($storeId);
        $this->cache->save(
            $this->json->encode(array_map(static fn (FilterConfig $c): array => $c->toArray(), array_values($configs))),
            $key,
            [CacheType::CACHE_TAG]
        );

        return $this->loaded[$storeId] = $configs;
    }

    /**
     * The active filters of one store view (attributes and switched-on built-ins).
     *
     * @param int $storeId
     * @return array<string, FilterConfig>
     */
    public function getActive(int $storeId): array
    {
        return array_filter($this->getForStore($storeId), static fn (FilterConfig $c): bool => $c->isActive());
    }

    /**
     * Forgets what this process loaded (after a save).
     *
     * @return void
     */
    public function reset(): void
    {
        $this->loaded = [];
    }

    /**
     * Merges filterable attributes, saved settings, defaults and store labels.
     *
     * @param int $storeId
     * @return array<string, FilterConfig>
     */
    private function build(int $storeId): array
    {
        $attributes = $this->storage->filterableAttributes($storeId);
        $rows = $this->storage->attributeRows();
        $builtinRows = $this->storage->builtinRows();
        $labels = $this->storage->labels($storeId);
        $behaviour = array_flip(FilterConfig::BEHAVIOUR_COLUMNS);

        $configs = [];
        $price = null;
        foreach ($attributes as $id => $attribute) {
            if ($attribute['code'] === FilterConfigInterface::BUILTIN_PRICE) {
                $price = $attribute;
                continue;
            }
            $config = $this->defaults->forAttribute(
                $attribute['code'],
                $id,
                $attribute['input_type'],
                $attribute['swatch_type'],
                $attribute['position']
            );
            if (isset($rows[$id])) {
                $config = $config->with(array_intersect_key($rows[$id], $behaviour));
            }
            $configs[] = $config->with(['label' => $labels['attributes'][$id] ?? $attribute['label']]);
        }
        foreach (FilterConfigInterface::BUILTIN_CODES as $code) {
            $config = $this->defaults->forBuiltin($code);
            $label = $labels['builtins'][$code] ?? null;
            if ($code === FilterConfigInterface::BUILTIN_PRICE && $price !== null) {
                $config = $config->with(['attribute_id' => $price['attribute_id'], 'position' => $price['position']]);
                $label ??= $price['label'];
            }
            if (isset($builtinRows[$code])) {
                $row = $builtinRows[$code];
                $config = $config->with(array_intersect_key($row, $behaviour) + ['active' => $row['is_active']]);
            }
            $configs[] = $config->with(['label' => $label]);
        }
        usort(
            $configs,
            static fn (FilterConfig $a, FilterConfig $b): int =>
                [$a->getPosition(), $a->getCode()] <=> [$b->getPosition(), $b->getCode()]
        );
        $byCode = [];
        foreach ($configs as $config) {
            $byCode[$config->getCode()] = $config;
        }

        return $byCode;
    }
}
