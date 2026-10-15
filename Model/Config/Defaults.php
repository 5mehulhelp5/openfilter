<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;

/**
 * The settings OpenFilter starts from (Spec FilterConfig, "Defaults"): swatches as multi-select swatches, price and
 * decimals as sliders, booleans as toggles, other selects as multi-select lists. Built-ins: price and category on,
 * rating, stock, sale and new off until the merchant turns them on.
 */
class Defaults
{
    /** Built-in positions when nothing overrides them: category first, toggles after the attributes. */
    private const BUILTIN = [
        Filter::BUILTIN_CATEGORY => ['input_type' => 'category', 'display_mode' => Filter::DISPLAY_LIST,
            'multiselect' => false, 'active' => true, 'position' => -100],
        Filter::BUILTIN_PRICE => ['input_type' => 'price', 'display_mode' => Filter::DISPLAY_SLIDER,
            'multiselect' => false, 'active' => true, 'position' => 0],
        Filter::BUILTIN_RATING => ['input_type' => 'rating', 'display_mode' => Filter::DISPLAY_RATING,
            'multiselect' => false, 'active' => false, 'position' => 1000],
        Filter::BUILTIN_STOCK => ['input_type' => 'boolean', 'display_mode' => Filter::DISPLAY_TOGGLE,
            'multiselect' => false, 'active' => false, 'position' => 1010],
        Filter::BUILTIN_SALE => ['input_type' => 'boolean', 'display_mode' => Filter::DISPLAY_TOGGLE,
            'multiselect' => false, 'active' => false, 'position' => 1020],
        Filter::BUILTIN_NEW => ['input_type' => 'boolean', 'display_mode' => Filter::DISPLAY_TOGGLE,
            'multiselect' => false, 'active' => false, 'position' => 1030],
    ];

    /**
     * @param string $code
     * @param int $attributeId
     * @param string $inputType normalised input type (select, multiselect, boolean, decimal)
     * @param string|null $swatchType visual, text or null
     * @param int $position the attribute's layered navigation position
     * @return FilterConfig
     */
    public function forAttribute(
        string $code,
        int $attributeId,
        string $inputType,
        ?string $swatchType,
        int $position
    ): FilterConfig {
        $data = ['code' => $code, 'attribute_id' => $attributeId, 'input_type' => $inputType,
            'swatch_type' => $swatchType, 'position' => $position];
        if ($swatchType !== null) {
            // Swatches are compact: up to 12 before "Show more" (Review #1); saved filters keep their own setting.
            $data += ['display_mode' => Filter::DISPLAY_SWATCH, 'multiselect' => true, 'visible_options' => 12];
        } elseif ($inputType === 'decimal') {
            $data += ['display_mode' => Filter::DISPLAY_SLIDER, 'multiselect' => false];
        } elseif ($inputType === 'boolean') {
            $data += ['display_mode' => Filter::DISPLAY_TOGGLE, 'multiselect' => false];
        } else {
            $data += ['display_mode' => Filter::DISPLAY_LIST, 'multiselect' => true];
        }

        return new FilterConfig($data);
    }

    /**
     * @param string $code one of FilterConfigInterface::BUILTIN_CODES
     * @return FilterConfig
     * @throws \InvalidArgumentException for an unknown code
     */
    public function forBuiltin(string $code): FilterConfig
    {
        if (!isset(self::BUILTIN[$code])) {
            throw new \InvalidArgumentException(sprintf('Unknown built-in filter "%s".', $code));
        }

        return new FilterConfig(['code' => $code, 'builtin' => true] + self::BUILTIN[$code]);
    }

    /**
     * Default English label of a built-in (translated when rendered).
     *
     * @param string $code
     * @return string
     */
    public function builtinLabel(string $code): string
    {
        return match ($code) {
            Filter::BUILTIN_RATING => 'Rating',
            Filter::BUILTIN_STOCK => 'In stock',
            Filter::BUILTIN_SALE => 'On sale',
            Filter::BUILTIN_NEW => 'New',
            Filter::BUILTIN_PRICE => 'Price',
            Filter::BUILTIN_CATEGORY => 'Category',
            default => ucfirst($code),
        };
    }
}
