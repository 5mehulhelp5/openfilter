<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Search;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Api\AttributeManagementInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Registry;
use Iranimij\OpenFilter\Test\Integration\Catalog\SearchIndexReset;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * A small catalog for facet count tests: three filterable select attributes, one anchor category with 36 enabled
 * products in deterministic combinations, and 4 products outside it (so category scoping is part of every count).
 */
class SpikeCatalog
{
    public const COLOR = 'of_spike_color';
    public const SIZE = 'of_spike_size';
    public const MATERIAL = 'of_spike_material';
    private const SKU_PREFIX = 'of-spike-';

    /** @var array<string, string[]> */
    public const OPTIONS = [
        self::COLOR => ['Red', 'Blue', 'Green', 'Black'],
        self::SIZE => ['S', 'M', 'L'],
        self::MATERIAL => ['Cotton', 'Wool'],
    ];

    /**
     * Creates the catalog (or finds it) and returns the category id and option ids by attribute and label.
     *
     * @return array{category: int, options: array<string, array<string, int>>}
     */
    public static function create(): array
    {
        $om = Bootstrap::getObjectManager();
        $options = [];
        foreach (self::OPTIONS as $code => $labels) {
            $options[$code] = self::attribute($code, $labels);
        }
        $om->get(EavConfig::class)->clear();
        $om->get(TypeListInterface::class)->cleanType('config');
        $om->get(TypeListInterface::class)->cleanType('eav');

        $categoryId = self::category('OpenFilter spike', 'of-spike');
        $otherId = self::category('OpenFilter spike other', 'of-spike-other');
        $repository = $om->get(ProductRepositoryInterface::class);
        for ($i = 0; $i < 40; $i++) {
            $sku = self::SKU_PREFIX . $i;
            try {
                $repository->get($sku);
                continue;
            } catch (NoSuchEntityException) {
                // create below
            }
            $colors = self::OPTIONS[self::COLOR];
            $sizes = self::OPTIONS[self::SIZE];
            $product = $om->get(ProductInterfaceFactory::class)->create();
            $product->setTypeId('simple')->setAttributeSetId(4)->setSku($sku)->setName('OpenFilter spike ' . $i)
                ->setPrice(10 + $i)->setVisibility(Visibility::VISIBILITY_BOTH)->setStatus(Status::STATUS_ENABLED)
                ->setWebsiteIds([1])
                ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);
            $product->setData(self::COLOR, $options[self::COLOR][$colors[$i % 4]]);
            $product->setData(self::SIZE, $options[self::SIZE][$sizes[($i * 7 + intdiv($i, 5)) % 3]]);
            $product->setData(self::MATERIAL, $options[self::MATERIAL][$i % 5 === 0 || $i % 7 === 0 ? 'Wool' : 'Cotton']);
            $product->setCategoryIds([$i < 36 ? $categoryId : $otherId]);
            $repository->save($product);
        }
        SearchIndexReset::forNewAttributes();
        foreach (['catalog_category_product', 'catalog_product_category', 'catalogsearch_fulltext'] as $indexer) {
            $om->get(IndexerRegistry::class)->get($indexer)->reindexAll();
        }

        return ['category' => $categoryId, 'options' => $options];
    }

    /**
     * Removes the products, categories and attributes again.
     */
    public static function remove(): void
    {
        $om = Bootstrap::getObjectManager();
        $registry = $om->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);
        $repository = $om->get(ProductRepositoryInterface::class);
        for ($i = 0; $i < 40; $i++) {
            try {
                $repository->deleteById(self::SKU_PREFIX . $i);
            } catch (NoSuchEntityException) {
                continue;
            }
        }
        foreach (['of-spike', 'of-spike-other'] as $urlKey) {
            $id = self::findCategory($urlKey);
            if ($id !== null) {
                $om->get(CategoryRepositoryInterface::class)->deleteByIdentifier($id);
            }
        }
        foreach (array_keys(self::OPTIONS) as $code) {
            try {
                $om->get(ProductAttributeRepositoryInterface::class)->deleteById($code);
            } catch (NoSuchEntityException) {
                continue;
            }
        }
        $registry->unregister('isSecureArea');
        $om->get(EavConfig::class)->clear();
        $om->get(TypeListInterface::class)->cleanType('config');
    }

    /**
     * @param string $code
     * @param string[] $labels
     * @return array<string, int> option id by label
     */
    private static function attribute(string $code, array $labels): array
    {
        $om = Bootstrap::getObjectManager();
        try {
            /** @var Attribute $attribute */
            $attribute = $om->get(ProductAttributeRepositoryInterface::class)->get($code);
        } catch (NoSuchEntityException) {
            $values = [];
            $order = [];
            foreach ($labels as $index => $label) {
                $values['option_' . $index] = [$label];
                $order['option_' . $index] = $index + 1;
            }
            /** @var Attribute $attribute */
            $attribute = $om->get(AttributeFactory::class)->create();
            $attribute->setData([
                'attribute_code' => $code,
                'entity_type_id' => (int) $om->get(EavConfig::class)->getEntityType('catalog_product')->getId(),
                'frontend_input' => 'select',
                'backend_type' => 'int',
                'frontend_label' => [ucfirst(substr($code, strlen('of_spike_')))],
                'is_user_defined' => 1,
                'is_global' => 1,
                'is_filterable' => 1,
                'is_filterable_in_search' => 1,
                'is_searchable' => 0,
                'is_visible_on_front' => 0,
                'used_in_product_listing' => 0,
                'option' => ['value' => $values, 'order' => $order],
            ]);
            $attribute->save();
            $set = $om->get(AttributeSetRepositoryInterface::class)->get(4);
            $om->get(AttributeManagementInterface::class)->assign(
                'catalog_product',
                4,
                (int) $set->getDefaultGroupId(),
                $code,
                900
            );
            $om->get(EavConfig::class)->clear();
            $attribute = $om->create(ProductAttributeRepositoryInterface::class)->get($code);
        }
        $ids = [];
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $ids[(string) $option['label']] = (int) $option['value'];
        }

        return $ids;
    }

    /**
     * @param string $name
     * @param string $urlKey
     * @return int
     */
    private static function category(string $name, string $urlKey): int
    {
        $existing = self::findCategory($urlKey);
        if ($existing !== null) {
            return $existing;
        }
        $om = Bootstrap::getObjectManager();
        $category = $om->get(CategoryInterfaceFactory::class)->create();
        $category->setName($name)->setParentId(2)->setIsActive(true)->setIsAnchor(true)->setUrlKey($urlKey)
            ->setStoreId(0);

        return (int) $om->get(CategoryRepositoryInterface::class)->save($category)->getId();
    }

    /**
     * @param string $urlKey
     * @return int|null
     */
    private static function findCategory(string $urlKey): ?int
    {
        $collection = Bootstrap::getObjectManager()
            ->create(\Magento\Catalog\Model\ResourceModel\Category\Collection::class)
            ->addAttributeToFilter('url_key', $urlKey);
        $id = $collection->getFirstItem()->getId();

        return $id === null ? null : (int) $id;
    }
}
