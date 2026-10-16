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
 * Options for "More products" (1.2).
 */
class Pagination implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::PAGINATION_PAGES, 'label' => __('Page links (Magento\'s pager)')],
            ['value' => Config::PAGINATION_LOAD_MORE, 'label' => __('A "Load more" button')],
            ['value' => Config::PAGINATION_INFINITE, 'label' => __('As shoppers scroll')],
        ];
    }
}
