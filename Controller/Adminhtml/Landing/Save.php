<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Landing;

use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Iranimij\OpenFilter\Model\Landing\UrlParser;
use Iranimij\OpenFilter\Ui\DataProvider\LandingForm;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Saves a landing page (1.2). The filters come from the address of a filtered category page (read with the URL names
 * of the page's first store view); an existing page keeps its filters when the address is left empty.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::landing_pages';

    /**
     * @param Action\Context $context
     * @param LandingPages $pages
     * @param UrlParser $urlParser
     * @param StoreManagerInterface $storeManager
     * @param DataPersistorInterface $dataPersistor the values of a save that failed, for the form
     */
    public function __construct(
        Action\Context $context,
        private readonly LandingPages $pages,
        private readonly UrlParser $urlParser,
        private readonly StoreManagerInterface $storeManager,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var array<string, mixed> $post */
        $post = (array) $request->getPostValue();
        $redirect = $this->resultRedirectFactory->create();
        $pageId = (int) ($post['page_id'] ?? 0);
        try {
            $current = $pageId > 0 ? $this->pages->get($pageId)->toArray() : [];
            $storeIds = array_map('intval', (array) ($post['store_ids'] ?? [0]));
            $data = array_intersect_key($post, array_flip([
                'is_active', 'url_key', 'title', 'h1', 'meta_title', 'meta_description', 'description', 'robots',
            ])) + ['store_ids' => $storeIds] + $current;
            $address = trim((string) ($post['page_url'] ?? ''));
            if ($address !== '') {
                $data = $this->urlParser->parse($address, $this->storeFor($storeIds)) + $data;
            }
            $saved = $this->pages->save(new LandingPage(['page_id' => $pageId > 0 ? $pageId : null] + $data));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set(LandingForm::PERSISTOR_KEY, $post);

            return $pageId > 0 ? $redirect->setPath('*/*/edit', ['page_id' => $pageId]) : $redirect->setPath('*/*/new');
        }
        $this->messageManager->addSuccessMessage((string) __('You saved the landing page.'));

        return $this->getRequest()->getParam('back')
            ? $redirect->setPath('*/*/edit', ['page_id' => $saved->getId()])
            : $redirect->setPath('*/*/index');
    }

    /**
     * The store view whose URL names read the address: the page's first store view, or the default one.
     *
     * @param int[] $storeIds
     * @return int
     */
    private function storeFor(array $storeIds): int
    {
        $specific = array_values(array_filter($storeIds));

        return $specific !== [] ? $specific[0] : (int) $this->storeManager->getDefaultStoreView()?->getId();
    }
}
