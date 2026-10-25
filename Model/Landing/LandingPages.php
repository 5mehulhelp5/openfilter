<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Landing;

use Iranimij\Base\Serializer\SafeJson;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Landing pages (1.2): storage, checks and the storefront lookups. The router and the filter links read every active
 * page from the "openfilter" cache (no SQL on the storefront); a save or delete cleans it and marks the full page
 * cache invalid, as filter settings do.
 */
class LandingPages
{
    public const TABLE = 'openfilter_landing_page';
    public const STORE_TABLE = 'openfilter_landing_page_store';

    private const CACHE_KEY = 'openfilter_landing_pages';

    /** Core's full page cache type. */
    private const FULL_PAGE_CACHE = 'full_page';

    /** @var LandingPage[]|null every active page */
    private ?array $active = null;

    /** @var array<string, array<string, LandingPage>> "store:category" => state key => page (forState) */
    private array $index = [];

    /**
     * @param ResourceConnection $resource
     * @param FrontendInterface $cache the "openfilter" cache type
     * @param SafeJson $json
     * @param TypeListInterface $cacheTypeList
     * @param UrlFinderInterface $urlFinder URL keys taken by categories, products and CMS pages
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly FrontendInterface $cache,
        private readonly SafeJson $json,
        private readonly TypeListInterface $cacheTypeList,
        private readonly UrlFinderInterface $urlFinder
    ) {
    }

    /**
     * @param int $pageId
     * @return LandingPage
     * @throws NoSuchEntityException
     */
    public function get(int $pageId): LandingPage
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->resource->getTableName(self::TABLE))->where('page_id = ?', $pageId)
        );
        if (!is_array($row) || $row === []) {
            throw new NoSuchEntityException(__('The landing page %1 does not exist.', $pageId));
        }

        return new LandingPage($row + ['store_ids' => $this->storeIds([$pageId])[$pageId] ?? [0]]);
    }

    /**
     * Every page, newest first (the admin grid).
     *
     * @return LandingPage[]
     */
    public function getAll(): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()->from($this->resource->getTableName(self::TABLE))->order('page_id DESC')
        );
        $stores = $this->storeIds(array_map('intval', array_column($rows, 'page_id')));

        return array_map(
            static fn (array $row): LandingPage => new LandingPage($row + ['store_ids' => $stores[(int) $row['page_id']] ?? [0]]),
            $rows
        );
    }

    /**
     * Checks and writes a page; returns it with its id.
     *
     * @param LandingPage $page
     * @return LandingPage
     * @throws InputException with every problem found
     */
    public function save(LandingPage $page): LandingPage
    {
        $this->validate($page);
        /** @var \Magento\Framework\DB\Adapter\Pdo\Mysql $connection */
        $connection = $this->resource->getConnection();
        $row = array_intersect_key($page->toArray(), array_flip([
            'is_active', 'category_id', 'url_key', 'title', 'h1', 'meta_title', 'meta_description', 'description', 'robots',
        ]));
        $row['is_active'] = (int) $row['is_active'];
        $row['filters'] = $this->json->encode($page->getFilters());
        $table = $this->resource->getTableName(self::TABLE);
        $pageId = $page->getId();
        if ($pageId === null) {
            $connection->insert($table, $row);
            $pageId = (int) $connection->lastInsertId($table);
        } else {
            $connection->update($table, $row, ['page_id = ?' => $pageId]);
        }
        $storeTable = $this->resource->getTableName(self::STORE_TABLE);
        $connection->delete($storeTable, ['page_id = ?' => $pageId]);
        $connection->insertMultiple($storeTable, array_map(
            static fn (int $storeId): array => ['page_id' => $pageId, 'store_id' => $storeId],
            $page->getStoreIds()
        ));
        $this->clean();

        return $this->get($pageId);
    }

    /**
     * @param int[] $pageIds
     * @return void
     */
    public function delete(array $pageIds): void
    {
        if ($pageIds === []) {
            return;
        }
        $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['page_id IN (?)' => array_map('intval', $pageIds)]
        );
        $this->clean();
    }

    /**
     * The active page at a URL key in a store view.
     *
     * @param string $urlKey
     * @param int $storeId
     * @return LandingPage|null
     */
    public function byUrlKey(string $urlKey, int $storeId): ?LandingPage
    {
        $urlKey = strtolower(trim($urlKey, '/'));
        foreach ($this->active() as $page) {
            if ($page->getUrlKey() === $urlKey && $page->isInStore($storeId)) {
                return $page;
            }
        }

        return null;
    }

    /**
     * The active page of a category whose filters are exactly this state, in a store view.
     *
     * @param int $categoryId
     * @param array<string, string[]> $filters
     * @param int $storeId
     * @return LandingPage|null
     */
    public function forState(int $categoryId, array $filters, int $storeId): ?LandingPage
    {
        if ($filters === [] || $this->active() === []) {
            return null;
        }
        $slot = $storeId . ':' . $categoryId;
        if (!isset($this->index[$slot])) {
            $this->index[$slot] = [];
            foreach ($this->active() as $page) {
                if ($page->getCategoryId() === $categoryId && $page->isInStore($storeId)) {
                    $this->index[$slot][$this->stateKey($page->getFilters())] ??= $page;
                }
            }
        }

        return $this->index[$slot] === [] ? null : ($this->index[$slot][$this->stateKey($filters)] ?? null);
    }

    /**
     * Active pages of a store view (the XML sitemap).
     *
     * @param int $storeId
     * @return LandingPage[]
     */
    public function forStore(int $storeId): array
    {
        return array_values(array_filter($this->active(), static fn (LandingPage $page): bool => $page->isInStore($storeId)));
    }

    /**
     * A stable key of a filter state (keys and values sorted), to compare states.
     *
     * @param array<string, string[]> $filters
     * @return string
     */
    public function stateKey(array $filters): string
    {
        ksort($filters);
        foreach ($filters as $key => $values) {
            sort($values);
            $filters[$key] = array_values(array_map('strval', $values));
        }

        return (string) $this->json->encode($filters);
    }

    /**
     * @param LandingPage $page
     * @return void
     * @throws InputException
     */
    private function validate(LandingPage $page): void
    {
        $errors = [];
        if ($page->getTitle() === '') {
            $errors[] = __('Enter a title.');
        }
        if (preg_match('#^[a-z0-9][a-z0-9._-]*(?:/[a-z0-9][a-z0-9._-]*)*$#', $page->getUrlKey()) !== 1) {
            $errors[] = __('The URL key "%1" may use lower-case letters, digits, dots, dashes and slashes.', $page->getUrlKey());
        }
        if ($page->getCategoryId() <= 0 || $page->getFilters() === []) {
            $errors[] = __('Paste the address of a filtered category page with at least one filter.');
        }
        foreach ($this->getAll() as $other) {
            if ($other->getId() !== $page->getId() && $other->getUrlKey() === $page->getUrlKey()
                && $this->overlap($other->getStoreIds(), $page->getStoreIds())
            ) {
                $errors[] = __('The landing page "%1" already uses the URL key "%2".', $other->getTitle(), $page->getUrlKey());
                break;
            }
        }
        $taken = $this->urlFinder->findOneByData([UrlRewrite::REQUEST_PATH => $page->getUrlKey()]);
        if ($taken !== null && $page->getUrlKey() !== '') {
            $errors[] = __('Another page (%1) already has the address "%2".', $taken->getEntityType(), $page->getUrlKey());
        }
        if ($errors !== []) {
            throw new InputException(__(implode(' ', array_map('strval', $errors))));
        }
    }

    /**
     * Whether two pages share a store view (0 = every store view).
     *
     * @param int[] $first
     * @param int[] $second
     * @return bool
     */
    private function overlap(array $first, array $second): bool
    {
        return in_array(0, $first, true) || in_array(0, $second, true) || array_intersect($first, $second) !== [];
    }

    /**
     * Every active page, from the cache or read and cached.
     *
     * @return LandingPage[]
     */
    private function active(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }
        $cached = $this->cache->load(self::CACHE_KEY);
        if (is_string($cached)) {
            return $this->active = array_map(
                static fn ($data): LandingPage => new LandingPage((array) $data),
                (array) $this->json->decode($cached)
            );
        }
        $pages = array_values(array_filter($this->getAll(), static fn (LandingPage $page): bool => $page->isActive()));
        $this->cache->save(
            $this->json->encode(array_map(static fn (LandingPage $page): array => $page->toArray(), $pages)),
            self::CACHE_KEY,
            [CacheType::CACHE_TAG]
        );

        return $this->active = $pages;
    }

    /**
     * @param int[] $pageIds
     * @return array<int, int[]> page id => store ids
     */
    private function storeIds(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $stores = [];
        $rows = $connection->fetchAll(
            $connection->select()->from($this->resource->getTableName(self::STORE_TABLE))->where('page_id IN (?)', $pageIds)
        );
        foreach ($rows as $row) {
            $stores[(int) $row['page_id']][] = (int) $row['store_id'];
        }

        return $stores;
    }

    /**
     * @return void
     */
    private function clean(): void
    {
        $this->active = null;
        $this->index = [];
        $this->cache->remove(self::CACHE_KEY);
        $this->cacheTypeList->invalidate(self::FULL_PAGE_CACHE);
    }
}
