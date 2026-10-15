<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;

/**
 * Plain value object behind FilterConfigInterface. toArray()/fromArray() round-trip it through the cache.
 */
class FilterConfig implements FilterConfigInterface
{
    /** Columns stored in openfilter_attribute / openfilter_builtin. */
    public const BEHAVIOUR_COLUMNS = [
        'display_mode',
        'multiselect',
        'logic',
        'visible_options',
        'search_threshold',
        'expanded',
        'sort_by',
        'hide_single_option',
        'seo_indexable',
        'position_override',
        'ranges',
        'tree_depth',
    ];

    /** @var array<string, true> keys given through setters since construction (the fields of a REST request) */
    private array $sent = [];

    /** @var array<string, mixed> */
    private array $data = [
        'code' => '',
        'builtin' => false,
        'attribute_id' => null,
        'input_type' => 'select',
        'swatch_type' => null,
        'display_mode' => self::DISPLAY_LIST,
        'multiselect' => true,
        'logic' => self::LOGIC_OR,
        'visible_options' => 6,
        'search_threshold' => 12,
        'expanded' => null,
        'sort_by' => self::SORT_POSITION,
        'hide_single_option' => true,
        'seo_indexable' => false,
        'position_override' => null,
        'active' => true,
        'position' => 0,
        'label' => null,
        'ranges' => null,
        'tree_depth' => self::TREE_DEPTH_DEFAULT,
        'tooltip' => null,
        'category_mode' => self::CATEGORIES_ALL,
        'category_ids' => [],
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $this->data)) {
                $this->data[$key] = $value;
            }
        }
        $this->normalise();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * The behaviour columns as stored in the database.
     *
     * @return array<string, int|string|null>
     */
    public function toRow(): array
    {
        $expanded = $this->getExpanded();

        return [
            'display_mode' => $this->getDisplayMode(),
            'multiselect' => (int) $this->isMultiselect(),
            'logic' => $this->getLogic(),
            'visible_options' => $this->getVisibleOptions(),
            'search_threshold' => $this->getSearchThreshold(),
            'expanded' => $expanded === null ? null : (int) $expanded,
            'sort_by' => $this->getSortBy(),
            'hide_single_option' => (int) $this->isHideSingleOption(),
            'seo_indexable' => (int) $this->isSeoIndexable(),
            'position_override' => $this->getPositionOverride(),
            'ranges' => $this->getRanges(),
            'tree_depth' => $this->getTreeDepth(),
        ];
    }

    /**
     * A copy carrying the behaviour settings of any FilterConfigInterface implementation.
     *
     * @param FilterConfigInterface $config
     * @return self
     */
    public function withBehaviourOf(FilterConfigInterface $config): self
    {
        $copy = clone $this;
        $copy->setDisplayMode($config->getDisplayMode())
            ->setMultiselect($config->isMultiselect())
            ->setLogic($config->getLogic())
            ->setVisibleOptions($config->getVisibleOptions())
            ->setSearchThreshold($config->getSearchThreshold())
            ->setExpanded($config->getExpanded())
            ->setSortBy($config->getSortBy())
            ->setHideSingleOption($config->isHideSingleOption())
            ->setSeoIndexable($config->isSeoIndexable())
            ->setPositionOverride($config->getPositionOverride())
            ->setRanges($config->getRanges())
            ->setTreeDepth($config->getTreeDepth())
            ->setCategoryMode($config->getCategoryMode())
            ->setCategoryIds($config->getCategoryIds())
            ->setActive($config->isActive());

        return $copy;
    }

    /**
     * A copy carrying only the settings given to $config through its setters (REST: the fields of the request).
     *
     * @param FilterConfig $config
     * @return self
     */
    public function withSettingsOf(FilterConfig $config): self
    {
        $copy = clone $this;
        $copy->sent = [];
        foreach (array_keys($config->sent) as $key) {
            $copy->data[$key] = $config->data[$key];
        }

        return $copy;
    }

    /**
     * A copy with resolved values (or other data) replaced.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public function with(array $data): self
    {
        return new self(array_replace($this->data, $data));
    }

    /**
     * Unique map key: the code, or "attribute:<code>" for an attribute named like a built-in.
     *
     * @return string
     */
    public function getKey(): string
    {
        $code = $this->getCode();
        $clash = !$this->isBuiltin() && in_array($code, self::BUILTIN_CODES, true);

        return $clash ? FilterConfigProvider::ATTRIBUTE_KEY_PREFIX . $code : $code;
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return (string) $this->data['code'];
    }

    /**
     * @inheritDoc
     */
    public function isBuiltin(): bool
    {
        return (bool) $this->data['builtin'];
    }

    /**
     * @inheritDoc
     */
    public function getAttributeId(): ?int
    {
        return $this->data['attribute_id'] === null ? null : (int) $this->data['attribute_id'];
    }

    /**
     * @inheritDoc
     */
    public function getInputType(): string
    {
        return (string) $this->data['input_type'];
    }

    /**
     * @inheritDoc
     */
    public function getSwatchType(): ?string
    {
        return $this->data['swatch_type'] === null ? null : (string) $this->data['swatch_type'];
    }

    /**
     * @inheritDoc
     */
    public function getDisplayMode(): string
    {
        return (string) $this->data['display_mode'];
    }

    /**
     * @inheritDoc
     */
    public function setDisplayMode(string $displayMode): self
    {
        $this->data['display_mode'] = $displayMode;

        $this->sent['display_mode'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isMultiselect(): bool
    {
        return (bool) $this->data['multiselect'];
    }

    /**
     * @inheritDoc
     */
    public function setMultiselect(bool $multiselect): self
    {
        $this->data['multiselect'] = $multiselect;

        $this->sent['multiselect'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getLogic(): string
    {
        return (string) $this->data['logic'];
    }

    /**
     * @inheritDoc
     */
    public function setLogic(string $logic): self
    {
        $this->data['logic'] = $logic;

        $this->sent['logic'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getVisibleOptions(): int
    {
        return (int) $this->data['visible_options'];
    }

    /**
     * @inheritDoc
     */
    public function setVisibleOptions(int $visibleOptions): self
    {
        $this->data['visible_options'] = $visibleOptions;

        $this->sent['visible_options'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getSearchThreshold(): int
    {
        return (int) $this->data['search_threshold'];
    }

    /**
     * @inheritDoc
     */
    public function setSearchThreshold(int $searchThreshold): self
    {
        $this->data['search_threshold'] = $searchThreshold;

        $this->sent['search_threshold'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getExpanded(): ?bool
    {
        return $this->data['expanded'] === null ? null : (bool) $this->data['expanded'];
    }

    /**
     * @inheritDoc
     */
    public function setExpanded(?bool $expanded): self
    {
        $this->data['expanded'] = $expanded;

        $this->sent['expanded'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getSortBy(): string
    {
        return (string) $this->data['sort_by'];
    }

    /**
     * @inheritDoc
     */
    public function setSortBy(string $sortBy): self
    {
        $this->data['sort_by'] = $sortBy;

        $this->sent['sort_by'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isHideSingleOption(): bool
    {
        return (bool) $this->data['hide_single_option'];
    }

    /**
     * @inheritDoc
     */
    public function setHideSingleOption(bool $hide): self
    {
        $this->data['hide_single_option'] = $hide;

        $this->sent['hide_single_option'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isSeoIndexable(): bool
    {
        return (bool) $this->data['seo_indexable'];
    }

    /**
     * @inheritDoc
     */
    public function setSeoIndexable(bool $indexable): self
    {
        $this->data['seo_indexable'] = $indexable;

        $this->sent['seo_indexable'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPositionOverride(): ?int
    {
        return $this->data['position_override'] === null ? null : (int) $this->data['position_override'];
    }

    /**
     * @inheritDoc
     */
    public function setPositionOverride(?int $position): self
    {
        $this->data['position_override'] = $position;

        $this->sent['position_override'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return (bool) $this->data['active'];
    }

    /**
     * @inheritDoc
     */
    public function setActive(bool $active): self
    {
        $this->data['active'] = $active;

        $this->sent['active'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPosition(): int
    {
        return $this->getPositionOverride() ?? (int) $this->data['position'];
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): ?string
    {
        $label = $this->data['label'];

        return $label === null || $label === '' ? null : (string) $label;
    }

    /**
     * @inheritDoc
     */
    public function getRanges(): ?string
    {
        return $this->data['ranges'];
    }

    /**
     * @inheritDoc
     */
    public function setRanges(?string $ranges): self
    {
        $this->data['ranges'] = $ranges === null || trim($ranges) === '' ? null : trim($ranges);

        $this->sent['ranges'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getTreeDepth(): int
    {
        return (int) $this->data['tree_depth'];
    }

    /**
     * @inheritDoc
     */
    public function setTreeDepth(int $depth): self
    {
        $this->data['tree_depth'] = $depth;

        $this->sent['tree_depth'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getTooltip(): ?string
    {
        return $this->data['tooltip'];
    }

    /**
     * @inheritDoc
     */
    public function getCategoryMode(): string
    {
        return (string) $this->data['category_mode'];
    }

    /**
     * @inheritDoc
     */
    public function setCategoryMode(string $mode): self
    {
        $this->data['category_mode'] = $mode;

        $this->sent['category_mode'] = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCategoryIds(): array
    {
        return $this->data['category_ids'];
    }

    /**
     * @inheritDoc
     */
    public function setCategoryIds(array $categoryIds): self
    {
        $this->data['category_ids'] = array_values(array_unique(array_map('intval', $categoryIds)));
        sort($this->data['category_ids']);

        $this->sent['category_ids'] = true;
        return $this;
    }

    /**
     * Database rows carry strings; keep the internal types stable for the cache round trip.
     */
    private function normalise(): void
    {
        foreach (['builtin', 'multiselect', 'hide_single_option', 'seo_indexable', 'active'] as $key) {
            $this->data[$key] = (bool) $this->data[$key];
        }
        foreach (['visible_options', 'search_threshold', 'position', 'tree_depth'] as $key) {
            $this->data[$key] = (int) $this->data[$key];
        }
        foreach (['attribute_id', 'position_override'] as $key) {
            $this->data[$key] = $this->data[$key] === null || $this->data[$key] === '' ? null : (int) $this->data[$key];
        }
        foreach (['ranges', 'tooltip'] as $key) {
            $value = $this->data[$key];
            $this->data[$key] = $value === null || trim((string) $value) === '' ? null : (string) $value;
        }
        $this->data['category_ids'] = array_values(array_unique(array_map('intval', (array) $this->data['category_ids'])));
        sort($this->data['category_ids']);
        $expanded = $this->data['expanded'];
        $this->data['expanded'] = $expanded === null || $expanded === '' ? null : (bool) $expanded;
    }
}
