<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Migration;

use Iranimij\OpenFilter\Model\Migration\OldUrlParser;
use Iranimij\OpenFilter\Model\Migration\Spellings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * One case per URL example of the vendors' public user guides (docs/migration.md), on a small vocabulary.
 */
class OldUrlParserTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: array<string, string[]>|null, 2?: string[]}>
     */
    public static function urls(): array
    {
        return [
            'Amasty: an option name' => ['autumn', ['season' => ['autumn']]],
            'Amasty: options joined by the separator' => ['red-green-blue', ['color' => ['blue', 'green', 'red']]],
            'Amasty: options of several filters' => ['red-xl-activity', ['activity' => ['activity'], 'color' => ['red'], 'size' => ['xl']]],
            'Amasty: filter key' => ['shopby/red-xl', ['color' => ['red'], 'size' => ['xl']], ['shopby']],
            'Amasty / Mageplaza: attribute code' => ['color_black', ['color' => ['black']]],
            'Mageplaza: option without separators' => ['heavyduty', ['style_general' => ['heavy-duty']]],
            'Mageplaza: attribute code, underscores' => ['climate_all_weather', ['climate' => ['all-weather']]],
            'Mirasvit short, dashes' => ['red-blue-xl', ['color' => ['blue', 'red'], 'size' => ['xl']]],
            'Mirasvit short, slashes' => ['red/blue/xl', ['color' => ['blue', 'red'], 'size' => ['xl']]],
            'Mirasvit short, underscores' => ['red_blue_xl', ['color' => ['blue', 'red'], 'size' => ['xl']]],
            'Mirasvit long, attribute segments' => ['color/red-blue/size/xl', ['color' => ['blue', 'red'], 'size' => ['xl']]],
            'Mirasvit long, attribute first' => ['color-red-blue/size-xl', ['color' => ['blue', 'red'], 'size' => ['xl']]],
            'Mirasvit long, colon and commas' => ['color:red,blue/size:xl', ['color' => ['blue', 'red'], 'size' => ['xl']]],
            'Mirasvit long, multi-word attribute' => ['stylegeneral/heavy-duty', ['style_general' => ['heavy-duty']]],
            'Mirasvit prefix' => ['filterprefix/material/cotton', ['material' => ['cotton']], ['filterprefix']],
            'encoded comma, upper case' => ['Color%3ARed%2CBlue', ['color' => ['blue', 'red']]],
            'a shared name with its attribute' => ['frame_black', ['frame' => ['black']]],
            'a shared name without an attribute is not guessed' => ['black-frame', null],
            'unknown words' => ['nonsense', null],
            'part of a name' => ['heavy', null],
            'only the filter key' => ['shopby', null, ['shopby']],
        ];
    }

    /**
     * @param string $path
     * @param array<string, string[]>|null $expected
     * @param string[] $filterKeys
     * @dataProvider urls
     */
    #[DataProvider('urls')]
    public function testDocumentedFormats(string $path, ?array $expected, array $filterKeys = []): void
    {
        self::assertSame($expected, (new OldUrlParser())->parse($path, $this->vocabulary(), $filterKeys));
    }

    public function testManyReadingsStopEarly(): void
    {
        // "a" is an option of 20 filters: the readings multiply, the parser gives up instead of trying them all.
        $vocabulary = ['attributes' => [], 'options' => ['a' => array_fill_keys(array_map('strval', range(1, 20)), 'a')]];

        self::assertNull((new OldUrlParser())->parse('a-a-a-a-a-a', $vocabulary));
    }

    /**
     * @return array{attributes: array<string, string>, options: array<string, array<string, string>>}
     */
    private function vocabulary(): array
    {
        $filters = [
            'color' => ['red', 'green', 'blue', 'black'],
            'size' => ['xl', 'm'],
            'season' => ['autumn'],
            'activity' => ['activity'],
            'style_general' => ['heavy-duty'],
            'climate' => ['all-weather'],
            'material' => ['cotton'],
            'frame' => ['black'],
        ];
        $vocabulary = ['attributes' => [], 'options' => []];
        foreach ($filters as $key => $options) {
            foreach ((new Spellings())->of($key) as $spelling) {
                $vocabulary['attributes'][$spelling] = $key;
            }
            foreach ($options as $slug) {
                foreach ((new Spellings())->of($slug) as $spelling) {
                    $vocabulary['options'][$spelling][$key] = $slug;
                }
            }
        }

        return $vocabulary;
    }
}
