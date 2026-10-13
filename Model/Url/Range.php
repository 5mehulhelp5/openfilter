<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

/**
 * Slider ranges in URLs: "20-80", open ends "20-" and "-80", "." as decimal separator, 0 ≤ value ≤ 10^9.
 */
class Range
{
    public const MAX = 1000000000;

    /**
     * @param string $value
     * @return array{?float, ?float}|null [from, to]; null when nothing usable is left
     */
    public function parse(string $value): ?array
    {
        if (preg_match('/^\s*(\d+(?:\.\d+)?)?\s*-\s*(\d+(?:\.\d+)?)?\s*$/', $value, $match) !== 1) {
            return null;
        }
        $from = isset($match[1]) && $match[1] !== '' ? $this->clamp((float) $match[1]) : null;
        $to = isset($match[2]) && $match[2] !== '' ? $this->clamp((float) $match[2]) : null;
        if ($from === null && $to === null) {
            return null;
        }
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * @param float|null $from
     * @param float|null $to
     * @return string
     */
    public function format(?float $from, ?float $to): string
    {
        return $this->number($from) . '-' . $this->number($to);
    }

    /**
     * Canonical form of a range value, or null when it is not one.
     *
     * @param string $value
     * @return string|null
     */
    public function normalise(string $value): ?string
    {
        $range = $this->parse($value);

        return $range === null ? null : $this->format($range[0], $range[1]);
    }

    /**
     * @param float $value
     * @return float
     */
    private function clamp(float $value): float
    {
        return min(max($value, 0.0), (float) self::MAX);
    }

    /**
     * @param float|null $value
     * @return string
     */
    private function number(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
