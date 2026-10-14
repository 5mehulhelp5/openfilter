<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config\Source;

use Iranimij\OpenFilter\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options for "Where filters show on desktop" (1.1). Phones always use the filter drawer.
 */
class Layout implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::LAYOUT_SIDEBAR, 'label' => __('Sidebar next to the products')],
            ['value' => Config::LAYOUT_HORIZONTAL, 'label' => __('Bar above the products')],
        ];
    }
}
