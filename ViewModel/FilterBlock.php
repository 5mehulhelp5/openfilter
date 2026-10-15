<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Layer\Filter\AbstractRangeFilter;
use Iranimij\OpenFilter\Model\Layer\Filter\Item;
use Iranimij\OpenFilter\Model\Layer\Filter\OpenFilterInterface;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Seo\NofollowPolicy;
use Laminas\Uri\Http as HttpUri;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Catalog\Model\Layer\Filter\Item as CoreItem;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Turns the layer's filters into render-ready groups for the storefront templates (any theme package).
 */
class FilterBlock implements ArgumentInterface
{
    /** Display of core's category drill-down. */
    public const DISPLAY_CATEGORY = 'category';

    private const CATEGORY_REQUEST_VAR = 'cat';

    /**
     * @param LayerContext $context
     * @param Config $config
     * @param Slider $slider
     * @param NofollowPolicy $nofollow
     */
    public function __construct(
        private readonly LayerContext $context,
        private readonly Config $config,
        private readonly Slider $slider,
        private readonly NofollowPolicy $nofollow
    ) {
    }

    /**
     * Groups for the filters that have options, in the layer's order.
     *
     * @param array<int|string, FilterInterface|AbstractFilter> $filters
     * @return Data\FilterGroup[]
     */
    public function groups(array $filters): array
    {
        $expandedByDefault = $this->config->getExpandedGroups($this->context->storeId());
        $groups = [];
        foreach ($filters as $filter) {
            if (!$filter instanceof AbstractFilter) {
                continue;
            }
            $slider = $filter instanceof AbstractRangeFilter && !$filter->isRangeList()
                ? $this->slider->forFilter($filter)
                : null;
            if ($slider === null && (int) $filter->getItemsCount() === 0) {
                continue;
            }
            $groups[] = $this->group($filter, count($groups) < $expandedByDefault, $slider);
        }

        return $groups;
    }

    /**
     * Selected values over all groups (the mobile "Filter (N)" button).
     *
     * @param Data\FilterGroup[] $groups
     * @return int
     */
    public function selectedCount(array $groups): int
    {
        return array_sum(array_map(static fn (Data\FilterGroup $group): int => $group->getSelectedCount(), $groups));
    }

    /**
     * @param AbstractFilter $filter
     * @param bool $openByPosition
     * @param Data\Slider|null $slider
     * @return Data\FilterGroup
     */
    private function group(AbstractFilter $filter, bool $openByPosition, ?Data\Slider $slider): Data\FilterGroup
    {
        $key = $filter instanceof OpenFilterInterface ? $filter->getOpenFilterKey() : null;
        $config = $key === null ? null : $this->context->config($key);
        $requestVar = (string) $filter->getRequestVar();
        $options = array_map(fn ($item): Data\FilterOption => $this->option($item), array_values($filter->getItems()));
        $selected = array_filter($options, static fn (Data\FilterOption $option): bool => $option->isSelected());
        if ($slider !== null) {
            $options = []; // the slider shows the range; the active range is a chip
        }
        $configured = $config?->getExpanded();
        $display = $config?->getDisplayMode()
            ?? ($requestVar === self::CATEGORY_REQUEST_VAR ? self::DISPLAY_CATEGORY : FilterConfigInterface::DISPLAY_LIST);

        return new Data\FilterGroup(
            $config?->getCode() ?? $requestVar,
            $key,
            (string) $filter->getName(),
            $display,
            $key !== null && $filter instanceof OpenFilterInterface && $filter->isMultiselect(),
            $this->layout() === Config::LAYOUT_HORIZONTAL ? false : ($selected !== [] || ($configured ?? $openByPosition)),
            $config?->getVisibleOptions() ?? 0,
            $config !== null && count($options) > $config->getSearchThreshold(),
            $options,
            $slider,
            $config?->getTooltip(),
            $config?->getLogic() === FilterConfigInterface::LOGIC_AND,
            $key !== null && $display === FilterConfigInterface::DISPLAY_DROPDOWN ? $this->dropdownForm($key) : null
        );
    }

    /**
     * Desktop layout of the filters (1.1): Config::LAYOUT_SIDEBAR or LAYOUT_HORIZONTAL (its groups are popovers
     * that start closed; the chips show what is picked).
     *
     * @return string
     */
    public function layout(): string
    {
        return $this->config->getLayout($this->context->storeId());
    }

    /**
     * Desktop: results with every click or after "Show N products" (1.1): Config::APPLY_INSTANT or APPLY_BUTTON.
     *
     * @return string
     */
    public function applyMode(): string
    {
        return $this->config->getApplyMode($this->context->storeId());
    }

    /**
     * The sidebar stays in view while scrolling (1.1, sidebar layout only).
     *
     * @return bool
     */
    public function isSticky(): bool
    {
        return $this->layout() === Config::LAYOUT_SIDEBAR && $this->config->isSticky($this->context->storeId());
    }

    /**
     * The dropdown's GET form: the page without this filter's values, which the select adds back (an option id; the
     * normaliser answers with the canonical URL).
     *
     * @param string $key
     * @return Data\Form
     */
    private function dropdownForm(string $key): Data\Form
    {
        $url = $this->context->urlFor($this->context->state()->without($key));
        $fields = [];
        foreach ((new HttpUri($url))->getQueryAsArray() as $name => $value) {
            if (is_scalar($value)) {
                $fields[(string) $name] = (string) $value;
            }
        }

        return new Data\Form($this->context->parameterFor($key) ?? $key, strtok($url, '?') ?: $url, $fields);
    }

    /**
     * @param CoreItem $item
     * @return Data\FilterOption
     */
    private function option(CoreItem $item): Data\FilterOption
    {
        $value = $item->getData('value');
        $selected = $item instanceof Item && $item->isSelected();
        $storeId = $this->context->storeId();

        return new Data\FilterOption(
            trim(strip_tags((string) $item->getData('label'))),
            is_array($value) ? implode('-', $value) : (string) $value,
            (string) $item->getUrl(),
            (int) $item->getData('count'),
            $selected,
            $this->nofollow->rel($this->context->state()->count(), $selected, $storeId),
            (int) $item->getData('depth')
        );
    }
}
