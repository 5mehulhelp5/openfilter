<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Model\Admin\SetupChecklist;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Hides "Set up OpenFilter" above the Filters grid (1.2).
 */
class HideChecklist extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param SetupChecklist $checklist
     */
    public function __construct(
        Action\Context $context,
        private readonly SetupChecklist $checklist
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $this->checklist->hide();

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
