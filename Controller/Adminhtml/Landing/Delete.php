<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Landing;

use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Deletes a landing page (1.2).
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::landing_pages';

    /**
     * @param Action\Context $context
     * @param LandingPages $pages
     */
    public function __construct(
        Action\Context $context,
        private readonly LandingPages $pages
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $this->pages->delete([(int) $this->getRequest()->getParam('page_id')]);
        $this->messageManager->addSuccessMessage((string) __('You deleted the landing page.'));

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
