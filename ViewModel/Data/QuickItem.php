<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel\Data;

/**
 * A value in the quick filter bar (1.2): the option and the name of its filter (read by screen readers, shown on
 * hover), so "Black" is "Colour: Black".
 */
class QuickItem
{
    /**
     * @param string $filterLabel
     * @param FilterOption $option
     */
    public function __construct(
        private readonly string $filterLabel,
        private readonly FilterOption $option
    ) {
    }

    /**
     * @return string
     */
    public function getFilterLabel(): string
    {
        return $this->filterLabel;
    }

    /**
     * @return FilterOption
     */
    public function getOption(): FilterOption
    {
        return $this->option;
    }
}
