<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Ui\Component\MassAction\SelectedFilters;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Show, hide or reset the selected filters of the grid.
 */
class MassAction extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param FilterConfigRepositoryInterface $repository
     * @param SelectedFilters $selected
     */
    public function __construct(
        Action\Context $context,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly SelectedFilters $selected
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('*/*/index');
        $action = (string) $this->getRequest()->getParam('do');
        if (!in_array($action, ['show', 'hide', 'reset'], true)) {
            $this->messageManager->addErrorMessage(__('Please correct the data sent.'));

            return $redirect;
        }
        try {
            $keys = $this->selected->keys();
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect;
        }
        $done = 0;
        foreach ($keys as $key) {
            try {
                if ($action === 'reset') {
                    $this->repository->resetToDefault($key);
                } else {
                    $config = $this->repository->getByCode($key);
                    $this->repository->save($config->setActive($action === 'show'));
                }
                $done++;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage(__('[%1] %2', $key, $e->getMessage()));
            }
        }
        $this->messageManager->addSuccessMessage(match ($action) {
            'show' => __('%1 filter(s) are shown on the storefront.', $done),
            'hide' => __('%1 filter(s) are hidden from the storefront.', $done),
            default => __('%1 filter(s) are back to their defaults.', $done),
        });

        return $redirect;
    }
}
