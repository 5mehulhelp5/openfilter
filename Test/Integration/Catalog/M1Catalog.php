<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Catalog;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as ConfigurableOptionsFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Api\AttributeManagementInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * The M1 catalog (04 · Roadmap, M1 "done when"): simple and configurable products, three customer groups with
 * different prices (Wholesale tier prices), two store views (a German one with German labels and one product not
 * visible there; visibility is store-scoped, status is website-scoped), one anchor category, products outside it,
 * and a search token in every name.
 *
 * Simple products 0..23 in the category; 24..27 outside. Configurables C0..C3 in the category, each with four
 * children (two colours × two sizes, not visible on their own).
 */
class M1Catalog
{
    public const COLOR = 'of_m1_color';
    public const SIZE = 'of_m1_size';
    public const MATERIAL = 'of_m1_material';
    public const STORE = 'of_m1_de';
    public const TOKEN = 'ofmone';
    public const SKU = 'of-m1-';
    public const WHOLESALE = 2;
    /** Simple product not visible in the German store view. */
    public const HIDDEN_IN_GERMAN = 3;

    /** @var array<string, array<int, array{string, string}>> admin and German labels */
    public const OPTIONS = [
        self::COLOR => [['Red', 'Rot'], ['Blue', 'Blau'], ['Green', 'Grün'], ['Black', 'Schwarz']],
        self::SIZE => [['S', 'S'], ['M', 'M'], ['L', 'L']],
        self::MATERIAL => [['Cotton', 'Baumwolle'], ['Wool', 'Wolle']],
    ];

    /** @var array<int, array{colors: string[], sizes: string[], material: string}> configurable definitions */
    public const CONFIGURABLES = [
        ['colors' => ['Red', 'Blue'], 'sizes' => ['S', 'M'], 'material' => 'Cotton'],
        ['colors' => ['Green', 'Black'], 'sizes' => ['M', 'L'], 'material' => 'Wool'],
        ['colors' => ['Red', 'Green'], 'sizes' => ['L', 'S'], 'material' => 'Wool'],
        ['colors' => ['Blue', 'Black'], 'sizes' => ['S', 'L'], 'material' => 'Cotton'],
    ];

    /**
     * @return array{category: int, store: int, options: array<string, array<string, int>>}
     */
    public static function create(): array
    {
        $om = Bootstrap::getObjectManager();
        $store = self::store();
        $options = [];
        foreach (self::OPTIONS as $code => $labels) {
            $options[$code] = self::attribute($code, $labels, $store);
        }
        $om->get(EavConfig::class)->clear();
        $om->get(TypeListInterface::class)->cleanType('config');
        $om->get(TypeListInterface::class)->cleanType('eav');
        $category = self::category('OpenFilter M1', 'of-m1');
        $outside = self::category('OpenFilter M1 outside', 'of-m1-outside');

        $repository = $om->get(ProductRepositoryInterface::class);
        if (!self::exists(self::SKU . '0')) {
            $colors = array_column(self::OPTIONS[self::COLOR], 0);
            $sizes = array_column(self::OPTIONS[self::SIZE], 0);
            for ($i = 0; $i < 28; $i++) {
                $product = self::simple(self::SKU . $i, 'Ofmone simple ' . $i, 10.0 + 2 * $i, Visibility::VISIBILITY_BOTH);
                $product->setData(self::COLOR, $options[self::COLOR][$colors[$i % 4]]);
                $product->setData(self::SIZE, $options[self::SIZE][$sizes[($i + intdiv($i, 4)) % 3]]);
                $product->setData(self::MATERIAL, $options[self::MATERIAL][$i % 3 === 0 ? 'Wool' : 'Cotton']);
                $product->setCategoryIds([$i < 24 ? $category : $outside]);
                if ($i % 2 === 0) {
                    $product->setData('tier_price', [[
                        'website_id' => 0, 'cust_group' => self::WHOLESALE, 'price_qty' => 1, 'price' => (10.0 + 2 * $i) * 0.8,
                    ]]);
                }
                $repository->save($product);
            }
            foreach (self::CONFIGURABLES as $k => $definition) {
                self::configurable($k, $definition, $options, $category);
            }
            $id = (int) $repository->get(self::SKU . self::HIDDEN_IN_GERMAN)->getId();
            $om->get(ProductAction::class)->updateAttributes(
                [$id],
                ['visibility' => Visibility::VISIBILITY_NOT_VISIBLE],
                $store
            );
        }
        SearchIndexReset::forNewAttributes();
        foreach (['catalog_category_product', 'catalog_product_category', 'catalog_product_price', 'catalogsearch_fulltext'] as $indexer) {
            $om->get(IndexerRegistry::class)->get($indexer)->reindexAll();
        }

        return ['category' => $category, 'store' => $store, 'options' => $options];
    }

