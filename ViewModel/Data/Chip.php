<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel\Data;

/**
 * One active filter value above the results ("Colour: Blue ×"), a link that removes it.
 */
class Chip
{
    /**
     * @param string $name filter name
     * @param string $value value label (ranges in the display currency)
     * @param string $removeUrl
     */
    public function __construct(
        private readonly string $name,
        private readonly string $value,
        private readonly string $removeUrl
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
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
    public function getRemoveUrl(): string
    {
        return $this->removeUrl;
    }

    /**
     * "Name: value", or the name alone for Yes/No filters whose value is their name ("On sale").
     *
     * @return string
     */
    public function getText(): string
    {
        return $this->value === '' || $this->value === $this->name ? $this->name : $this->name . ': ' . $this->value;
    }
}
