<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Phrase;

/**
 * Checks that a filter's settings fit its attribute (Spec FilterConfig, "Validation").
 */
class Validator
{
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
        if ($config->getLogic() !== Filter::LOGIC_OR) {
            $errors[] = __('Only "or" logic is available in this version.');
        }
        if (!in_array($config->getSortBy(), Filter::SORT_ORDERS, true)) {
            $errors[] = __('"%1" is not an option order.', $config->getSortBy());
        }
        if ($config->getVisibleOptions() < 0 || $config->getSearchThreshold() < 0) {
            $errors[] = __('Visible options and the search box threshold cannot be negative.');
        }
        if ($errors !== []) {
            throw new InputException(new Phrase(implode(' ', array_map('strval', $errors))));
        }
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
                Filter::BUILTIN_PRICE => in_array($mode, [Filter::DISPLAY_SLIDER, Filter::DISPLAY_FROM_TO], true),
                Filter::BUILTIN_CATEGORY => $mode === Filter::DISPLAY_LIST,
                Filter::BUILTIN_RATING => in_array($mode, [Filter::DISPLAY_RATING, Filter::DISPLAY_LIST], true),
                default => $mode === Filter::DISPLAY_TOGGLE,
            };
        }

        return match ($mode) {
            Filter::DISPLAY_SWATCH => $config->getSwatchType() !== null,
            Filter::DISPLAY_SLIDER, Filter::DISPLAY_FROM_TO => $input === 'decimal',
            Filter::DISPLAY_TOGGLE => $input === 'boolean' || ($input === 'select' && $optionCount === 2),
            Filter::DISPLAY_RATING => false,
            default => in_array($input, ['select', 'multiselect', 'boolean'], true),
        };
    }
}
