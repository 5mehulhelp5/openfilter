<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search;

use Iranimij\OpenFilter\Model\Search\NiceRanges;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NiceRangesTest extends TestCase
{
    /**
     * @return array<string, array{float, float, array<int, array{float, ?float}>}>
     */
    public static function spans(): array
    {
        return [
            'shirts 12 to 96' => [12.0, 96.0, [[0.0, 20.0], [20.0, 40.0], [40.0, 60.0], [60.0, 80.0], [80.0, null]]],
            'jackets 45 to 310' => [45.0, 310.0, [[0.0, 100.0], [100.0, 200.0], [200.0, 300.0], [300.0, null]]],
            'cheap 1.5 to 4.2' => [1.5, 4.2, [[1.0, 2.0], [2.0, 3.0], [3.0, 4.0], [4.0, null]]],
            'one price' => [25.0, 25.0, [[25.0, null]]],
            'thousands' => [990.0, 5400.0, [[0.0, 1000.0], [1000.0, 2000.0], [2000.0, 3000.0], [3000.0, 4000.0], [4000.0, 5000.0], [5000.0, null]]],
        ];
    }

    /**
     * @dataProvider spans
     * @param array<int, array{float, ?float}> $expected
     */
    #[DataProvider('spans')]
    public function testRoundBoundsAndAnOpenLastRange(float $min, float $max, array $expected): void
    {
        self::assertSame($expected, (new NiceRanges())->between($min, $max));
    }

    public function testStepsAreOneTwoOrFiveTimesAPowerOfTen(): void
    {
        $ranges = new NiceRanges();

        self::assertSame(20.0, $ranges->step(16.8));
        self::assertSame(100.0, $ranges->step(53.0));
        self::assertSame(1.0, $ranges->step(0.54));
        self::assertSame(0.5, $ranges->step(0.31));
        self::assertSame(1000.0, $ranges->step(882.0));
    }
}
