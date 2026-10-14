<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigEditor;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Saves the rows edited in the Filters grid; each row is saved on its own and reports its own error.
 */
class InlineEdit extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param JsonFactory $jsonFactory
     * @param FilterConfigRepositoryInterface $repository
     * @param FilterConfigEditor $editor
     */
    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly FilterConfigEditor $editor
    ) {
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $messages = [];
        $items = $this->getRequest()->getParam('items', []);
        if (!$this->getRequest()->getParam('isAjax') || !is_array($items) || $items === []) {
            $messages[] = (string) __('Please correct the data sent.');
        } else {
            foreach ($items as $key => $values) {
                $error = $this->saveRow((string) $key, is_array($values) ? $values : []);
                if ($error !== null) {
                    $messages[] = $error;
                }
            }
        }

        return $this->jsonFactory->create()->setData(['messages' => $messages, 'error' => $messages !== []]);
    }

    /**
     * @param string $key
     * @param array<string, mixed> $values
     * @return string|null error message
     */
    private function saveRow(string $key, array $values): ?string
    {
        try {
            $config = $this->repository->getByCode($key);
            if (!$config instanceof FilterConfig) {
                return (string) __('[%1] This filter cannot be edited here.', $key);
            }
            $this->repository->save($this->editor->apply($config, $values));
        } catch (LocalizedException $e) {
            return (string) __('[%1] %2', $key, $e->getMessage());
        }

        return null;
    }
}
