<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Saves a filter's URL name in one scope as the filter form edits it: for all store views the name is required;
 * in a store view an empty name (or the name for all store views) removes the store view's own name, so it
 * follows the name for all store views again (Spec Slug: store view rows only where different).
 */
class AttributeSlugScope
{
    private const FULL_PAGE_CACHE = 'full_page';

    /**
     * @param SlugRepositoryInterface $slugs
     * @param SlugStorage $storage
     * @param SlugProvider $provider
     * @param FrontendInterface $cache the "openfilter" cache type
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        private readonly SlugRepositoryInterface $slugs,
        private readonly SlugStorage $storage,
        private readonly SlugProvider $provider,
        private readonly FrontendInterface $cache,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    /**
     * The name the store view has of its own, or '' when it uses the name for all store views.
     *
     * @param int $attributeId
     * @param int $storeId
     * @return string
     */
    public function own(int $attributeId, int $storeId): string
    {
        if ($storeId === 0) {
            return $this->slugs->getAttributeSlug($attributeId, 0);
        }
        $name = $this->slugs->getAttributeSlug($attributeId, $storeId);

        return $name === $this->slugs->getAttributeSlug($attributeId, 0) ? '' : $name;
    }

    /**
     * @param int $attributeId
     * @param int $storeId 0 = all store views
     * @param string $name
     * @return void
     * @throws LocalizedException when the name is missing for all store views, malformed, reserved or taken
     */
    public function save(int $attributeId, int $storeId, string $name): void
    {
        $name = trim($name);
        if ($storeId === 0) {
            if ($name === '') {
                throw new LocalizedException(__('The URL name for all store views cannot be empty.'));
            }
            if ($name !== $this->slugs->getAttributeSlug($attributeId, 0)) {
                $this->slugs->saveAttributeSlug($attributeId, 0, $name);
            }

            return;
        }
        if ($name === '' || $name === $this->slugs->getAttributeSlug($attributeId, 0)) {
            if ($this->own($attributeId, $storeId) !== '') {
                $this->storage->writeAttributeSlug($attributeId, $storeId, null);
                $this->cache->clean();
                $this->provider->reset();
                $this->cacheTypeList->invalidate(self::FULL_PAGE_CACHE);
            }

            return;
        }
        if ($name !== $this->slugs->getAttributeSlug($attributeId, $storeId)) {
            $this->slugs->saveAttributeSlug($attributeId, $storeId, $name);
        }
    }
}
