<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Api\Data\FilterConfigSearchResultsInterface;
use Iranimij\OpenFilter\Api\Data\FilterConfigSearchResultsInterfaceFactory;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\InputException;
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
     * @param FilterConfigList $list
     * @param FilterConfigSearchResultsInterfaceFactory $resultsFactory
     * @param DependencyRule $dependencies dependent filters (1.2): no loops, no unknown filter to wait for
     */
    public function __construct(
        private readonly FilterConfigProvider $provider,
        private readonly FilterConfigStorage $storage,
        private readonly Validator $validator,
        private readonly Defaults $defaults,
        private readonly FrontendInterface $cache,
        private readonly TypeListInterface $cacheTypeList,
        private readonly FilterConfigList $list,
        private readonly FilterConfigSearchResultsInterfaceFactory $resultsFactory,
        private readonly DependencyRule $dependencies
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
     * Labels are resolved for the admin: built-ins without their own label get the translated default label.
     *
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): FilterConfigSearchResultsInterface
    {
        $configs = array_map(
            fn (FilterConfig $config): FilterConfig => $config->getLabel() !== null ? $config : $config->with([
                'label' => $config->isBuiltin()
                    ? (string) __($this->defaults->builtinLabel($config->getCode()))
                    : $config->getCode(),
            ]),
            $this->provider->getForStore(Store::DEFAULT_STORE_ID)
        );
        [$items, $total] = $this->list->apply($configs, $searchCriteria);
        $results = $this->resultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($items);
        $results->setTotalCount($total);

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function save(FilterConfigInterface $config): FilterConfigInterface
    {
        $current = $this->current($this->provider->keyOf($config));
        $merged = $current->withBehaviourOf($config);
        $row = $merged->toRow();
        $attributeId = $current->getAttributeId();
        $optionCount = !$current->isBuiltin() && $attributeId !== null ? $this->storage->optionCount($attributeId) : 0;
        $this->validator->validate($merged, $optionCount);
        $problem = $this->dependencies->problem(
            $this->provider->keyOf($current),
            $merged->getDependsOn(),
            $this->provider->getForStore(Store::DEFAULT_STORE_ID)
        );
        if ($problem !== null) {
            throw new InputException($problem);
        }

        if ($current->isBuiltin()) {
            $this->storage->saveBuiltin($current->getCode(), $merged->isActive(), $row);
        } elseif ($attributeId !== null) {
            $this->storage->saveAttribute($attributeId, $row + ['is_active' => (int) $merged->isActive()]);
        }
        // Always written: the config being saved may be the provider's own cached object, so comparing with the
        // "current" one could see no change.
        $this->storage->saveCategoryRule($this->provider->keyOf($current), $merged->getCategoryMode(), $merged->getCategoryIds());
        $this->clean();

        return $this->current($this->provider->keyOf($config));
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
    public function saveTooltip(string $code, int $storeId, ?string $tooltip): void
    {
        $current = $this->current($code);
        $attributeId = $current->getAttributeId();
        $tooltip = $tooltip === null ? null : trim($tooltip);
        if ($current->isBuiltin()) {
            $this->storage->saveBuiltin($code, $current->isActive(), $current->toRow());
            $this->storage->saveBuiltinTooltip($code, $storeId, $tooltip);
        } elseif ($attributeId !== null) {
            $this->storage->saveAttributeTooltip($attributeId, $storeId, $tooltip);
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
