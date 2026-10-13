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
 * Order of a filter's options.
 */
class SortBy implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Filter::SORT_POSITION, 'label' => __('As sorted in the attribute')],
            ['value' => Filter::SORT_NAME, 'label' => __('Alphabetical')],
            ['value' => Filter::SORT_COUNT, 'label' => __('Most products first')],
        ];
    }
}
