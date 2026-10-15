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
 * Options for "Where it shows" (1.1).
 */
class CategoryMode implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Filter::CATEGORIES_ALL, 'label' => __('In every category')],
            ['value' => Filter::CATEGORIES_ONLY, 'label' => __('Only in these categories')],
            ['value' => Filter::CATEGORIES_EXCEPT, 'label' => __('In every category except these')],
        ];
    }
}
