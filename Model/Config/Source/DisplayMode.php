<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config\Source;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Display types in the merchant's words (08 · UX Spec §5).
 */
class DisplayMode implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->labels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return array<string, Phrase>
     */
    public function labels(): array
    {
        return [
            Filter::DISPLAY_LIST => __('Checkbox list'),
            Filter::DISPLAY_SWATCH => __('Swatches'),
            Filter::DISPLAY_SLIDER => __('Slider'),
            Filter::DISPLAY_FROM_TO => __('From–to fields'),
            Filter::DISPLAY_RATING => __('Stars'),
            Filter::DISPLAY_TOGGLE => __('On/off switch'),
            Filter::DISPLAY_DROPDOWN => __('Dropdown'),
            Filter::DISPLAY_RANGES => __('Ranges (for example 0–50, 50–100)'),
            Filter::DISPLAY_TREE => __('Category tree'),
        ];
    }
}
