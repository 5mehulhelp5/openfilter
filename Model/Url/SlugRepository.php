<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Generates and saves URL names. Store view rows exist only where the store view's name differs from the admin one.
 */
class SlugRepository implements SlugRepositoryInterface
{
    private const FULL_PAGE_CACHE = 'full_page';

    /**
     * @param SlugStorage $storage
     * @param SlugGenerator $generator
     * @param SlugProvider $provider
     * @param StoreManagerInterface $storeManager
     * @param FrontendInterface $cache the "openfilter" cache type
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        private readonly SlugStorage $storage,
        private readonly SlugGenerator $generator,
        private readonly SlugProvider $provider,
        private readonly StoreManagerInterface $storeManager,
        private readonly FrontendInterface $cache,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getOptionSlugs(int $attributeId, int $storeId): array
    {
        return $this->provider->forStore($storeId)['options'][$attributeId] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getAttributeSlug(int $attributeId, int $storeId): string
    {
        $slug = $this->provider->forStore($storeId)['attributes'][$attributeId] ?? null;
        if ($slug === null) {
            throw new NoSuchEntityException(__('Attribute %1 is not used in layered navigation.', $attributeId));
        }

        return $slug;
    }

    /**
     * @inheritDoc
     */
    public function saveOptionSlug(int $optionId, int $storeId, string $slug): void
    {
        if (!$this->generator->isValidOptionSlug($slug)) {
            throw new LocalizedException(
                __('A URL name may contain lowercase letters, digits and single dashes (at most 64 characters).')
            );
        }
        $attributeId = $this->storage->attributeOfOption($optionId);
        if ($attributeId === null) {
            throw new NoSuchEntityException(__('Option %1 does not exist.', $optionId));
        }
        $rows = $this->storage->optionSlugRows($attributeId);
        $effective = $storeId === 0 ? ($rows[0] ?? []) : array_replace($rows[0] ?? [], $rows[$storeId] ?? []);
        unset($effective[$optionId]);
        if (in_array($slug, $effective, true)) {
            throw new LocalizedException(__('Another option of this filter already uses the URL name "%1".', $slug));
        }
        if ($storeId !== 0 && ($rows[0][$optionId] ?? null) === $slug) {
            $this->storage->writeOptionSlugs($attributeId, $storeId, [], [$optionId]);
        } else {
            $this->storage->writeOptionSlugs($attributeId, $storeId, [$optionId => $slug]);
        }
        $this->clean();
    }

    /**
     * @inheritDoc
     */
    public function saveAttributeSlug(int $attributeId, int $storeId, string $slug): void
    {
        if (preg_match('/^[a-z0-9]+([_-][a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 64) {
            throw new LocalizedException(
                __('A URL name may contain lowercase letters, digits, dashes and underscores (at most 64 characters).')
            );
        }
        if (in_array($slug, SlugGenerator::RESERVED, true)) {
            throw new LocalizedException(__('"%1" is reserved by Magento or a built-in filter.', $slug));
        }
        $others = $this->provider->forStore($storeId)['attributes'];
        if (!isset($others[$attributeId])) {
            throw new NoSuchEntityException(__('Attribute %1 is not used in layered navigation.', $attributeId));
        }
        unset($others[$attributeId]);
        if (in_array($slug, $others, true)) {
            throw new LocalizedException(__('Another filter already uses the URL name "%1".', $slug));
        }
        $this->storage->writeAttributeSlug($attributeId, $storeId, $slug);
        $this->clean();
    }

    /**
     * @inheritDoc
     */
    public function regenerate(int $attributeId, ?int $storeId = null, bool $overwrite = false): void
    {
        $labels = $this->storage->optionLabels($attributeId);
        if ($labels['admin'] === []) {
            return;
        }
        $rows = $this->storage->optionSlugRows($attributeId);
        $existingAdmin = $overwrite ? [] : array_intersect_key($rows[0] ?? [], $labels['admin']);
        $admin = $this->generator->forOptions($labels['admin'], $existingAdmin);
        $regenerated = array_keys(array_diff_key($admin, $existingAdmin));
        if ($regenerated === []) {
            return;
        }
        if ($storeId === null || $storeId === 0) {
            $this->storage->writeOptionSlugs($attributeId, 0, array_intersect_key($admin, array_flip($regenerated)));
        }
        $storeIds = $storeId === null ? $this->storeIds() : ($storeId === 0 ? [] : [$storeId]);
        foreach ($storeIds as $id) {
            $this->regenerateStore($attributeId, $id, $labels, $admin, $rows[$id] ?? [], $regenerated);
        }
        $this->clean();
    }

    /**
     * Store view names for the regenerated options; options whose name equals the admin name get no row.
     *
     * @param int $attributeId
     * @param int $storeId
     * @param array{admin: array<int, string>, stores: array<int, array<int, string>>} $labels
     * @param array<int, string> $admin effective admin slugs
     * @param array<int, string> $storeRows existing store rows
     * @param int[] $regenerated option ids to (re)name
     * @return void
     */
    private function regenerateStore(
        int $attributeId,
        int $storeId,
        array $labels,
        array $admin,
        array $storeRows,
        array $regenerated
    ): void {
        $effective = array_replace($admin, array_intersect_key($storeRows, $admin));
        $fixed = array_diff_key($effective, array_flip($regenerated));
        $storeLabels = [];
        foreach ($labels['admin'] as $optionId => $label) {
            $storeLabels[$optionId] = $labels['stores'][$storeId][$optionId] ?? $label;
        }
        $slugs = $this->generator->forOptions($storeLabels, $fixed);
        $write = [];
        $remove = [];
        foreach ($regenerated as $optionId) {
            if ($slugs[$optionId] === $admin[$optionId]) {
                $remove[] = $optionId;
            } else {
                $write[$optionId] = $slugs[$optionId];
            }
        }
        $this->storage->writeOptionSlugs($attributeId, $storeId, $write, $remove);
    }

    /**
     * @return int[] store view ids (without admin)
     */
    private function storeIds(): array
    {
        return array_map(static fn ($store): int => (int) $store->getId(), array_values($this->storeManager->getStores()));
    }

    /**
     * Cleans the OpenFilter cache and marks the full page cache invalid (links on filtered pages change).
     *
     * @return void
     */
    private function clean(): void
    {
        $this->cache->clean();
        $this->provider->reset();
        $this->cacheTypeList->invalidate(self::FULL_PAGE_CACHE);
    }
}
