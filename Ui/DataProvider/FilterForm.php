<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Ui\DataProvider;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Admin\FormScope;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Search\BuiltinClauses;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Config\Source\DisplayMode;
use Iranimij\OpenFilter\Model\Config\Validator;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Model\Url\AttributeSlugScope;
use Magento\Framework\Api\Filter as CriteriaFilter;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Data and fields of the filter form for one scope (Magento's store switcher, request "store"): all store views
 * edit the settings, the shared label and the shared URL names; a store view edits only its own label and URL
 * names next to the shared ones (read only), so the form keeps the same width for any number of store views.
 * The form asks for one filter by its key (request "code"; the meta is built before the form adds its filter,
 * so it reads the request).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class FilterForm extends AbstractDataProvider
{
    /** Settings that apply to every store view: read only in a store view (fieldset => fields). */
    public const GLOBAL_FIELDS = [
        'looks' => ['display_mode', 'ranges', 'tree_depth'],
        'behaves' => [
            'active', 'multiselect', 'logic', 'visible_options', 'search_threshold', 'expanded', 'sort_by',
            'hide_single_option', 'position_override',
        ],
        'where' => ['category_mode', 'category_ids', 'depends_on', 'depends_values'],
        'seo' => ['seo_indexable'],
    ];

    /** Input template that names table inputs after their column and option (no visible label in a cell). */
    private const ROW_INPUT = 'Iranimij_OpenFilter/form/element/row-input';

    /** Read-only cells of the options table (Review #1). */
    private const ROW_TEXT = 'Iranimij_OpenFilter/form/element/row-text';

    /** A filter with more options than this can be waited for with any pick only (keeps the form light). */
    private const MAX_PARENT_VALUES = 200;

    /** @var string|null key of the filter the form shows */
    private ?string $key = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param FilterConfigRepositoryInterface $repository
     * @param FilterConfigProvider $provider
     * @param AttributeSlugScope $attributeSlugs
     * @param FilterConfigStorage $configStorage
     * @param SlugStorage $slugStorage
     * @param Validator $validator
     * @param DisplayMode $displayModes
     * @param FormScope $formScope
     * @param RequestInterface $request
     * @param Defaults $defaults built-in filter names (the filters to wait for)
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
        private readonly AttributeSlugScope $attributeSlugs,
        private readonly FilterConfigStorage $configStorage,
        private readonly SlugStorage $slugStorage,
        private readonly Validator $validator,
        private readonly DisplayMode $displayModes,
        private readonly FormScope $formScope,
        private readonly RequestInterface $request,
        private readonly Defaults $defaults,
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
        $scope = $this->scope();
        $attributeId = $config->getAttributeId();
        $expanded = $config->getExpanded();
        $data = [
            'key' => $key,
            'store' => (string) $scope,
            'code' => $config->getCode(),
            'display_mode' => $config->getDisplayMode(),
            'multiselect' => $config->isMultiselect() ? '1' : '0',
            'logic' => $config->getLogic(),
            'ranges' => (string) $config->getRanges(),
            'tree_depth' => (string) $config->getTreeDepth(),
            'category_mode' => $config->getCategoryMode(),
            'category_ids' => array_map('strval', $config->getCategoryIds()),
            'visible_options' => $config->getVisibleOptions(),
            'search_threshold' => $config->getSearchThreshold(),
            'expanded' => $expanded === null ? 'auto' : (string) (int) $expanded,
            'sort_by' => $config->getSortBy(),
            'hide_single_option' => $config->isHideSingleOption() ? '1' : '0',
            'seo_indexable' => $config->isSeoIndexable() ? '1' : '0',
            'active' => $config->isActive() ? '1' : '0',
            'position_override' => $config->getPositionOverride(),
            'depends_on' => (string) $config->getDependsOn(),
            'depends_values' => $config->getDependsValues(),
        ];
        $labels = $this->configStorage->labelOverrides($config->isBuiltin() ? null : $attributeId, $config->getCode());
        $tooltips = $this->configStorage->tooltipOverrides(
            $config->isBuiltin() ? null : $attributeId,
            $config->getCode()
        );
        foreach (array_unique([0, $scope]) as $storeId) {
            $data['label_' . $storeId] = $labels[$storeId] ?? null;
            $data['tooltip_' . $storeId] = $tooltips[$storeId] ?? null;
        }
        if ($this->hasUrlNames($config) && $attributeId !== null) {
            $data['url_name'] = $this->attributeSlugs->own($attributeId, 0);
            if ($scope !== 0) {
                $data['url_name_store'] = $this->attributeSlugs->own($attributeId, $scope);
            }
            $data['option_urls'] = $this->optionRows($attributeId, $scope);
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
        $scope = $this->scope();
        $stores = $this->formScope->stores();
        $multiStore = !$this->formScope->isSingleStore();
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
        $visible = [
            ['looks', 'ranges', $this->hasRanges($config)],
            ['looks', 'tree_depth', $config->isBuiltin() && $config->getCode() === Filter::BUILTIN_CATEGORY],
            ['behaves', 'logic', !$config->isBuiltin() && in_array($config->getInputType(), ['select', 'multiselect'], true)],
        ];
        foreach ($visible as [$fieldset, $field, $shown]) {
            if (!$shown) {
                $meta[$fieldset]['children'][$field]['arguments']['data']['config']['visible'] = false;
            }
        }
        $meta['behaves']['children']['position_override']['arguments']['data']['config']['notice'] = __(
            'Empty = the position from layered navigation (now %1). Lower numbers come first.',
            $config->getPosition()
        );
        if ($multiStore) {
            foreach (self::GLOBAL_FIELDS as $fieldset => $fields) {
                foreach ($fields as $field) {
                    $settings = ['scopeLabel' => (string) __('[GLOBAL]')];
                    if ($scope !== 0) {
                        $settings['disabled'] = true;
                    }
                    $meta = $this->merge($meta, [$fieldset, 'children', $field], $settings);
                }
            }
        }

        $meta = $this->dependencyMeta($meta, $config);
        $meta = $this->labelMeta($meta, $scope, $stores, $multiStore);
        if (!$this->hasUrlNames($config)) {
            $meta['seo']['children']['url_name']['arguments']['data']['config']['visible'] = false;
            $meta['seo']['children']['option_urls']['arguments']['data']['config']['visible'] = false;

            return $meta;
        }

        return $this->urlNameMeta($meta, $scope, $stores, $multiStore);
    }

    /**
     * The filters this one can wait for and their values (1.2).
     *
     * @param array<string, mixed> $meta
     * @param Filter $config
     * @return array<string, mixed>
     */
    private function dependencyMeta(array $meta, Filter $config): array
    {
        $own = $this->provider->keyOf($config);
        $parents = [['value' => '', 'label' => (string) __('Nothing (always shown)')]];
        $values = [];
        foreach ($this->provider->getForStore(0) as $key => $other) {
            if ($key === $own) {
                continue;
            }
            $label = $other->getLabel()
                ?? ($other->isBuiltin() ? (string) __($this->defaults->builtinLabel($other->getCode())) : $other->getCode());
            $parents[] = ['value' => $key, 'label' => sprintf('%s (%s)', $label, $key)];
            $values[$key] = $this->parentValues($other);
        }
        $meta = $this->merge($meta, ['where', 'children', 'depends_on'], ['options' => $parents]);

        return $this->merge($meta, ['where', 'children', 'depends_values'], ['optionsByParent' => $values]);
    }

    /**
     * Values another filter can be waited for with: options, rating levels, on/off, the merchant's price ranges.
     *
     * @param Filter $parent
     * @return array<int, array{value: string, label: string}>
     */
    private function parentValues(Filter $parent): array
    {
        $attributeId = $parent->getAttributeId();
        if (!$parent->isBuiltin() && $attributeId !== null) {
            if (!in_array($parent->getInputType(), ['select', 'multiselect'], true)) {
                return [];
            }
            $labels = $this->slugStorage->optionLabels($attributeId)['admin'];
            if (count($labels) > self::MAX_PARENT_VALUES) {
                return [];
            }
            $options = [];
            foreach ($labels as $optionId => $label) {
                $options[] = ['value' => (string) $optionId, 'label' => (string) $label];
            }

            return $options;
        }

        return match ($parent->getCode()) {
            Filter::BUILTIN_RATING => array_map(
                static fn (int $stars): array => [
                    'value' => (string) $stars,
                    'label' => $stars === 1 ? (string) __('%1 star & up', $stars) : (string) __('%1 stars & up', $stars),
                ],
                BuiltinClauses::STARS
            ),
            Filter::BUILTIN_STOCK, Filter::BUILTIN_SALE, Filter::BUILTIN_NEW => [['value' => '1', 'label' => (string) __('Yes')]],
            Filter::BUILTIN_PRICE => $parent->getDisplayMode() === Filter::DISPLAY_RANGES && $parent->getRanges() !== null
                ? array_map(
                    static fn (string $range): array => ['value' => trim($range), 'label' => trim($range)],
                    explode(',', $parent->getRanges())
                )
                : [],
            default => [],
        };
    }

    /**
     * Label for all store views; in a store view also the store view's own label.
     *
     * @param array<string, mixed> $meta
     * @param int $scope
     * @param array<int, string> $stores
     * @param bool $multiStore
     * @return array<string, mixed>
     */
    private function labelMeta(array $meta, int $scope, array $stores, bool $multiStore): array
    {
        $meta['labels']['arguments']['data']['config']['label'] = __('Label and tooltip');
        $shared = ['scopeLabel' => $multiStore ? (string) __('[STORE VIEW]') : null];
        if ($scope === 0) {
            $meta = $this->merge($meta, ['labels', 'children', 'tooltip_0'], array_filter($shared));
            if ($multiStore) {
                $shared['notice'] = (string) __('Empty = the attribute\'s own label. Pick a store view at the top for its own.');
            }

            return $this->merge($meta, ['labels', 'children', 'label_0'], array_filter($shared));
        }
        $meta = $this->merge($meta, ['labels', 'children', 'label_0'], array_filter($shared + ['disabled' => true]));
        $meta = $this->merge($meta, ['labels', 'children', 'tooltip_0'], array_filter($shared + ['disabled' => true]));
        $meta['labels']['children']['tooltip_' . $scope] = $this->field([
            'formElement' => 'textarea',
            'label' => (string) __('Tooltip in "%1"', $stores[$scope]),
            'dataScope' => 'tooltip_' . $scope,
            'sortOrder' => 60,
            'placeholder' => (string) __('Same as for all store views'),
            'notice' => (string) __('Empty = the tooltip for all store views.'),
            'scopeLabel' => (string) __('[STORE VIEW]'),
        ]);
        $meta['labels']['children']['label_' . $scope] = $this->field([
            'label' => (string) __('Label in "%1"', $stores[$scope]),
            'dataScope' => 'label_' . $scope,
            'sortOrder' => 20,
            'placeholder' => (string) __('Same as for all store views'),
            'notice' => (string) __('Empty = the label for all store views.'),
            'scopeLabel' => (string) __('[STORE VIEW]'),
        ]);

        return $meta;
    }

    /**
     * URL name of the filter and its options: shared names for all store views, or a store view's own names
     * next to the shared ones (read only). Same columns for any number of store views.
     *
     * @param array<string, mixed> $meta
     * @param int $scope
     * @param array<int, string> $stores
     * @param bool $multiStore
     * @return array<string, mixed>
     */
    private function urlNameMeta(array $meta, int $scope, array $stores, bool $multiStore): array
    {
        $scopeLabel = $multiStore ? (string) __('[STORE VIEW]') : null;
        if ($scope === 0) {
            if ($scopeLabel !== null) {
                $meta = $this->merge($meta, ['seo', 'children', 'url_name'], ['scopeLabel' => $scopeLabel]);
            }

            return $meta;
        }
        $store = $stores[$scope];
        $meta = $this->merge($meta, ['seo', 'children', 'url_name'], [
            'label' => (string) __('URL name (all store views)'),
            'disabled' => true,
            'scopeLabel' => $scopeLabel,
        ]);
        $meta['seo']['children']['url_name_store'] = $this->field([
            'label' => (string) __('URL name in "%1"', $store),
            'dataScope' => 'url_name_store',
            'sortOrder' => 25,
            'placeholder' => (string) __('Same as for all store views'),
            'notice' => (string) __('Empty = same as for all store views, also for the option URL names below.'),
            'scopeLabel' => $scopeLabel,
        ]);
        $meta['seo']['children']['option_urls']['arguments']['data']['config']['label'] = __(
            'URL names of the options in "%1"',
            $store
        );
        $record = [
            'option_store_label' => $this->field([
                'label' => (string) __('Label in "%1"', $store),
                'dataScope' => 'option_store_label',
                'sortOrder' => 25,
                'disabled' => true,
                'elementTmpl' => self::ROW_TEXT,
            ]),
            'url_name_0' => ['arguments' => ['data' => ['config' => ['disabled' => true, 'elementTmpl' => self::ROW_TEXT]]]],
            'url_name_' . $scope => $this->field([
                'label' => (string) __('URL name in "%1"', $store),
                'dataScope' => 'url_name_' . $scope,
                'sortOrder' => 40,
                'elementTmpl' => self::ROW_INPUT,
                'placeholder' => (string) __('Same as for all store views'),
            ]),
            'regenerate' => ['arguments' => ['data' => ['config' => [
                'label' => (string) __('Generate again from the label in "%1"', $store),
            ]]]],
        ];
        $meta['seo']['children']['option_urls']['children']['record']['children'] = $record;

        return $meta;
    }

    /**
     * @param array<string, mixed> $settings label, dataScope, sortOrder and optional formElement, placeholder,
     *     notice, scopeLabel, disabled, elementTmpl
     * @return array<string, mixed>
     */
    private function field(array $settings): array
    {
        $config = array_filter($settings, static fn ($value): bool => $value !== null) + [
            'componentType' => 'field',
            'formElement' => 'input',
            'dataType' => 'text',
        ];

        return ['arguments' => ['data' => ['config' => $config]]];
    }

    /**
     * Adds settings to the config of a component given by its path in the meta.
     *
     * @param array<string, mixed> $meta
     * @param string[] $path
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function merge(array $meta, array $path, array $settings): array
    {
        $node = &$meta;
        foreach ($path as $step) {
            $node = &$node[$step];
        }
        $node['arguments']['data']['config'] = array_filter($settings, static fn ($value): bool => $value !== null)
            + ($node['arguments']['data']['config'] ?? []);
        unset($node);

        return $meta;
    }

    /**
     * Option rows of one scope: label, name for all store views and, in a store view, its label and its own name
     * (empty = same as for all store views).
     *
     * @param int $attributeId
     * @param int $scope
     * @return array<int, array<string, mixed>>
     */
    private function optionRows(int $attributeId, int $scope): array
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
            if ($scope !== 0) {
                $row['option_store_label'] = $labels['stores'][$scope][$optionId] ?? $label;
                $row['url_name_' . $scope] = $slugs[$scope][$optionId] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Ranges fit price and decimal filters (the "Ranges" display type).
     *
     * @param Filter $config
     * @return bool
     */
    private function hasRanges(Filter $config): bool
    {
        return $config->isBuiltin()
            ? $config->getCode() === Filter::BUILTIN_PRICE
            : $config->getInputType() === 'decimal';
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
     * @return int store view the form edits, 0 = all store views
     */
    private function scope(): int
    {
        return $this->formScope->resolve($this->request->getParam(FormScope::PARAM));
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
