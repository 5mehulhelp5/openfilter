<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Layer\Filter\AbstractRangeFilter;
use Iranimij\OpenFilter\Model\Layer\Filter\Price;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Url\Range;
use Laminas\Uri\Http as HttpUri;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Slider data for price and decimal filters: bounds from the same search request (they ignore the filter's own
 * value), the current range from the URL, the form that works without JavaScript and the URL template for AJAX.
 */
class Slider implements ArgumentInterface
{
    /** Range value the URL template carries in place of from-to (inside Range::MAX, so the codec keeps it). */
    public const PLACEHOLDER = '999999901-999999902';

    /**
     * @param LayerContext $context
     * @param Config $config
     * @param Range $range
     * @param StoreManagerInterface $storeManager
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        private readonly LayerContext $context,
        private readonly Config $config,
        private readonly Range $range,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResolverInterface $localeResolver
    ) {
    }

    /**
     * Slider for a range filter, or null when it has no bounds (no products, or every product at one value).
     *
     * @param AbstractRangeFilter $filter
     * @return Data\Slider|null
     */
    public function forFilter(AbstractRangeFilter $filter): ?Data\Slider
    {
        $key = $filter->getOpenFilterKey();
        $stats = $key === null ? null : $filter->getStats();
        if ($key === null || $stats === null || (float) $stats['max'] <= (float) $stats['min']) {
            return null;
        }
        $isPrice = $filter instanceof Price;
        $storeId = $this->context->storeId();
        $step = $isPrice
            ? max(0.01, $this->config->getPriceStep($storeId))
            : ((float) $stats['max'] - (float) $stats['min'] > 10 ? 1.0 : 0.1);
        $state = $this->context->state();
        $current = isset($state->get($key)[0]) ? $this->range->parse($state->get($key)[0]) : null;
        $clearUrl = $this->context->urlFor($state->without($key));
        [$action, $hidden] = $this->split($clearUrl);
        $store = $this->storeManager->getStore();

        return new Data\Slider(
            $this->context->parameterFor($key) ?? $key,
            $this->snap((float) $stats['min'], $step, false),
            $this->snap((float) $stats['max'], $step, true),
            $step,
            $current[0] ?? null,
            $current[1] ?? null,
            $isPrice && $store instanceof Store ? (string) $store->getCurrentCurrencyCode() : null,
            str_replace('_', '-', (string) $this->localeResolver->getLocale()),
            $action,
            $hidden,
            $this->context->urlFor($state->with($key, [self::PLACEHOLDER])),
            $clearUrl
        );
    }

    /**
     * Rounds a bound outwards to the step (with a tolerance for binary fractions: 7.5 / 0.1 is 75.00000000000001).
     *
     * @param float $value
     * @param float $step
     * @param bool $up
     * @return float
     */
    private function snap(float $value, float $step, bool $up): float
    {
        $steps = round($value / $step, 6);
        $decimals = max(0, (int) -floor(log10($step)));

        return round(($up ? ceil($steps) : floor($steps)) * $step, $decimals);
    }

    /**
     * The page URL without its query, and the query as form fields.
     *
     * @param string $url
     * @return array{string, array<string, string>}
     */
    private function split(string $url): array
    {
        $fields = [];
        foreach ((new HttpUri($url))->getQueryAsArray() as $name => $value) {
            if (is_scalar($value)) {
                $fields[(string) $name] = (string) $value;
            }
        }

        return [strtok($url, '?') ?: $url, $fields];
    }
}
