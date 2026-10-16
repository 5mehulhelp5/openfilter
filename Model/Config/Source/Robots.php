<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Options for a landing page's robots tag (1.2).
 */
class Robots implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'INDEX,FOLLOW', 'label' => __('Index, follow (search engines list the page)')],
            ['value' => 'NOINDEX,FOLLOW', 'label' => __('Noindex, follow')],
            ['value' => 'NOINDEX,NOFOLLOW', 'label' => __('Noindex, nofollow')],
        ];
    }
}
