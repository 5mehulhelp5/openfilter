<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Controller\Adminhtml\Filter;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Admin\FormScope;
use Iranimij\OpenFilter\Model\Cache\AfterRollback;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigEditor;
use Iranimij\OpenFilter\Model\Url\AttributeSlugScope;
use Iranimij\OpenFilter\Model\Url\OptionSlugBatch;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * Saves the filter form for the scope it shows, all or nothing: for all store views the settings, the shared
 * label, tooltip and URL names; for a store view only its own label, tooltip and URL names (settings and other store
 * views stay as they are, whatever else the request carries).
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Iranimij_OpenFilter::filters';

    /**
     * @param Action\Context $context
     * @param FilterConfigRepositoryInterface $repository
     * @param FilterConfigEditor $editor
     * @param AttributeSlugScope $attributeSlugs
     * @param OptionSlugBatch $optionSlugs
     * @param FormScope $formScope
     * @param ResourceConnection $resource
     * @param AfterRollback $afterRollback
     */
    public function __construct(
        Action\Context $context,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly FilterConfigEditor $editor,
        private readonly AttributeSlugScope $attributeSlugs,
        private readonly OptionSlugBatch $optionSlugs,
        private readonly FormScope $formScope,
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
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var array<string, mixed> $post */
        $post = (array) $request->getPostValue();
        $key = (string) ($post['key'] ?? '');
        $scope = $this->formScope->resolve($post[FormScope::PARAM] ?? 0);
        $redirect = $this->resultRedirectFactory->create();
        $back = ['code' => $key] + ($scope !== 0 ? [FormScope::PARAM => $scope] : []);
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $config = $this->repository->getByCode($key);
            if (!$config instanceof FilterConfig) {
                throw new LocalizedException(__('This filter cannot be edited here.'));
            }
            if ($scope === 0) {
                $config = $this->repository->save($this->editor->apply($config, $post));
            }
            if (array_key_exists('label_' . $scope, $post)) {
                $label = trim((string) $post['label_' . $scope]);
                $this->repository->saveLabel($key, $scope, $label === '' ? null : $label);
            }
            if (array_key_exists('tooltip_' . $scope, $post)) {
                $tooltip = trim((string) $post['tooltip_' . $scope]);
                $this->repository->saveTooltip($key, $scope, $tooltip === '' ? null : $tooltip);
            }
            $attributeId = $config->isBuiltin() ? null : $config->getAttributeId();
            if ($attributeId !== null) {
                $this->saveUrlNames($attributeId, $scope, $post);
            }
            $connection->commit();
        } catch (LocalizedException $e) {
            $connection->rollBack();
            $this->afterRollback->forget();
            $this->messageManager->addErrorMessage($e->getMessage());

            return $key === '' ? $redirect->setPath('*/*/index') : $redirect->setPath('*/*/edit', $back);
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->afterRollback->forget();
            throw $e;
        }
        $this->messageManager->addSuccessMessage((string) __('You saved the filter.'));

        return $redirect->setPath('*/*/edit', $back);
    }

    /**
     * URL names of one scope: the filter's name ("url_name" for all store views, "url_name_store" for a store
     * view) and the options' names in column url_name_<scope>; "generate again" acts on this scope (all store
     * views = everywhere, as in 1.0).
     *
     * @param int $attributeId
     * @param int $scope
     * @param array<string, mixed> $post
     * @return void
     * @throws LocalizedException
     */
    private function saveUrlNames(int $attributeId, int $scope, array $post): void
    {
        $field = $scope === 0 ? 'url_name' : 'url_name_store';
        if (array_key_exists($field, $post)) {
            $this->attributeSlugs->save($attributeId, $scope, (string) $post[$field]);
        }
        $names = [];
        $regenerate = [];
        foreach ((array) ($post['option_urls'] ?? []) as $row) {
            if (!is_array($row) || !isset($row['option_id'])) {
                continue;
            }
            $optionId = (int) $row['option_id'];
            if (array_key_exists('url_name_' . $scope, $row)) {
                $names[$optionId][$scope] = (string) $row['url_name_' . $scope];
            }
            if ((string) ($row['regenerate'] ?? '0') === '1') {
                $regenerate[] = $optionId;
            }
        }
        if ($names !== [] || $regenerate !== []) {
            $this->optionSlugs->save($attributeId, $names, $regenerate, $scope === 0 ? null : $scope);
        }
    }
}
