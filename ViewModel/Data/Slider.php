<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel\Data;

/**
 * A price or decimal range filter as the storefront renders it: a GET form with two number fields that works
 * without JavaScript, enhanced into a dual-thumb slider. Values are in the display currency.
 */
class Slider
{
    /**
     * @param string $parameter URL parameter of the filter
     * @param float $min lower bound, snapped to the step
     * @param float $max upper bound, snapped to the step
     * @param float $step
     * @param float|null $from current lower value, null when the filter is not active
     * @param float|null $to current upper value
     * @param string|null $currency ISO code for prices, null for decimal attributes
     * @param string $locale BCP 47 tag for number formatting (de-DE)
     * @param string $action form action: the page without query
     * @param array<string, string> $hiddenFields the page's other query parameters, kept by the form
     * @param string $urlTemplate URL with this filter set to Slider::PLACEHOLDER
     * @param string $clearUrl URL without this filter
     */
    public function __construct(
        private readonly string $parameter,
        private readonly float $min,
        private readonly float $max,
        private readonly float $step,
        private readonly ?float $from,
        private readonly ?float $to,
        private readonly ?string $currency,
        private readonly string $locale,
        private readonly string $action,
        private readonly array $hiddenFields,
        private readonly string $urlTemplate,
        private readonly string $clearUrl
    ) {
    }

    /**
     * @return string
     */
    public function getParameter(): string
    {
        return $this->parameter;
    }

    /**
     * @return float
     */
    public function getMin(): float
    {
        return $this->min;
    }

    /**
     * @return float
     */
    public function getMax(): float
    {
        return $this->max;
    }

    /**
     * @return float
     */
    public function getStep(): float
    {
        return $this->step;
    }

    /**
     * @return float|null
     */
    public function getFrom(): ?float
    {
        return $this->from;
    }

    /**
     * @return float|null
     */
    public function getTo(): ?float
    {
        return $this->to;
    }

    /**
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return array<string, string>
     */
    public function getHiddenFields(): array
    {
        return $this->hiddenFields;
    }

    /**
     * @return string
     */
    public function getUrlTemplate(): string
    {
        return $this->urlTemplate;
    }

    /**
     * @return string
     */
    public function getClearUrl(): string
    {
        return $this->clearUrl;
    }

    /**
     * Field name of the lower value in the form (folded into the parameter by the URL normaliser).
     *
     * @return string
     */
    public function getFromField(): string
    {
        return $this->parameter . '_from';
    }

    /**
     * Field name of the upper value in the form.
     *
     * @return string
     */
    public function getToField(): string
    {
        return $this->parameter . '_to';
    }
}
