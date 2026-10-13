<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Whether a filter group is open when the page loads.
 */
class Expanded implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'auto', 'label' => __('Automatic (the first filters are open)')],
            ['value' => '1', 'label' => __('Open')],
            ['value' => '0', 'label' => __('Closed')],
        ];
    }
}
