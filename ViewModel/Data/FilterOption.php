<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel\Data;

/**
 * One option of a filter group as the storefront renders it: a real link to the filtered URL.
 */
class FilterOption
{
    /**
     * @param string $label
     * @param string $value option id (or range) as the filter knows it
     * @param string $url link that adds the option, or removes it when it is selected
     * @param int $count products the link leads to
     * @param bool $selected
     * @param string $rel the link's rel attribute ('nofollow' beyond the SEO threshold, '' otherwise)
     */
    public function __construct(
        private readonly string $label,
        private readonly string $value,
        private readonly string $url,
        private readonly int $count,
        private readonly bool $selected,
        private readonly string $rel = ''
    ) {
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
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return bool
     */
    public function isSelected(): bool
    {
        return $this->selected;
    }

    /**
     * Value of the link's rel attribute ('' for none).
     *
     * @return string
     */
    public function getRel(): string
    {
        return $this->rel;
    }
}
