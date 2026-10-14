<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\Base\Serializer\SafeJson;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Magento\Framework\Cache\FrontendInterface;

/**
 * Effective URL names of one store view, cached (`openfilter_slugs_<store>`) so the codec reads no tables:
 * parameter name per filterable attribute and option URL names per attribute.
 */
class SlugProvider
{
    public const CACHE_KEY_PREFIX = 'openfilter_slugs_';

    /** @var array<int, array{attributes: array<int, string>, options: array<int, array<int, string>>}> */
    private array $loaded = [];

    /**
     * @param SlugStorage $storage
     * @param FilterConfigStorage $configStorage
     * @param SlugGenerator $generator
     * @param FrontendInterface $cache the "openfilter" cache type
     * @param SafeJson $json
     */
    public function __construct(
        private readonly SlugStorage $storage,
        private readonly FilterConfigStorage $configStorage,
        private readonly SlugGenerator $generator,
        private readonly FrontendInterface $cache,
        private readonly SafeJson $json
    ) {
    }

    /**
     * URL names of a store view.
     *
     * @param int $storeId
     * @return array{attributes: array<int, string>, options: array<int, array<int, string>>}
     */
    public function forStore(int $storeId): array
    {
        if (isset($this->loaded[$storeId])) {
            return $this->loaded[$storeId];
        }
        $key = self::CACHE_KEY_PREFIX . $storeId;
        $cached = $this->cache->load($key);
        if (is_string($cached) && $cached !== '') {
            $data = $this->json->decode($cached);

            return $this->loaded[$storeId] = [
                'attributes' => $this->intKeys((array) ($data['attributes'] ?? [])),
                'options' => array_map(fn ($o) => $this->intKeys((array) $o), $this->intKeys((array) ($data['options'] ?? []))),
            ];
        }
        $data = $this->build($storeId);
        $this->cache->save($this->json->encode($data), $key, [CacheType::CACHE_TAG]);

        return $this->loaded[$storeId] = $data;
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
     * Stored names, with attribute codes for parameters that have no row (made unique against the others).
     *
     * @param int $storeId
     * @return array{attributes: array<int, string>, options: array<int, array<int, string>>}
     */
    private function build(int $storeId): array
    {
        $stored = $this->storage->attributeSlugsForStore($storeId);
        $attributes = [];
        $missing = [];
        foreach ($this->configStorage->filterableAttributes($storeId) as $id => $attribute) {
            if ($attribute['code'] === 'price') {
                continue;
            }
            if (isset($stored[$id])) {
                $attributes[$id] = $stored[$id];
            } else {
                $missing[$id] = $attribute['code'];
            }
        }
        foreach ($missing as $id => $code) {
            $attributes[$id] = $this->generator->forAttribute($code, array_values($attributes));
        }

        return ['attributes' => $attributes, 'options' => $this->storage->optionSlugsForStore($storeId)];
    }

    /**
     * JSON objects with numeric keys decode as arrays with int keys already; this keeps the type explicit.
     *
     * @param array<int|string, mixed> $data
     * @return array<int, mixed>
     */
    private function intKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[(int) $key] = $value;
        }

        return $result;
    }
}
