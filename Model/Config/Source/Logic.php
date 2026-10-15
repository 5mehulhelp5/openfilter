<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config\Source;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options for "Products must match" (1.1): any of the picked values, or all of them.
 */
class Logic implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Filter::LOGIC_OR, 'label' => __('Any of the picked values (more products)')],
            ['value' => Filter::LOGIC_AND, 'label' => __('All of the picked values (fewer products)')],
        ];
    }
}
