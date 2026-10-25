<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Block\Adminhtml;

use Iranimij\OpenFilter\Model\Admin\SetupChecklist as Checklist;
use Magento\Backend\Block\Template;

/**
 * "Set up OpenFilter" above the Filters grid (1.2), until the merchant hides it.
 */
class SetupChecklist extends Template
{
    /**
     * @param Template\Context $context
     * @param Checklist $checklist
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly Checklist $checklist,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return bool
     */
    public function isShown(): bool
    {
        return !$this->checklist->isHidden();
    }

    /**
     * @return array<int, array{code: string, title: string, done: bool, url: string, link: string}>
     */
    public function getSteps(): array
    {
        return $this->checklist->steps();
    }

    /**
     * @return string
     */
    public function getHideUrl(): string
    {
        return $this->getUrl('openfilter/filter/hideChecklist');
    }
}
