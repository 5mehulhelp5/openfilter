<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api\Data;

/**
 * How one filter looks and behaves: a filterable attribute or a built-in filter (rating, stock, sale, new, price,
 * category). Resolved values (label, position) are set by the per-store provider.
 *
 * @api
 */
interface FilterConfigInterface
{
    public const DISPLAY_LIST = 'list';
    public const DISPLAY_SWATCH = 'swatch';
    public const DISPLAY_SLIDER = 'slider';
    public const DISPLAY_FROM_TO = 'from_to';
    public const DISPLAY_RATING = 'rating';
    public const DISPLAY_TOGGLE = 'toggle';
    public const DISPLAY_DROPDOWN = 'dropdown';
    /** Price or decimal values as a list of ranges (1.1). */
    public const DISPLAY_RANGES = 'ranges';
    /** Category tree with several levels (1.1). */
    public const DISPLAY_TREE = 'tree';
    /** Options as the merchant's images with their labels, for attributes without swatches (1.2). */
    public const DISPLAY_IMAGES = 'images';
    public const DISPLAY_MODES = [
        self::DISPLAY_LIST,
        self::DISPLAY_SWATCH,
        self::DISPLAY_SLIDER,
        self::DISPLAY_FROM_TO,
        self::DISPLAY_RATING,
        self::DISPLAY_TOGGLE,
        self::DISPLAY_DROPDOWN,
        self::DISPLAY_RANGES,
        self::DISPLAY_TREE,
        self::DISPLAY_IMAGES,
    ];

    /** Products matching any of the picked values. */
    public const LOGIC_OR = 'or';
    /** Products matching all of the picked values (1.1, multi-select option filters). */
    public const LOGIC_AND = 'and';
    public const LOGICS = [self::LOGIC_OR, self::LOGIC_AND];

    /** Where the filter shows on category pages (1.1): everywhere, only in, or everywhere except some categories. */
    public const CATEGORIES_ALL = 'all';
    public const CATEGORIES_ONLY = 'only';
    public const CATEGORIES_EXCEPT = 'except';
    public const CATEGORY_MODES = [self::CATEGORIES_ALL, self::CATEGORIES_ONLY, self::CATEGORIES_EXCEPT];

    /** Category tree levels: default and limits (1.1). */
    public const TREE_DEPTH_DEFAULT = 3;
    public const TREE_DEPTH_MAX = 5;

    public const SORT_POSITION = 'position';
    public const SORT_NAME = 'name';
    public const SORT_COUNT = 'count';
    public const SORT_ORDERS = [self::SORT_POSITION, self::SORT_NAME, self::SORT_COUNT];

    public const BUILTIN_RATING = 'rating';
    public const BUILTIN_STOCK = 'stock';
    public const BUILTIN_SALE = 'sale';
    public const BUILTIN_NEW = 'new';
    public const BUILTIN_PRICE = 'price';
    public const BUILTIN_CATEGORY = 'category';
    public const BUILTIN_CODES = [
        self::BUILTIN_RATING,
        self::BUILTIN_STOCK,
        self::BUILTIN_SALE,
        self::BUILTIN_NEW,
        self::BUILTIN_PRICE,
        self::BUILTIN_CATEGORY,
    ];

    /**
     * Attribute code, or the built-in code.
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * @return bool
     */
    public function isBuiltin(): bool;

    /**
     * Attribute id (also set for the price built-in); null for category, rating, stock, sale and new.
     *
     * @return int|null
     */
    public function getAttributeId(): ?int;

    /**
     * Normalised input type: select, multiselect, boolean, decimal, price, category, rating.
     *
     * @return string
     */
    public function getInputType(): string;

    /**
     * Swatch type of the attribute (visual, text) or null.
     *
     * @return string|null
     */
    public function getSwatchType(): ?string;

    /**
     * @return string one of DISPLAY_MODES
     */
    public function getDisplayMode(): string;

    /**
     * @param string $displayMode
     * @return $this
     */
    public function setDisplayMode(string $displayMode): self;

    /**
     * @return bool
     */
    public function isMultiselect(): bool;

    /**
     * @param bool $multiselect
     * @return $this
     */
    public function setMultiselect(bool $multiselect): self;

    /**
     * @return string
     */
    public function getLogic(): string;

    /**
     * @param string $logic
     * @return $this
     */
    public function setLogic(string $logic): self;

