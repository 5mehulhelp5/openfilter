<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Ui\DataProvider;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Config\Source\DisplayMode;
use Iranimij\OpenFilter\Model\Config\Validator;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Framework\Api\Filter as CriteriaFilter;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Data and store-specific fields of the filter form. The form asks for one filter by its key (request "code";
 * the meta is built before the form adds its filter, so it reads the request).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class FilterForm extends AbstractDataProvider
{
    /** @var string|null key of the filter the form shows */
    private ?string $key = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param FilterConfigRepositoryInterface $repository
     * @param FilterConfigProvider $provider
     * @param SlugRepositoryInterface $slugs
     * @param FilterConfigStorage $configStorage
     * @param SlugStorage $slugStorage
     * @param Validator $validator
     * @param DisplayMode $displayModes
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly FilterConfigProvider $provider,
        private readonly SlugRepositoryInterface $slugs,
        private readonly FilterConfigStorage $configStorage,
        private readonly SlugStorage $slugStorage,
        private readonly Validator $validator,
        private readonly DisplayMode $displayModes,
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * The form asks for its record with an equality filter on the primary field.
     *
     * @param CriteriaFilter $filter
     * @return void
     */
    public function addFilter(CriteriaFilter $filter)
    {
        if ($filter->getField() === $this->getPrimaryFieldName()) {
            $this->key = (string) $filter->getValue();
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getData()
    {
        $config = $this->config();
        if ($config === null) {
            return [];
        }
        $key = $this->provider->keyOf($config);
        $attributeId = $config->getAttributeId();
        $expanded = $config->getExpanded();
        $data = [
            'key' => $key,
            'code' => $config->getCode(),
            'display_mode' => $config->getDisplayMode(),
            'multiselect' => $config->isMultiselect() ? '1' : '0',
            'visible_options' => $config->getVisibleOptions(),
            'search_threshold' => $config->getSearchThreshold(),
            'expanded' => $expanded === null ? 'auto' : (string) (int) $expanded,
            'sort_by' => $config->getSortBy(),
            'hide_single_option' => $config->isHideSingleOption() ? '1' : '0',
            'seo_indexable' => $config->isSeoIndexable() ? '1' : '0',
            'active' => $config->isActive() ? '1' : '0',
            'position_override' => $config->getPositionOverride(),
        ];
        foreach ($this->configStorage->labelOverrides($config->isBuiltin() ? null : $attributeId, $config->getCode())
            as $storeId => $label) {
            $data['label_' . $storeId] = $label;
        }
        if ($this->hasUrlNames($config) && $attributeId !== null) {
            $data['url_name'] = $this->slugs->getAttributeSlug($attributeId, 0);
            $data['option_urls'] = $this->optionRows($attributeId);
        }

        return [$key => $data];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta()
    {
        $meta = parent::getMeta();
        $config = $this->config();
        if ($config === null) {
            return $meta;
        }
        $attributeId = $config->getAttributeId();
        $optionCount = !$config->isBuiltin() && $attributeId !== null ? $this->configStorage->optionCount($attributeId) : 0;
        $labels = $this->displayModes->labels();
        $options = [];
        foreach ($this->validator->allowedModes($config, $optionCount) as $mode) {
            $options[] = ['value' => $mode, 'label' => $labels[$mode]];
        }
        $meta['looks']['children']['display_mode']['arguments']['data']['config']['options'] = $options;
        if ($this->request->getParam('why')) {
            $meta['why']['arguments']['data']['config']['opened'] = true;
        }
        $meta['behaves']['children']['position_override']['arguments']['data']['config']['notice'] = __(
            'Empty = the position from layered navigation (now %1). Lower numbers come first.',
            $config->getPosition()
        );

        $stores = $this->stores();
        $sort = 20;
        foreach ($stores as $storeId => $name) {
            $meta['labels']['children']['label_' . $storeId] = $this->field(
                (string) __('Label in "%1"', $name),
                'label_' . $storeId,
                $sort += 10,
                (string) __('Empty = the label for all store views.')
            );
        }

        if (!$this->hasUrlNames($config)) {
            $meta['seo']['children']['url_name']['arguments']['data']['config']['visible'] = false;
            $meta['seo']['children']['option_urls']['arguments']['data']['config']['visible'] = false;
        } else {
            $sort = 30;
            $record = [];
            foreach ($stores as $storeId => $name) {
                $record['url_name_' . $storeId] = $this->field(
                    (string) __('URL name in "%1"', $name),
                    'url_name_' . $storeId,
                    $sort += 10,
                    null
                );
            }
            $meta['seo']['children']['option_urls']['children']['record']['children'] = $record;
        }

        return $meta;
    }

    /**
     * @param string $label
     * @param string $scope
     * @param int $sortOrder
     * @param string|null $notice
     * @return array<string, mixed>
     */
    private function field(string $label, string $scope, int $sortOrder, ?string $notice): array
    {
        $config = [
            'componentType' => 'field',
            'formElement' => 'input',
            'dataType' => 'text',
            'label' => $label,
            'dataScope' => $scope,
            'sortOrder' => $sortOrder,
        ];
        if ($notice !== null) {
            $config['notice'] = $notice;
        }

        return ['arguments' => ['data' => ['config' => $config]]];
    }

    /**
     * Option rows: label, name for all store views, names per store view (empty = same as for all store views).
     *
     * @param int $attributeId
     * @return array<int, array<string, mixed>>
     */
    private function optionRows(int $attributeId): array
    {
        $labels = $this->slugStorage->optionLabels($attributeId);
        $slugs = $this->slugStorage->optionSlugRows($attributeId);
        $rows = [];
        foreach ($labels['admin'] as $optionId => $label) {
            $row = [
                'option_id' => $optionId,
                'option_label' => $label,
                'url_name_0' => $slugs[0][$optionId] ?? '',
                'regenerate' => '0',
            ];
            foreach (array_keys($this->stores()) as $storeId) {
                $row['url_name_' . $storeId] = $slugs[$storeId][$optionId] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * URL names exist for attribute filters other than price (built-ins have fixed parameter names).
     *
     * @param Filter $config
     * @return bool
     */
    private function hasUrlNames(Filter $config): bool
    {
        return !$config->isBuiltin() && $config->getAttributeId() !== null;
    }

    /**
     * @return array<int, string> store view id => name
     */
    private function stores(): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            $stores[(int) $store->getId()] = (string) $store->getName();
        }
        ksort($stores);

        return $stores;
    }

    /**
     * @return Filter|null
     */
    private function config(): ?Filter
    {
        $key = $this->key ?? (string) $this->request->getParam($this->getRequestFieldName());
        if ($key === '') {
            return null;
        }
        try {
            return $this->repository->getByCode($key);
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
