<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

/**
 * The form of one filter (attribute or built-in).
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param PageFactory $pageFactory
     * @param FilterConfigRepositoryInterface $repository
     * @param Defaults $defaults
     */
    public function __construct(
        Action\Context $context,
        private readonly PageFactory $pageFactory,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly Defaults $defaults
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page|Redirect
     */
    public function execute()
    {
        try {
            $config = $this->repository->getByCode((string) $this->getRequest()->getParam('code'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
        /** @var Page $page adminhtml pages are backend pages */
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Iranimij_OpenFilter::filters');
        $title = $config->getLabel()
            ?? ($config->isBuiltin() ? (string) __($this->defaults->builtinLabel($config->getCode())) : $config->getCode());
        $page->getConfig()->getTitle()->prepend((string) __('Filter: %1', $title));

        return $page;
    }
}
