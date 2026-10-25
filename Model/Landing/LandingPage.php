<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Landing;

/**
 * A landing page (1.2): a category with a fixed filter state at its own URL key, with its own title, heading, meta
 * tags and content, in some store views (0 = all). Plain value object; LandingPages stores and caches it.
 */
class LandingPage
{
    public const ROBOTS = ['INDEX,FOLLOW', 'NOINDEX,FOLLOW', 'NOINDEX,NOFOLLOW'];

    /** @var array<string, mixed> */
    private array $data = [
        'page_id' => null,
        'is_active' => true,
        'category_id' => 0,
        'filters' => [],
        'url_key' => '',
        'title' => '',
        'h1' => null,
        'meta_title' => null,
        'meta_description' => null,
        'description' => null,
        'robots' => 'INDEX,FOLLOW',
        'store_ids' => [0],
        'updated_at' => null,
    ];

    /**
     * @param array<string, mixed> $data a database row (filters as JSON) or the same keys with typed values
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $this->data)) {
                $this->data[$key] = $value;
            }
        }
        if (is_string($this->data['filters'])) {
            $decoded = json_decode($this->data['filters'], true);
            $this->data['filters'] = is_array($decoded) ? $decoded : [];
        }
        $this->data['filters'] = $this->filterList((array) $this->data['filters']);
        $this->data['page_id'] = $this->data['page_id'] === null || $this->data['page_id'] === '' ? null : (int) $this->data['page_id'];
        $this->data['is_active'] = (bool) $this->data['is_active'];
        $this->data['category_id'] = (int) $this->data['category_id'];
        $this->data['store_ids'] = array_values(array_unique(array_map('intval', (array) $this->data['store_ids'])));
        sort($this->data['store_ids']);
        foreach (['h1', 'meta_title', 'meta_description', 'description'] as $key) {
            $value = $this->data[$key];
            $this->data[$key] = $value === null || trim((string) $value) === '' ? null : (string) $value;
        }
        $this->data['url_key'] = strtolower(trim((string) $this->data['url_key'], " /"));
        $this->data['title'] = trim((string) $this->data['title']);
        if (!in_array($this->data['robots'], self::ROBOTS, true)) {
            $this->data['robots'] = self::ROBOTS[0];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->data['page_id'];
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->data['is_active'];
    }

    /**
     * @return int
     */
    public function getCategoryId(): int
    {
        return $this->data['category_id'];
    }

    /**
     * The filter state: filter key => values (option ids, "from-to"), in no particular order.
     *
     * @return array<string, string[]>
     */
    public function getFilters(): array
    {
        return $this->data['filters'];
    }

    /**
     * @return string
     */
    public function getUrlKey(): string
    {
        return $this->data['url_key'];
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->data['title'];
    }

    /**
     * Heading of the page (the title when empty).
     *
     * @return string
     */
    public function getHeading(): string
    {
        return $this->data['h1'] ?? $this->getTitle();
    }

    /**
     * Title in the browser and search results (the title when empty).
     *
     * @return string
     */
    public function getMetaTitle(): string
    {
        return $this->data['meta_title'] ?? $this->getTitle();
    }

    /**
     * @return string|null
     */
    public function getMetaDescription(): ?string
    {
        return $this->data['meta_description'];
    }

    /**
     * Content above the products (HTML; widgets and variables as in CMS blocks).
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->data['description'];
    }

    /**
     * @return string
     */
    public function getRobots(): string
    {
        return $this->data['robots'];
    }

    /**
     * @return int[] 0 = all store views
     */
    public function getStoreIds(): array
    {
        return $this->data['store_ids'];
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function isInStore(int $storeId): bool
    {
        return in_array(0, $this->getStoreIds(), true) || in_array($storeId, $this->getStoreIds(), true);
    }

    /**
     * @param array<int|string, mixed> $filters
     * @return array<string, string[]>
     */
    private function filterList(array $filters): array
    {
        $list = [];
        foreach ($filters as $key => $values) {
            $values = array_values(array_filter(array_map('strval', (array) $values), static fn (string $v): bool => $v !== ''));
            if ($values !== []) {
                $list[(string) $key] = $values;
            }
        }

        return $list;
    }
}
