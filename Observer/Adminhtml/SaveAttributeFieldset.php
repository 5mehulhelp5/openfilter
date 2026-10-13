<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Observer\Adminhtml;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigEditor;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * After a product attribute is saved in the admin: cleans OpenFilter's cache (the attribute may have become a filter,
 * stopped being one, or changed its label or position) and saves the "OpenFilter" fieldset when it was posted. Runs
 * inside the attribute's save transaction, so an invalid setting stops the whole save.
 */
class SaveAttributeFieldset implements ObserverInterface
{
    /**
     * @param FilterConfigRepositoryInterface $repository
     * @param SlugRepositoryInterface $slugs
     * @param FilterConfigEditor $editor
     * @param CacheType $cache
     * @param FilterConfigProvider $provider
     */
    public function __construct(
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly SlugRepositoryInterface $slugs,
        private readonly FilterConfigEditor $editor,
        private readonly CacheType $cache,
        private readonly FilterConfigProvider $provider
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws LocalizedException when a posted setting does not fit the attribute
     */
    public function execute(Observer $observer): void
    {
        $attribute = $observer->getEvent()->getData('attribute');
        if (!$attribute instanceof AbstractAttribute || !$attribute->getId()) {
            return;
        }
        $this->cache->clean();
        $this->provider->reset();
        $posted = $attribute->getData('openfilter');
        if (!is_array($posted) || $posted === []) {
            return;
        }
        $attributeId = (int) $attribute->getId();
        try {
            $config = $this->repository->getByAttributeId($attributeId);
        } catch (NoSuchEntityException) {
            return;
        }
        if ($config instanceof FilterConfig) {
            $this->repository->save($this->editor->apply($config, $posted));
        }
        $name = trim((string) ($posted['url_name'] ?? ''));
        if ($name !== '' && $name !== $this->slugs->getAttributeSlug($attributeId, 0)) {
            $this->slugs->saveAttributeSlug($attributeId, 0, $name);
        }
    }
}
