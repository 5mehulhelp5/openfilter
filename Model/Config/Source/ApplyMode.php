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
 * Options for "When results update on desktop" (1.1). Phones always stage choices in the drawer.
 */
class ApplyMode implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::APPLY_INSTANT, 'label' => __('At once, with every click')],
            ['value' => Config::APPLY_BUTTON, 'label' => __('After "Show N products" (shoppers pick several first)')],
        ];
    }
}