    /**
     * Expected membership, independent of the engine: which products of the category (or of a search for the token,
     * which also finds the products outside the category) match a selection.
     *
     * @return array<string, array{colors: string[], sizes: string[], material: string, price: float,
     *     wholesale: float, category: bool, german: bool}> by SKU of the visible product
     */
    public static function products(): array
    {
        $colors = array_column(self::OPTIONS[self::COLOR], 0);
        $sizes = array_column(self::OPTIONS[self::SIZE], 0);
        $products = [];
        for ($i = 0; $i < 28; $i++) {
            $price = 10.0 + 2 * $i;
            $products[self::SKU . $i] = [
                'colors' => [$colors[$i % 4]],
                'sizes' => [$sizes[($i + intdiv($i, 4)) % 3]],
                'material' => $i % 3 === 0 ? 'Wool' : 'Cotton',
                'price' => $price,
                'wholesale' => $i % 2 === 0 ? $price * 0.8 : $price,
                'category' => $i < 24,
                'german' => $i !== self::HIDDEN_IN_GERMAN,
            ];
        }
        foreach (self::CONFIGURABLES as $k => $definition) {
            $products[self::SKU . 'c' . $k] = [
                'colors' => $definition['colors'],
                'sizes' => $definition['sizes'],
                'material' => $definition['material'],
                'price' => 50.0 + $k,
                'wholesale' => 50.0 + $k,
                'category' => true,
                'german' => true,
            ];
        }

        return $products;
    }

