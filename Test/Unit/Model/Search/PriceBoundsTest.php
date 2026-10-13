<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search;

use Iranimij\OpenFilter\Model\Search\PriceBounds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PriceBoundsTest extends TestCase
{
    /**
     * @return array<string, array{float, bool, bool, float}>
     */
    public static function taxFactors(): array
    {
        return [
            'net entered, shown with tax' => [19.0, false, true, 1.19],
            'gross entered, shown without tax' => [19.0, true, false, 1 / 1.19],
            'shown as entered (net)' => [19.0, false, false, 1.0],
            'shown as entered (gross)' => [19.0, true, true, 1.0],
            'no tax rate' => [0.0, false, true, 1.0],
        ];
    }

    /**
     * @dataProvider taxFactors
     */
    #[DataProvider('taxFactors')]
    public function testTaxFactor(float $rate, bool $enteredWithTax, bool $displayedWithTax, float $expected): void
    {
        self::assertEqualsWithDelta($expected, (new PriceBounds())->taxFactor($rate, $enteredWithTax, $displayedWithTax), 1e-9);
    }

    public function testRoundTripThroughCurrencyAndTax(): void
    {
        $bounds = new PriceBounds();
        $display = $bounds->toDisplay(100.0, 0.9, 1.19);

        self::assertEqualsWithDelta(107.1, $display, 1e-9);
        self::assertEqualsWithDelta(100.0, $bounds->toBase($display, 0.9, 1.19), 1e-9);
        self::assertSame(5.0, $bounds->toBase(5.0, 0.0, 1.0), 'a zero rate never divides by zero');
    }

    public function testRoundsTheRangeOutwardsToTheStep(): void
    {
        $bounds = new PriceBounds();

        self::assertSame([8.0, 57.0], $bounds->round(8.4, 56.2));
        self::assertSame([5.0, 60.0], $bounds->round(8.4, 56.2, 5));
        self::assertSame([10.0, 10.0], $bounds->round(10.0, 10.0));
        self::assertSame([0.5, 1.5], $bounds->round(0.74, 1.26, 0.5));
        self::assertSame([3.0, 3.0], $bounds->round(3.0, 3.0, 0));
    }
}
