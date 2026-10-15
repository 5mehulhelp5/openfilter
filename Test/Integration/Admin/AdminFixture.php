<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Admin;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Test attributes and a clean OpenFilter state for the M2 admin tests.
 */
class AdminFixture
{
    /**
     * A filterable select attribute (created once per code).
     *
     * @param string $code
     * @param string[] $labels option admin labels
     * @param array<string, mixed> $data extra attribute data
     * @return int attribute id
     */
    public static function selectAttribute(string $code, array $labels, array $data = []): int
    {
        $om = Bootstrap::getObjectManager();
        try {
            return (int) $om->get(ProductAttributeRepositoryInterface::class)->get($code)->getAttributeId();
        } catch (NoSuchEntityException) {
            // create below
        }
        $values = [];
        $order = [];
        foreach ($labels as $index => $label) {
            $values['option_' . $index] = [$label];
            $order['option_' . $index] = $index + 1;
        }
        /** @var Attribute $attribute */
        $attribute = $om->get(AttributeFactory::class)->create();
        $attribute->setData($data + [
            'attribute_code' => $code,
            'entity_type_id' => (int) $om->get(EavConfig::class)->getEntityType('catalog_product')->getId(),
            'frontend_input' => 'select',
            'backend_type' => 'int',
            'frontend_label' => [ucfirst(str_replace('_', ' ', $code))],
            'is_user_defined' => 1,
            'is_global' => 1,
            'is_filterable' => 1,
            'is_filterable_in_search' => 1,
            'option' => ['value' => $values, 'order' => $order],
        ]);
        $attribute->save();
        $om->get(EavConfig::class)->clear();
        self::clean();

        return (int) $attribute->getAttributeId();
    }

    /**
     * @param string $code
     * @return void
     */
    public static function deleteAttribute(string $code): void
    {
        try {
            Bootstrap::getObjectManager()->get(ProductAttributeRepositoryInterface::class)->deleteById($code);
        } catch (NoSuchEntityException) {
            // already gone
        }
        Bootstrap::getObjectManager()->get(EavConfig::class)->clear();
        self::clean();
    }

    /**
     * Forgets saved built-in settings and cleans OpenFilter's cache.
     *
     * @return void
     */
    public static function resetBuiltins(): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $connection->delete($connection->getTableName('openfilter_builtin'));
        self::clean();
    }

    /**
     * A store view in the default website (created once per code).
     *
     * @param string $code
     * @param string $name
     * @param int $sortOrder
     * @return int store view id
     */
    public static function storeView(string $code, string $name, int $sortOrder = 10): int
    {
        $om = Bootstrap::getObjectManager();
        $store = $om->create(Store::class)->load($code, 'code');
        if (!$store->getId()) {
            $default = $om->get(StoreManagerInterface::class)->getStore('default');
            $store = $om->get(StoreFactory::class)->create();
            $store->setCode($code)->setName($name)->setWebsiteId((int) $default->getWebsiteId())
                ->setGroupId((int) $default->getStoreGroupId())->setSortOrder($sortOrder)->setIsActive(1)->save();
            $om->get(StoreManagerInterface::class)->reinitStores();
        }
        self::clean();

        return (int) $store->getId();
    }

    /**
     * @param string $code
     * @return void
     */
    public static function deleteStoreView(string $code): void
    {
        $om = Bootstrap::getObjectManager();
        $store = $om->create(Store::class)->load($code, 'code');
        if ($store->getId()) {
            $registry = $om->get(Registry::class);
            $secure = $registry->registry('isSecureArea');
            $registry->unregister('isSecureArea');
            $registry->register('isSecureArea', true);
            try {
                $store->delete();
            } finally {
                $registry->unregister('isSecureArea');
                if ($secure !== null) {
                    $registry->register('isSecureArea', $secure);
                }
            }
            $om->get(StoreManagerInterface::class)->reinitStores();
        }
        self::clean();
    }

    /**
     * @return void
     */
    public static function clean(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();
        $om->get(SlugProvider::class)->reset();
    }
}