    /**
     * @param string $sku
     * @return bool
     */
    private static function exists(string $sku): bool
    {
        try {
            Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class)->get($sku);

            return true;
        } catch (NoSuchEntityException) {
            return false;
        }
    }

    /**
     * @param string $sku
     * @param string $name
     * @param float $price
     * @param int $visibility
     * @return Product
     */
    private static function simple(string $sku, string $name, float $price, int $visibility): Product
    {
        /** @var Product $product */
        $product = Bootstrap::getObjectManager()->get(ProductInterfaceFactory::class)->create();
        $product->setTypeId('simple')->setAttributeSetId(4)->setSku($sku)->setName($name)->setPrice($price)
            ->setVisibility($visibility)->setStatus(Status::STATUS_ENABLED)->setWebsiteIds([1])
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);

        return $product;
    }

    /**
     * @param int $k
     * @param array{colors: string[], sizes: string[], material: string} $definition
     * @param array<string, array<string, int>> $options
     * @param int $category
     * @return void
     */
    private static function configurable(int $k, array $definition, array $options, int $category): void
    {
        $om = Bootstrap::getObjectManager();
        $repository = $om->get(ProductRepositoryInterface::class);
        $childIds = [];
        $values = [self::COLOR => [], self::SIZE => []];
        foreach ($definition['colors'] as $color) {
            foreach ($definition['sizes'] as $size) {
                $child = self::simple(
                    self::SKU . "c$k-$color-$size",
                    "Ofmone child $k $color $size",
                    50.0 + $k,
                    Visibility::VISIBILITY_NOT_VISIBLE
                );
                $child->setData(self::COLOR, $options[self::COLOR][$color]);
                $child->setData(self::SIZE, $options[self::SIZE][$size]);
                $child->setData(self::MATERIAL, $options[self::MATERIAL][$definition['material']]);
                $childIds[] = (int) $repository->save($child)->getId();
                $values[self::COLOR][$color] = $options[self::COLOR][$color];
                $values[self::SIZE][$size] = $options[self::SIZE][$size];
            }
        }
        $attributes = [];
        foreach ($values as $code => $ids) {
            $attribute = $om->get(ProductAttributeRepositoryInterface::class)->get($code);
            $attributes[] = [
                'attribute_id' => $attribute->getAttributeId(),
                'code' => $code,
                'label' => $code,
                'position' => count($attributes),
                'values' => array_map(
                    static fn (int $id): array => ['label' => (string) $id, 'attribute_id' => $attribute->getAttributeId(), 'value_index' => $id],
                    array_values($ids)
                ),
            ];
        }
        /** @var Product $product */
        $product = $om->get(ProductInterfaceFactory::class)->create();
        $product->setTypeId(Configurable::TYPE_CODE)->setAttributeSetId(4)->setSku(self::SKU . 'c' . $k)
            ->setName('Ofmone configurable ' . $k)->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)->setWebsiteIds([1])->setCategoryIds([$category])
            ->setStockData(['use_config_manage_stock' => 1, 'is_in_stock' => 1]);
        $product->setData(self::MATERIAL, $options[self::MATERIAL][$definition['material']]);
        $extension = $product->getExtensionAttributes();
        $extension->setConfigurableProductOptions($om->get(ConfigurableOptionsFactory::class)->create($attributes));
        $extension->setConfigurableProductLinks($childIds);
        $product->setExtensionAttributes($extension);
        $repository->save($product);
    }

    /**
     * @return int German store view id
     */
    private static function store(): int
    {
        $om = Bootstrap::getObjectManager();
        $store = $om->create(Store::class)->load(self::STORE, 'code');
        if (!$store->getId()) {
            $default = $om->get(StoreManagerInterface::class)->getStore('default');
            $store = $om->get(StoreFactory::class)->create();
            $store->setCode(self::STORE)->setName('Deutsch')->setWebsiteId((int) $default->getWebsiteId())
                ->setGroupId((int) $default->getStoreGroupId())->setSortOrder(10)->setIsActive(1)->save();
            $om->get(StoreManagerInterface::class)->reinitStores();
        }

        return (int) $store->getId();
    }

    /**
     * @param string $code
     * @param array<int, array{string, string}> $labels
     * @param int $store
     * @return array<string, int> option id by admin label
     */
    private static function attribute(string $code, array $labels, int $store): array
    {
        $om = Bootstrap::getObjectManager();
        try {
            /** @var Attribute $attribute */
            $attribute = $om->create(ProductAttributeRepositoryInterface::class)->get($code);
        } catch (NoSuchEntityException) {
            $values = [];
            $order = [];
            foreach ($labels as $index => [$admin, $german]) {
                $values['option_' . $index] = [0 => $admin, $store => $german];
                $order['option_' . $index] = $index + 1;
            }
            /** @var Attribute $attribute */
            $attribute = $om->get(AttributeFactory::class)->create();
            $attribute->setData([
                'attribute_code' => $code,
                'entity_type_id' => (int) $om->get(EavConfig::class)->getEntityType('catalog_product')->getId(),
                'frontend_input' => 'select',
                'backend_type' => 'int',
                'frontend_label' => [ucfirst(substr($code, strlen('of_m1_')))],
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
                910
            );
            $om->get(EavConfig::class)->clear();
            $attribute = $om->create(ProductAttributeRepositoryInterface::class)->get($code);
        }
        $ids = [];
        foreach ($attribute->setStoreId(0)->getSource()->getAllOptions(false, true) as $option) {
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
        $om = Bootstrap::getObjectManager();
        $existing = $om->get(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class)->create()
            ->addAttributeToFilter('url_key', $urlKey)->getFirstItem();
        if ($existing->getId()) {
            return (int) $existing->getId();
        }
        $category = $om->get(CategoryInterfaceFactory::class)->create();
        $category->setName($name)->setParentId(2)->setIsActive(true)->setIsAnchor(true)->setUrlKey($urlKey)
            ->setStoreId(0);

        return (int) $om->get(CategoryRepositoryInterface::class)->save($category)->getId();
    }
}
