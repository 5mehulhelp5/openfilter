<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

/**
 * Slider bounds between the index (base currency, prices as entered) and what the shopper sees (display currency,
 * tax as displayed). Pure arithmetic; PriceBoundsConverter supplies the store's rate and tax factor.
 */
class PriceBounds
{
    /**
     * @param float $base
     * @param float $currencyRate base → display currency
     * @param float $taxFactor displayed ÷ entered (1.19 when prices are entered net and shown with 19 % tax)
     * @return float
     */
    public function toDisplay(float $base, float $currencyRate, float $taxFactor): float
    {
        return $base * $currencyRate * $taxFactor;
    }

    /**
     * @param float $display
     * @param float $currencyRate
     * @param float $taxFactor
     * @return float
     */
    public function toBase(float $display, float $currencyRate, float $taxFactor): float
    {
        $divisor = $currencyRate * $taxFactor;

        return $divisor > 0 ? $display / $divisor : $display;
    }

    /**
     * Displayed ÷ entered price for a tax rate in percent.
     *
     * @param float $ratePercent
     * @param bool $enteredWithTax catalog prices include tax
     * @param bool $displayedWithTax prices are shown including tax
     * @return float
     */
    public function taxFactor(float $ratePercent, bool $enteredWithTax, bool $displayedWithTax): float
    {
        if ($enteredWithTax === $displayedWithTax || $ratePercent <= 0) {
            return 1.0;
        }
        $factor = 1 + $ratePercent / 100;

        return $displayedWithTax ? $factor : 1 / $factor;
    }

    /**
     * Slider range covering every product: min rounded down, max rounded up to the step.
     *
     * @param float $min
     * @param float $max
     * @param float $step
     * @return array{float, float}
     */
    public function round(float $min, float $max, float $step = 1.0): array
    {
        $step = $step > 0 ? $step : 1.0;
        $low = floor(round($min / $step, 6)) * $step;
        $high = ceil(round($max / $step, 6)) * $step;

        return [round($low, 4), round(max($high, $low), 4)];
    }
}