    /**
     * Options shown before "Show more"; 0 = all.
     *
     * @return int
     */
    public function getVisibleOptions(): int;

    /**
     * @param int $visibleOptions
     * @return $this
     */
    public function setVisibleOptions(int $visibleOptions): self;

    /**
     * A search box appears above lists longer than this; 0 = never.
     *
     * @return int
     */
    public function getSearchThreshold(): int;

    /**
     * @param int $searchThreshold
     * @return $this
     */
    public function setSearchThreshold(int $searchThreshold): self;

    /**
     * Null = automatic (the first three groups open).
     *
     * @return bool|null
     */
    public function getExpanded(): ?bool;

    /**
     * @param bool|null $expanded
     * @return $this
     */
    public function setExpanded(?bool $expanded): self;

    /**
     * @return string one of SORT_ORDERS
     */
    public function getSortBy(): string;

    /**
     * @param string $sortBy
     * @return $this
     */
    public function setSortBy(string $sortBy): self;

    /**
     * @return bool
     */
    public function isHideSingleOption(): bool;

    /**
     * @param bool $hide
     * @return $this
     */
    public function setHideSingleOption(bool $hide): self;

    /**
     * Single-value pages of this filter may be indexed by search engines.
     *
     * @return bool
     */
    public function isSeoIndexable(): bool;

    /**
     * @param bool $indexable
     * @return $this
     */
    public function setSeoIndexable(bool $indexable): self;

    /**
     * @return int|null
     */
    public function getPositionOverride(): ?int;

    /**
     * @param int|null $position
     * @return $this
     */
    public function setPositionOverride(?int $position): self;

    /**
     * Whether the filter is shown on the storefront (attributes: unless hidden by the merchant; built-ins: switched on
     * by the merchant). Hiding never changes the attribute's own "Use in layered navigation" setting.
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * @param bool $active
     * @return $this
     */
    public function setActive(bool $active): self;

    /**
     * Resolved position (override, else the attribute's layered navigation position).
     *
     * @return int
     */
    public function getPosition(): int;

    /**
     * Resolved label in the provider's store view; null = the default label (translated when rendered).
     *
     * @return string|null
     */
    public function getLabel(): ?string;

    /**
     * Ranges of the "ranges" display type in the URL range format ("0-50,50-100,100-"); null = automatic ranges
     * from the price step and the prices found (1.1).
     *
     * @return string|null
     */
    public function getRanges(): ?string;

    /**
     * @param string|null $ranges
     * @return $this
     */
    public function setRanges(?string $ranges): self;

    /**
     * Levels shown by the category tree, 1 to TREE_DEPTH_MAX (1.1).
     *
     * @return int
     */
    public function getTreeDepth(): int;

    /**
     * @param int $depth
     * @return $this
     */
    public function setTreeDepth(int $depth): self;

    /**
     * Resolved tooltip in the provider's store view; null = none (1.1).
     *
     * @return string|null
     */
    public function getTooltip(): ?string;

    /**
     * Where the filter shows on category pages (1.1): CATEGORIES_ALL, CATEGORIES_ONLY or CATEGORIES_EXCEPT the
     * categories of getCategoryIds() and the categories below them. Search pages always show it.
     *
     * @return string
     */
    public function getCategoryMode(): string;

    /**
     * @param string $mode
     * @return $this
     */
    public function setCategoryMode(string $mode): self;

    /**
     * @return int[]
     */
    public function getCategoryIds(): array;

    /**
     * @param int[] $categoryIds
     * @return $this
     */
    public function setCategoryIds(array $categoryIds): self;

    /**
     * Key of the filter this one waits for (1.2): it shows only after a pick there; null = always shown.
     *
     * @return string|null
     */
    public function getDependsOn(): ?string;

    /**
     * @param string|null $filterKey
     * @return $this
     */
    public function setDependsOn(?string $filterKey): self;

    /**
     * Values of getDependsOn() that show this filter (option ids, or the built-in's values); empty = any pick (1.2).
     *
     * @return string[]
     */
    public function getDependsValues(): array;

    /**
     * @param string[] $values
     * @return $this
     */
    public function setDependsValues(array $values): self;

    /**
     * Whether the filter's options appear in the quick filter bar above the products (1.2).
     *
     * @return bool
     */
    public function isQuickBar(): bool;

    /**
     * @param bool $quickBar
     * @return $this
     */
    public function setQuickBar(bool $quickBar): self;
}
