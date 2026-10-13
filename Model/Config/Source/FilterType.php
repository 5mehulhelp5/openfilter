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
 * Attribute filter or one of OpenFilter's built-in filters.
 */
class FilterType implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [['value' => 0, 'label' => __('Attribute')], ['value' => 1, 'label' => __('Built-in')]];
    }
}
