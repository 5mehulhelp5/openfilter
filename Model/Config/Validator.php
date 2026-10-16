<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Url\RangeList;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Phrase;

/**
 * Checks that a filter's settings fit its attribute (Spec FilterConfig, "Validation").
 */
class Validator
{
    /**
     * @param RangeList $rangeList
     */
    public function __construct(
        private readonly RangeList $rangeList
    ) {
    }

    /**
     * @param Filter $config
     * @param int $optionCount number of options of a select attribute (toggle needs exactly two)
     * @return void
     * @throws InputException with every problem found
     */
    public function validate(Filter $config, int $optionCount = 0): void
    {
        $errors = [];
        $mode = $config->getDisplayMode();
        if (!in_array($mode, Filter::DISPLAY_MODES, true)) {
            $errors[] = __('"%1" is not a display type.', $mode);
        } elseif (!$this->fits($config, $mode, $optionCount)) {
            $errors[] = __('The display type "%1" does not fit the filter "%2".', $mode, $config->getCode());
        }
        if (!in_array($config->getLogic(), Filter::LOGICS, true)) {
            $errors[] = __('"%1" is not a filter logic.', $config->getLogic());
        } elseif ($config->getLogic() === Filter::LOGIC_AND && !$this->allowsAnd($config)) {
            $errors[] = __('"Match all" needs a filter of options where shoppers can pick several values.');
        }
        $ranges = $config->getRanges();
        if ($ranges !== null && $this->rangeList->parse($ranges) === null) {
            $errors[] = __(
                'The ranges "%1" are not a list like 0-50,50-100,100- (ascending, not overlapping, at most %2).',
                $ranges,
                RangeList::MAX_RANGES
            );
        }
        if (!in_array($config->getCategoryMode(), Filter::CATEGORY_MODES, true)) {
            $errors[] = __('"%1" is not a choice for where the filter shows.', $config->getCategoryMode());
        } elseif ($config->getCategoryMode() !== Filter::CATEGORIES_ALL && $config->getCategoryIds() === []) {
            $errors[] = __('Pick at least one category for where the filter shows.');
        }
        if ($config->getTreeDepth() < 1 || $config->getTreeDepth() > Filter::TREE_DEPTH_MAX) {
            $errors[] = __('The category tree shows 1 to %1 levels.', Filter::TREE_DEPTH_MAX);
        }
        if (!in_array($config->getSortBy(), Filter::SORT_ORDERS, true)) {
            $errors[] = __('"%1" is not an option order.', $config->getSortBy());
        }
        if ($config->getVisibleOptions() < 0 || $config->getSearchThreshold() < 0) {
            $errors[] = __('Visible options and the search box threshold cannot be negative.');
        }
        $parent = $config->getDependsOn();
        if ($parent !== null && in_array($parent, [$config->getCode(), 'attribute:' . $config->getCode()], true)) {
            $errors[] = __('A filter cannot wait for a pick in itself.');
        }
        if ($parent === null && $config->getDependsValues() !== []) {
            $errors[] = __('Choose the filter to wait for before choosing its values.');
        }
        if ($errors !== []) {
            throw new InputException(new Phrase(implode(' ', array_map('strval', $errors))));
        }
    }

    /**
     * Display types that fit the filter, in the order of FilterConfigInterface::DISPLAY_MODES (for forms).
     *
     * @param Filter $config
     * @param int $optionCount number of options of a select attribute
     * @return string[]
     */
    public function allowedModes(Filter $config, int $optionCount = 0): array
    {
        return array_values(array_filter(
            Filter::DISPLAY_MODES,
            fn (string $mode): bool => $this->fits($config, $mode, $optionCount)
        ));
    }

    /**
     * "Match all" needs several values of an option filter (an option list: select or multiselect).
     *
     * @param Filter $config
     * @return bool
     */
    private function allowsAnd(Filter $config): bool
    {
        return !$config->isBuiltin()
            && $config->isMultiselect()
            && in_array($config->getInputType(), ['select', 'multiselect'], true);
    }

    /**
     * @param Filter $config
     * @param string $mode
     * @param int $optionCount
     * @return bool
     */
    private function fits(Filter $config, string $mode, int $optionCount): bool
    {
        $input = $config->getInputType();
        if ($config->isBuiltin()) {
            return match ($config->getCode()) {
                Filter::BUILTIN_PRICE => in_array(
                    $mode,
                    [Filter::DISPLAY_SLIDER, Filter::DISPLAY_FROM_TO, Filter::DISPLAY_RANGES],
                    true
                ),
                Filter::BUILTIN_CATEGORY => in_array($mode, [Filter::DISPLAY_LIST, Filter::DISPLAY_TREE], true),
                Filter::BUILTIN_RATING => in_array($mode, [Filter::DISPLAY_RATING, Filter::DISPLAY_LIST], true),
                default => $mode === Filter::DISPLAY_TOGGLE,
            };
        }

        return match ($mode) {
            Filter::DISPLAY_SWATCH => $config->getSwatchType() !== null,
            Filter::DISPLAY_SLIDER, Filter::DISPLAY_FROM_TO, Filter::DISPLAY_RANGES => $input === 'decimal',
            Filter::DISPLAY_TREE => false,
            // Swatch attributes have their own pictures; images are for the others (1.2).
            Filter::DISPLAY_IMAGES => $config->getSwatchType() === null && in_array($input, ['select', 'multiselect'], true),
            Filter::DISPLAY_TOGGLE => $input === 'boolean' || ($input === 'select' && $optionCount === 2),
            Filter::DISPLAY_RATING => false,
            default => in_array($input, ['select', 'multiselect', 'boolean'], true),
        };
    }
}
