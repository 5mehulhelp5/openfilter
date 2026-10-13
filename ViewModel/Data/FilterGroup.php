<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel\Data;

/**
 * One filter of the layered navigation as the storefront renders it (a disclosure with its options).
 */
class FilterGroup
{
    /**
     * @param string $code attribute or built-in code (core filters: their request variable)
     * @param string|null $key OpenFilter key, null for core filters (category drill-down)
     * @param string $label
     * @param string $display display mode (FilterConfigInterface::DISPLAY_*, or FilterBlock::DISPLAY_CATEGORY)
     * @param bool $multiselect
     * @param bool $expanded
     * @param int $visibleOptions options shown before "Show more"
     * @param bool $searchable whether the group gets a search box
     * @param FilterOption[] $options
     * @param Slider|null $slider price and decimal filters: their range form and slider
     */
    public function __construct(
        private readonly string $code,
        private readonly ?string $key,
        private readonly string $label,
        private readonly string $display,
        private readonly bool $multiselect,
        private readonly bool $expanded,
        private readonly int $visibleOptions,
        private readonly bool $searchable,
        private readonly array $options,
        private readonly ?Slider $slider = null
    ) {
    }

    /**
     * @return Slider|null
     */
    public function getSlider(): ?Slider
    {
        return $this->slider;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return string|null
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getDisplay(): string
    {
        return $this->display;
    }

    /**
     * @return bool
     */
    public function isMultiselect(): bool
    {
        return $this->multiselect;
    }

    /**
     * @return bool
     */
    public function isExpanded(): bool
    {
        return $this->expanded;
    }

    /**
     * @return int
     */
    public function getVisibleOptions(): int
    {
        return $this->visibleOptions;
    }

    /**
     * @return bool
     */
    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * @return FilterOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Options shown before "Show more": the first visible ones plus every selected option, in their order.
     *
     * @return FilterOption[]
     */
    public function getLeadingOptions(): array
    {
        return array_values(array_filter(
            $this->options,
            fn (FilterOption $option, int $index): bool => $this->isLeading($option, $index),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * Options behind "Show more".
     *
     * @return FilterOption[]
     */
    public function getMoreOptions(): array
    {
        return array_values(array_filter(
            $this->options,
            fn (FilterOption $option, int $index): bool => !$this->isLeading($option, $index),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * @return int
     */
    public function getSelectedCount(): int
    {
        return count(array_filter($this->options, static fn (FilterOption $option): bool => $option->isSelected()));
    }

    /**
     * @param FilterOption $option
     * @param int $index
     * @return bool
     */
    private function isLeading(FilterOption $option, int $index): bool
    {
        return $this->visibleOptions <= 0 || $index < $this->visibleOptions || $option->isSelected();
    }
}
