<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Magento\Backend\App\Action;
use Iranimij\OpenFilter\Model\Admin\FilterHints;
use Iranimij\OpenFilter\Model\Admin\FirstRunNotice;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\FlagManager;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Catalog > OpenFilter > Filters.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param PageFactory $pageFactory
     * @param FlagManager $flags
     * @param FilterHints $hints filters sharing a name, search pages without filters
     */
    public function __construct(
        Action\Context $context,
        private readonly PageFactory $pageFactory,
        private readonly FlagManager $flags,
        private readonly FilterHints $hints
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page
     */
    public function execute(): Page
    {
        if ($this->getRequest()->getParam('reviewed') && !$this->flags->getFlagData(FirstRunNotice::FLAG)) {
            $this->flags->saveFlag(FirstRunNotice::FLAG, '1');
        }
        foreach ($this->hints->warnings() as $warning) {
            $this->messageManager->addWarningMessage($warning);
        }
        /** @var Page $page adminhtml pages are backend pages */
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Iranimij_OpenFilter::filters');
        $page->getConfig()->getTitle()->prepend((string) __('Filters'));

        return $page;
    }
}
