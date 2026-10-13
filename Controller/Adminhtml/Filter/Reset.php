<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Puts one filter back to OpenFilter's defaults (labels per store view and URL names stay).
 */
class Reset extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param FilterConfigRepositoryInterface $repository
     */
    public function __construct(
        Action\Context $context,
        private readonly FilterConfigRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $key = (string) $this->getRequest()->getParam('code');
        $redirect = $this->resultRedirectFactory->create();
        try {
            $this->repository->resetToDefault($key);
            $this->messageManager->addSuccessMessage(__('The filter is back to its default settings.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/index');
        }

        return $redirect->setPath('*/*/edit', ['code' => $key]);
    }
}
