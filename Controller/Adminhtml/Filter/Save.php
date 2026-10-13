<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigEditor;
use Iranimij\OpenFilter\Model\Url\OptionSlugBatch;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Saves the filter form: settings, labels per store view and URL names, all or nothing.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param FilterConfigRepositoryInterface $repository
     * @param FilterConfigEditor $editor
     * @param SlugRepositoryInterface $slugs
     * @param OptionSlugBatch $optionSlugs
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resource
     */
    public function __construct(
        Action\Context $context,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly FilterConfigEditor $editor,
        private readonly SlugRepositoryInterface $slugs,
        private readonly OptionSlugBatch $optionSlugs,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resource
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
        $key = (string) ($post['key'] ?? '');
        $redirect = $this->resultRedirectFactory->create();
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $config = $this->repository->getByCode($key);
            if (!$config instanceof FilterConfig) {
                throw new LocalizedException(__('This filter cannot be edited here.'));
            }
            $saved = $this->repository->save($this->editor->apply($config, $post));
            $this->saveLabels($key, $post);
            $attributeId = $saved->isBuiltin() ? null : $saved->getAttributeId();
            if ($attributeId !== null) {
                $this->saveUrlNames($attributeId, $post);
            }
            $connection->commit();
        } catch (LocalizedException $e) {
            $connection->rollBack();
            $this->messageManager->addErrorMessage($e->getMessage());

            return $key === '' ? $redirect->setPath('*/*/index') : $redirect->setPath('*/*/edit', ['code' => $key]);
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        $this->messageManager->addSuccessMessage(__('You saved the filter.'));

        return $redirect->setPath('*/*/edit', ['code' => $key]);
    }

    /**
     * @param string $key
     * @param array<string, mixed> $post
     * @return void
     */
    private function saveLabels(string $key, array $post): void
    {
        $storeIds = array_merge([0], array_map(
            static fn ($store): int => (int) $store->getId(),
            array_values($this->storeManager->getStores())
        ));
        foreach ($storeIds as $storeId) {
            if (array_key_exists('label_' . $storeId, $post)) {
                $label = trim((string) $post['label_' . $storeId]);
                $this->repository->saveLabel($key, $storeId, $label === '' ? null : $label);
            }
        }
    }

    /**
     * @param int $attributeId
     * @param array<string, mixed> $post
     * @return void
     * @throws LocalizedException
     */
    private function saveUrlNames(int $attributeId, array $post): void
    {
        $name = trim((string) ($post['url_name'] ?? ''));
        if ($name !== '' && $name !== $this->slugs->getAttributeSlug($attributeId, 0)) {
            $this->slugs->saveAttributeSlug($attributeId, 0, $name);
        }
        $names = [];
        $regenerate = [];
        foreach ((array) ($post['option_urls'] ?? []) as $row) {
            if (!is_array($row) || !isset($row['option_id'])) {
                continue;
            }
            $optionId = (int) $row['option_id'];
            foreach ($row as $field => $value) {
                if (is_string($field) && preg_match('/^url_name_(\d+)$/', $field, $match) === 1) {
                    $names[$optionId][(int) $match[1]] = (string) $value;
                }
            }
            if ((string) ($row['regenerate'] ?? '0') === '1') {
                $regenerate[] = $optionId;
            }
        }
        if ($names !== [] || $regenerate !== []) {
            $this->optionSlugs->save($attributeId, $names, $regenerate);
        }
    }
}
