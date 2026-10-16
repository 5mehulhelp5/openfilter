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
 * Deletes the landing pages selected in the grid (1.2).
 */
class MassDelete extends Action implements HttpPostActionInterface
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
        $ids = array_map('intval', (array) $this->getRequest()->getParam('selected', []));
        $excluded = $this->getRequest()->getParam('excluded');
        if ($excluded !== null && !$this->getRequest()->getParam('selected')) {
            // "Select all": every page except the ones unticked.
            $skip = $excluded === 'false' ? [] : array_map('intval', (array) $excluded);
            $ids = array_values(array_diff(
                array_map(static fn ($page): int => (int) $page->getId(), $this->pages->getAll()),
                $skip
            ));
        }
        $this->pages->delete($ids);
        $this->messageManager->addSuccessMessage((string) __('You deleted %1 landing pages.', count($ids)));

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
