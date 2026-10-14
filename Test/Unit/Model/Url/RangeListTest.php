<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Url;

use Iranimij\OpenFilter\Model\Url\Range;
use Iranimij\OpenFilter\Model\Url\RangeList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RangeListTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function lists(): array
    {
        return [
            'closed and open' => ['0-50,50-100,100-', '0-50,50-100,100-'],
            'open below first' => ['-50, 50-100', '-50,50-100'],
            'gaps allowed' => ['0-10,20-30', '0-10,20-30'],
            'decimals' => ['0.5-1.25,1.25-', '0.5-1.25,1.25-'],
            'one range' => ['10-20', '10-20'],
            'swapped ends are read in order' => ['50-0,50-100', '0-50,50-100'],
            'overlap' => ['0-60,50-100', null],
            'not ascending' => ['50-100,0-50', null],
            'open above in the middle' => ['0-50,50-,100-200', null],
            'open below later' => ['0-50,-100', null],
            'empty range' => ['5-5', null],
            'word' => ['cheap', null],
            'empty' => ['', null],
            'empty item' => ['0-50,,50-', null],
        ];
    }

    /**
     * @dataProvider lists
     */
    #[DataProvider('lists')]
    public function testNormalise(string $value, ?string $expected): void
    {
        self::assertSame($expected, (new RangeList(new Range()))->normalise($value));
    }

    public function testAtMostTwentyRanges(): void
    {
        $twenty = implode(',', array_map(static fn (int $i): string => ($i * 10) . '-' . ($i * 10 + 10), range(0, 19)));
        $list = new RangeList(new Range());

        self::assertNotNull($list->parse($twenty));
        self::assertNull($list->parse($twenty . ',200-'));
        self::assertSame([[0.0, 50.0], [50.0, null]], $list->parse('0-50,50-'));
    }
}
