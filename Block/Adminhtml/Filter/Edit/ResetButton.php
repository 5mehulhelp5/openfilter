<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Block\Adminhtml\Filter\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Reset to defaults" (asks first; labels per store view and URL names stay).
 */
class ResetButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Reset to defaults'),
            'class' => 'reset',
            'on_click' => sprintf(
                'deleteConfirm(%s, %s, {data: {}})',
                json_encode((string) __('Put this filter back to its default settings? Labels per store view and URL names are kept.')),
                json_encode($this->url('*/*/reset', ['code' => $this->code()]))
            ),
            'sort_order' => 20,
        ];
    }
}
