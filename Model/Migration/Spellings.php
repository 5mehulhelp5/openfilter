<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Migration;

/**
 * How other modules may have written a name in their filter URLs (1.2 migration): with dashes, with underscores or
 * with nothing between its words, lower case.
 */
class Spellings
{
    /**
     * @param string $name
     * @return string[]
     */
    public function of(string $name): array
    {
        $name = strtolower($name);

        return array_values(array_unique(array_filter([
            $name,
            strtr($name, '_', '-'),
            strtr($name, '-', '_'),
            str_replace(['-', '_'], '', $name),
        ])));
    }
}
