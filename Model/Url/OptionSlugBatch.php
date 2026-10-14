<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Saves the option URL names of one attribute as one set, for all store views and each store view: the whole set
 * is checked first (shape, one name per option, no name twice), so two options can swap names and an error writes
 * nothing. Rows ticked for "generate again" get fresh names from their labels. Store view rows exist only where
 * the name differs from the one for all store views (Spec Slug).
 */
class OptionSlugBatch
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
     * @param int $attributeId
     * @param array<int, array<int, string>> $names option id => store id => URL name (store 0 = all store views,
     *     required; '' in a store view = same as all store views); options or stores left out keep their names
     * @param int[] $regenerate option ids whose names are generated again from their labels
     * @return void
     * @throws LocalizedException when a name has the wrong shape, is missing or is used twice (nothing is written)
     */
    public function save(int $attributeId, array $names, array $regenerate = []): void
    {
        $labels = $this->storage->optionLabels($attributeId);
        $rows = $this->storage->optionSlugRows($attributeId);
        $regenerate = array_flip(array_map('intval', $regenerate));

        $admin = $this->resolve($labels['admin'], $labels['admin'], $rows[0] ?? [], $names, 0, $regenerate, []);
        $writes = [0 => [array_diff_assoc($admin, $rows[0] ?? []), []]];
        foreach ($this->storeIds() as $storeId) {
            $storeLabels = array_replace(
                $labels['admin'],
                array_intersect_key($labels['stores'][$storeId] ?? [], $labels['admin'])
            );
            $current = array_replace($admin, array_intersect_key($rows[$storeId] ?? [], $admin));
            $final = $this->resolve($labels['admin'], $storeLabels, $current, $names, $storeId, $regenerate, $admin);
            $write = [];
            $remove = [];
            foreach ($final as $optionId => $slug) {
                $stored = $rows[$storeId][$optionId] ?? null;
                if ($slug === $admin[$optionId]) {
                    if ($stored !== null) {
                        $remove[] = $optionId;
                    }
                } elseif ($slug !== $stored) {
                    $write[$optionId] = $slug;
                }
            }
            $writes[$storeId] = [$write, $remove];
        }

        $changed = false;
        foreach ($writes as $storeId => [$write, $remove]) {
            if ($write !== [] || $remove !== []) {
                $this->storage->writeOptionSlugs($attributeId, $storeId, $write, $remove);
                $changed = true;
            }
        }
        if ($changed) {
            $this->cache->clean();
            $this->provider->reset();
            $this->cacheTypeList->invalidate(self::FULL_PAGE_CACHE);
        }
    }

    /**
     * The final names of one store view (store 0 = all store views), checked as a set.
     *
     * @param array<int, string> $adminLabels option id => admin label (option order; names errors)
     * @param array<int, string> $labels option id => label of this store view
     * @param array<int, string> $current option id => current effective name
     * @param array<int, array<int, string>> $posted
     * @param int $storeId
     * @param array<int, int> $regenerate option id => index
     * @param array<int, string> $inherited names for all store views ('' in a store view falls back to them)
     * @return array<int, string> option id => name, for every option
     * @throws LocalizedException
     */
    private function resolve(
        array $adminLabels,
        array $labels,
        array $current,
        array $posted,
        int $storeId,
        array $regenerate,
        array $inherited
    ): array {
        $fixed = [];
        foreach (array_keys($adminLabels) as $optionId) {
            if (isset($regenerate[$optionId])) {
                continue;
            }
            if (!isset($posted[$optionId][$storeId])) {
                if (isset($current[$optionId])) {
                    $fixed[$optionId] = $current[$optionId];
                }
                continue;
            }
            $slug = trim((string) $posted[$optionId][$storeId]);
            if ($slug === '' && $storeId !== 0) {
                $slug = $inherited[$optionId];
            } elseif (!$this->generator->isValidOptionSlug($slug)) {
                throw new LocalizedException(__(
                    '"%1": a URL name may contain lowercase letters, digits and single dashes (at most 64 characters).',
                    $adminLabels[$optionId]
                ));
            }
            $fixed[$optionId] = $slug;
        }
        foreach (array_count_values($fixed) as $slug => $count) {
            if ($count > 1) {
                throw new LocalizedException(
                    __('Another option of this filter already uses the URL name "%1".', (string) $slug)
                );
            }
        }

        return $this->generator->forOptions($labels, $fixed);
    }

    /**
     * @return int[] store view ids (without admin)
     */
    private function storeIds(): array
    {
        return array_map(static fn ($store): int => (int) $store->getId(), array_values($this->storeManager->getStores()));
    }
}
