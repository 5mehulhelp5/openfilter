<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\AfterRollback;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;

/**
 * Saves "Sort filters": the posted order becomes the positions 10, 20, 30… (only changed filters are written),
 * all or nothing.
 */
class SortSave extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /** Gap between positions, so a filter can later be put between two without renumbering. */
    public const STEP = 10;

    /**
     * @param Action\Context $context
     * @param FilterConfigRepositoryInterface $repository
     * @param ResourceConnection $resource
     * @param AfterRollback $afterRollback
     */
    public function __construct(
        Action\Context $context,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly ResourceConnection $resource,
        private readonly AfterRollback $afterRollback
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $order = array_values(array_unique(array_map('strval', (array) $this->getRequest()->getParam('order', []))));
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/sort');
        if ($order === []) {
            $this->messageManager->addErrorMessage((string) __('No filters were sent.'));

            return $redirect;
        }
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($order as $index => $key) {
                $config = $this->repository->getByCode($key);
                $position = ($index + 1) * self::STEP;
                if ($config->getPosition() !== $position || $config->getPositionOverride() === null) {
                    $this->repository->save($config->setPositionOverride($position));
                }
            }
            $connection->commit();
        } catch (LocalizedException $e) {
            $connection->rollBack();
            $this->afterRollback->forget();
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect;
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->afterRollback->forget();
            throw $e;
        }
        $this->messageManager->addSuccessMessage((string) __('The new order of the filters is saved.'));

        return $redirect;
    }
}
