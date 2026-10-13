<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;

/**
 * Saves filter settings and keeps the per-store cache and the full page cache honest.
 */
class FilterConfigRepository implements FilterConfigRepositoryInterface
{
    /** Core's full page cache type (Magento_PageCache), named here to avoid a module dependency. */
    private const FULL_PAGE_CACHE = 'full_page';

    /**
     * @param FilterConfigProvider $provider
     * @param FilterConfigStorage $storage
     * @param Validator $validator
     * @param Defaults $defaults
     * @param FrontendInterface $cache the "openfilter" cache type
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        private readonly FilterConfigProvider $provider,
        private readonly FilterConfigStorage $storage,
        private readonly Validator $validator,
        private readonly Defaults $defaults,
        private readonly FrontendInterface $cache,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getByCode(string $code): FilterConfigInterface
    {
        return $this->current($code);
    }

    /**
     * @inheritDoc
     */
    public function getByAttributeId(int $attributeId): FilterConfigInterface
    {
        foreach ($this->provider->getForStore(Store::DEFAULT_STORE_ID) as $config) {
            if ($config->getAttributeId() === $attributeId) {
                return $config;
            }
        }
        throw new NoSuchEntityException(__('Attribute %1 is not used in layered navigation.', $attributeId));
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        return array_values($this->provider->getForStore(Store::DEFAULT_STORE_ID));
    }

    /**
     * @inheritDoc
     */
    public function save(FilterConfigInterface $config): FilterConfigInterface
    {
        $current = $this->current($config->getCode());
        $merged = $current->withBehaviourOf($config);
        $row = $merged->toRow();
        $attributeId = $current->getAttributeId();
        $optionCount = !$current->isBuiltin() && $attributeId !== null ? $this->storage->optionCount($attributeId) : 0;
        $this->validator->validate($merged, $optionCount);

        if ($current->isBuiltin()) {
            $this->storage->saveBuiltin($current->getCode(), $merged->isActive(), $row);
        } elseif ($attributeId !== null) {
            $this->storage->saveAttribute($attributeId, $row);
        }
        $this->clean();

        return $this->getByCode($config->getCode());
    }

    /**
     * @inheritDoc
     */
    public function saveLabel(string $code, int $storeId, ?string $label): void
    {
        $current = $this->current($code);
        $attributeId = $current->getAttributeId();
        if ($current->isBuiltin()) {
            $this->storage->saveBuiltin($code, $current->isActive(), $current->toRow());
            $this->storage->saveBuiltinLabel($code, $storeId, $label);
        } elseif ($attributeId !== null) {
            $this->storage->saveAttributeLabel($attributeId, $storeId, $label);
        }
        $this->clean();
    }

    /**
     * @inheritDoc
     */
    public function resetToDefault(string $code): FilterConfigInterface
    {
        $current = $this->current($code);
        $attributeId = $current->getAttributeId();
        if ($current->isBuiltin()) {
            $default = $this->defaults->forBuiltin($code);
            $this->storage->saveBuiltin($code, $default->isActive(), $default->toRow());
        } elseif ($attributeId !== null) {
            $this->storage->deleteAttribute($attributeId);
        }
        $this->clean();

        return $this->getByCode($code);
    }

    /**
     * The stored settings of one filter (admin scope).
     *
     * @param string $code
     * @return FilterConfig
     * @throws NoSuchEntityException
     */
    private function current(string $code): FilterConfig
    {
        $all = $this->provider->getForStore(Store::DEFAULT_STORE_ID);
        if (!isset($all[$code])) {
            throw new NoSuchEntityException(__('No filter with the code "%1".', $code));
        }

        return $all[$code];
    }

    /**
     * Cleans the OpenFilter cache and marks the full page cache invalid (filtered pages render differently now).
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
