<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\Base\Serializer\SafeJson;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The merchant's option images (1.2) for the "Images" display type: one image per option, the same in every store
 * view, stored below media/openfilter/options. Storefront reads come from the "openfilter" cache: no SQL.
 */
class OptionImages
{
    public const TABLE = 'openfilter_option_image';

    /** Folder below pub/media. */
    public const MEDIA_DIR = 'openfilter/options';

    /** Folder below pub/media for uploads not saved yet. */
    public const TMP_DIR = 'openfilter/tmp/options';

    private const CACHE_KEY = 'openfilter_option_images';

    /** @var array<int, string>|null option id => path below MEDIA_DIR */
    private ?array $paths = null;

    /**
     * @param ResourceConnection $resource
     * @param FrontendInterface $cache the "openfilter" cache type
     * @param SafeJson $json
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly FrontendInterface $cache,
        private readonly SafeJson $json,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Every stored image.
     *
     * @return array<int, string> option id => path below MEDIA_DIR
     */
    public function paths(): array
    {
        if ($this->paths !== null) {
            return $this->paths;
        }
        $cached = $this->cache->load(self::CACHE_KEY);
        if (is_string($cached)) {
            $paths = [];
            foreach ((array) $this->json->decode($cached) as $optionId => $path) {
                $paths[(int) $optionId] = (string) $path;
            }

            return $this->paths = $paths;
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()->from($this->resource->getTableName(self::TABLE), ['option_id', 'image']);
        $paths = [];
        foreach ($connection->fetchPairs($select) as $optionId => $path) {
            $paths[(int) $optionId] = (string) $path;
        }
        $this->cache->save($this->json->encode($paths), self::CACHE_KEY, [CacheType::CACHE_TAG]);

        return $this->paths = $paths;
    }

    /**
     * Image URLs of these options in a store view (options without an image are left out).
     *
     * @param array<int|string> $optionIds
     * @param int|null $storeId null = the current store view
     * @return array<int, string> option id => URL
     */
    public function urls(array $optionIds, ?int $storeId = null): array
    {
        $paths = array_intersect_key($this->paths(), array_flip(array_map('intval', $optionIds)));
        if ($paths === []) {
            return [];
        }
        $store = $this->storeManager->getStore($storeId);
        $base = $store instanceof Store ? $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) : '/media/';

        return array_map(static fn (string $path): string => $base . self::MEDIA_DIR . '/' . ltrim($path, '/'), $paths);
    }

    /**
     * Sets or removes images: option id => path below MEDIA_DIR, null removes.
     *
     * @param array<int, string|null> $images
     * @return void
     */
    public function save(array $images): void
    {
        if ($images === []) {
            return;
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $remove = array_keys(array_filter($images, static fn (?string $path): bool => $path === null || $path === ''));
        if ($remove !== []) {
            $connection->delete($table, ['option_id IN (?)' => $remove]);
        }
        $rows = [];
        foreach ($images as $optionId => $path) {
            if ($path !== null && $path !== '') {
                $rows[] = ['option_id' => (int) $optionId, 'image' => $path];
            }
        }
        if ($rows !== []) {
            $connection->insertOnDuplicate($table, $rows, ['image']);
        }
        $this->reset();
    }

    /**
     * Forgets the cached map (after a save).
     *
     * @return void
     */
    public function reset(): void
    {
        $this->paths = null;
        $this->cache->remove(self::CACHE_KEY);
    }
}
