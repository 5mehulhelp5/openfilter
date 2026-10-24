<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Migration;

/**
 * Reads the filter part of an old filter URL of another layered navigation module (1.2 migration): the path after
 * the category, in the formats their public user guides document (docs/migration.md):
 *
 * - option names after the category, joined by -, _ or / ("black-blue", "black/blue"), as Amasty, Mageplaza and
 *   Mirasvit's short formats write them;
 * - with the attribute before them ("color_black", "color-black-blue", "color/black-blue", "color:black,blue"),
 *   as their "attribute code" and Mirasvit's long formats write them;
 * - with a filter key segment ("shopby/black") that is skipped.
 *
 * Every reading of the path is tried; the result is used only when all readings agree on one filter state, so an
 * option name two filters share without an attribute before it is not guessed.
 */
class OldUrlParser
{
    /** Readings tried before giving up on a path. */
    private const MAX_READINGS = 64;

    private const SEPARATORS = ['-', '_', ',', ':'];

    /** @var array<string, string> written attribute name => filter key */
    private array $attributes = [];

    /** @var array<string, array<string, string>> written option name => filter key => OpenFilter URL name */
    private array $options = [];

    /** @var array<string, string[]> first word => written names (attributes and options) starting with it */
    private array $byFirstWord = [];

    private int $readings = 0;

    /**
     * @param string $path the part after the category, without the URL suffix ("shopby/black-blue")
     * @param array{attributes: array<string, string>, options: array<string, array<string, string>>} $vocabulary
     * @param string[] $filterKeys segments to skip ("shopby")
     * @return array<string, string[]>|null filter key => OpenFilter option URL names; null = not an old filter URL
     */
    public function parse(string $path, array $vocabulary, array $filterKeys = []): ?array
    {
        $this->attributes = $vocabulary['attributes'];
        $this->options = $vocabulary['options'];
        $this->byFirstWord = [];
        foreach (array_merge(array_keys($this->attributes), array_keys($this->options)) as $name) {
            $this->byFirstWord[$this->firstWord((string) $name, 0)][] = (string) $name;
        }
        $skip = array_map('strtolower', $filterKeys);
        $segments = array_values(array_filter(
            explode('/', strtolower(str_ireplace(['%2C', '%3A', '%5F'], [',', ':', '_'], trim($path, '/')))),
            static fn (string $segment): bool => $segment !== '' && !in_array($segment, $skip, true)
        ));
        if ($segments === []) {
            return null;
        }
        $this->readings = 0;
        $states = [];
        foreach ($this->readSegments($segments, 0, null, false, []) as $items) {
            $state = $this->state($items);
            $states[json_encode($state)] = $state;
            if (count($states) > 1) {
                return null;
            }
        }

        return $states === [] ? null : (reset($states) ?: null);
    }

    /**
     * @param string[] $segments
     * @param int $index
     * @param string|null $context the filter key an attribute name before set
     * @param bool $waiting an attribute name has no option after it yet
     * @param array<int, array{0: string, 1: string}> $items picks so far
     * @return \Generator<int, array<int, array{0: string, 1: string}>>
     */
    private function readSegments(array $segments, int $index, ?string $context, bool $waiting, array $items): \Generator
    {
        if ($index === count($segments)) {
            if ($items !== [] && !$waiting) {
                yield $items;
            }

            return;
        }
        foreach ($this->readWords($segments[$index], 0, $context, $waiting, $items) as [$context2, $waiting2, $items2]) {
            yield from $this->readSegments($segments, $index + 1, $context2, $waiting2, $items2);
        }
    }

    /**
     * Every way to read one segment from a position as attribute and option names joined by separators. An attribute
     * name counts only with an option after it (in this segment or the next).
     *
     * @param string $segment
     * @param int $at
     * @param string|null $context
     * @param bool $waiting
     * @param array<int, array{0: string, 1: string}> $items
     * @return \Generator<int, array{0: string|null, 1: bool, 2: array<int, array{0: string, 1: string}>}>
     */
    private function readWords(string $segment, int $at, ?string $context, bool $waiting, array $items): \Generator
    {
        if ($at >= strlen($segment)) {
            yield [$context, $waiting, $items];

            return;
        }
        foreach ($this->byFirstWord[$this->firstWord($segment, $at)] ?? [] as $name) {
            $end = $at + strlen($name);
            if (substr($segment, $at, strlen($name)) !== $name
                || ($end < strlen($segment) && !in_array($segment[$end], self::SEPARATORS, true))
            ) {
                continue;
            }
            if (++$this->readings > self::MAX_READINGS) {
                return;
            }
            if (isset($this->attributes[$name]) && !$waiting) {
                yield from $this->readWords($segment, $end + 1, $this->attributes[$name], true, $items);
            }
            foreach ($this->filtersOf($name, $context) as $key => $slug) {
                yield from $this->readWords($segment, $end + 1, $context, false, array_merge($items, [[$key, $slug]]));
            }
        }
    }

    /**
     * The filters an option name can belong to: the one the attribute before names, if it has the option.
     *
     * @param string $name
     * @param string|null $context
     * @return array<string, string> filter key => OpenFilter URL name
     */
    private function filtersOf(string $name, ?string $context): array
    {
        $filters = $this->options[$name] ?? [];
        if ($context !== null && isset($filters[$context])) {
            return [$context => $filters[$context]];
        }

        return $filters;
    }

    /**
     * @param string $text
     * @param int $at
     * @return string
     */
    private function firstWord(string $text, int $at): string
    {
        $length = strcspn($text, implode('', self::SEPARATORS), $at);

        return substr($text, $at, $length);
    }

    /**
     * @param array<int, array{0: string, 1: string}> $items
     * @return array<string, string[]>
     */
    private function state(array $items): array
    {
        $state = [];
        foreach ($items as [$key, $slug]) {
            $state[$key][$slug] = $slug;
        }
        ksort($state);

        return array_map(static function (array $slugs): array {
            sort($slugs);

            return array_values($slugs);
        }, $state);
    }
}
