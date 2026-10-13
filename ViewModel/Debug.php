<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Debug mode (Stores > Configuration > Iranimij > OpenFilter > Debug mode): `data-of-debug-*` attributes that show
 * what the server decided, readable in the browser's inspector. Prints nothing while debug mode is off.
 */
class Debug implements ArgumentInterface
{
    /**
     * @param Config $config
     * @param StrategyResolver $strategies
     * @param LayerContext $context
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly Config $config,
        private readonly StrategyResolver $strategies,
        private readonly LayerContext $context,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isDebug();
    }

    /**
     * Attributes of the filter region: count strategy, search engine, active values.
     *
     * @return string
     */
    public function region(): string
    {
        return $this->render(fn (): array => [
            'strategy' => $this->strategies->code(),
            'engine' => $this->strategies->engine(),
            'values' => (string) $this->context->state()->count(),
        ]);
    }

    /**
     * Attributes of a filter group: filter key and display.
     *
     * @param Data\FilterGroup $group
     * @return string
     */
    public function group(Data\FilterGroup $group): string
    {
        return $this->render(static fn (): array => [
            'filter' => $group->getKey() ?? $group->getCode() . ' (core)',
            'display' => $group->getDisplay(),
        ]);
    }

    /**
     * Attributes of an option link: option id (or range) and the count the engine returned.
     *
     * @param Data\FilterOption $option
     * @return string
     */
    public function option(Data\FilterOption $option): string
    {
        return $this->render(static fn (): array => [
            'option' => $option->getValue(),
            'count' => (string) $option->getCount(),
        ]);
    }

    /**
     * @param callable(): array<string, string> $attributes
     * @return string ' data-of-debug-x="…"' pairs, or '' outside debug mode
     */
    private function render(callable $attributes): string
    {
        if (!$this->isEnabled()) {
            return '';
        }
        $html = '';
        foreach ($attributes() as $name => $value) {
            $html .= sprintf(' data-of-debug-%s="%s"', $name, $this->escaper->escapeHtmlAttr($value));
        }

        return $html;
    }
}
