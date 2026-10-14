<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config\Source;

use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options for "How filter counts are computed".
 */
class CountStrategy implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => StrategyResolver::AUTO, 'label' => __('Automatic (recommended)')],
            ['value' => StrategyResolver::SINGLE_QUERY, 'label' => __('One search request per page')],
            ['value' => StrategyResolver::PER_ATTRIBUTE, 'label' => __('One extra search request per active filter')],
        ];
    }
}
