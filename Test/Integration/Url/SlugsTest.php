<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Url;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Iranimij\OpenFilter\Setup\Patch\Data\DefaultFilterConfig;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 */
class SlugsTest extends TestCase
{
    private const CODE = 'of_m1_slug_color';
    private const SWATCH = 'of_m1_slug_swatch';
    private const STORE = 'of_m1_de';

    private ?ObjectManagerInterface $om = null;

    private ?int $german = null;

    protected function setUp(): void
    {
        $this->om = Bootstrap::getObjectManager();
        $this->german = $this->store();
        $this->attribute(self::CODE, [
            ['Green', 'Grün'],
            ['White', 'Weiß'],
            ['Red', null],
            ['red', null],
        ]);
    }

    protected function tearDown(): void
    {
        $registry = $this->om->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);
        foreach ([self::CODE, self::SWATCH] as $code) {
            try {
                $this->om->get(ProductAttributeRepositoryInterface::class)->deleteById($code);
            } catch (NoSuchEntityException) {
                continue;
            }
        }
        $store = $this->om->create(Store::class)->load(self::STORE, 'code');
        if ($store->getId()) {
            $store->delete();
        }
        $registry->unregister('isSecureArea');
        $this->om->get(CacheType::class)->clean();
    }

    public function testGermanStoreViewGetsTransliteratedNamesAndCollisionsAreNumbered(): void
    {
        [$attributeId, $options] = $this->options(self::CODE);
        $slugs = $this->slugs();

        self::assertSame(
            [$options['Green'] => 'green', $options['White'] => 'white', $options['Red'] => 'red', $options['red'] => 'red-2'],
            $slugs->getOptionSlugs($attributeId, 0)
        );
        self::assertEquals(
            [$options['Green'] => 'gruen', $options['White'] => 'weiss', $options['Red'] => 'red', $options['red'] => 'red-2'],
            $slugs->getOptionSlugs($attributeId, $this->german)
        );
        self::assertSame(self::CODE, $slugs->getAttributeSlug($attributeId, $this->german));
        self::assertSame(2, $this->storeRows($attributeId), 'store rows only where the German name differs');
    }

    public function testNamesStayStableWhenLabelsChangeAndNewOptionsAvoidThem(): void
    {
        [$attributeId, $options] = $this->options(self::CODE);
        /** @var Attribute $attribute */
        $attribute = $this->om->create(ProductAttributeRepositoryInterface::class)->get(self::CODE);
        $attribute->setData('option', ['value' => [
            $options['Green'] => [0 => 'Olive', $this->german => 'Oliv'],
            $options['White'] => [0 => 'White', $this->german => 'Weiß'],
            $options['Red'] => [0 => 'Red'],
            $options['red'] => [0 => 'red'],
            'option_new' => [0 => 'Green', $this->german => 'Grün'],
        ]]);
        $attribute->save();
        [, $after] = $this->options(self::CODE);
        $slugs = $this->slugs();

        self::assertSame('green', $slugs->getOptionSlugs($attributeId, 0)[$options['Green']], 'label change keeps the name');
        self::assertSame('gruen', $slugs->getOptionSlugs($attributeId, $this->german)[$options['Green']]);
        self::assertSame('green-2', $slugs->getOptionSlugs($attributeId, 0)[$after['Green']]);
        self::assertSame('gruen-2', $slugs->getOptionSlugs($attributeId, $this->german)[$after['Green']]);
    }

    public function testDeletedOptionTakesItsNamesAlong(): void
    {
        [$attributeId, $options] = $this->options(self::CODE);
        $connection = $this->om->get(ResourceConnection::class)->getConnection();
        $connection->delete($connection->getTableName('eav_attribute_option'), ['option_id = ?' => $options['Green']]);

        $rows = $connection->fetchOne($connection->select()
            ->from($connection->getTableName('openfilter_option_slug'), ['COUNT(*)'])
            ->where('option_id = ?', $options['Green']));
        self::assertSame('0', (string) $rows);
        self::assertArrayNotHasKey($options['Green'], $this->slugs()->getOptionSlugs($attributeId, 0));
    }

    public function testMerchantEditsAndWarmCacheRunsNoSql(): void
    {
        [$attributeId, $options] = $this->options(self::CODE);
        $repository = $this->om->get(SlugRepositoryInterface::class);
        $repository->saveOptionSlug($options['Red'], $this->german, 'rot');
        $repository->saveAttributeSlug($attributeId, $this->german, 'farbe');

        $provider = $this->slugProvider();
        $provider->forStore($this->german);
        $provider->reset();
        $profiler = $this->om->get(ResourceConnection::class)->getConnection()->getProfiler();
        $profiler->clear();
        $profiler->setEnabled(true);
        $map = $provider->forStore($this->german);
        $queries = (int) $profiler->getTotalNumQueries();
        $profiler->setEnabled(false);

        self::assertSame('rot', $map['options'][$attributeId][$options['Red']]);
        self::assertSame('farbe', $map['attributes'][$attributeId]);
        self::assertSame(0, $queries);
    }

    public function testDefaultsPatchStoresSwatchSettingsAndBuiltinsExist(): void
    {
        $this->attribute(self::SWATCH, [['Navy', null], ['Sand', null]], 'visual');
        [$attributeId] = $this->options(self::SWATCH);
        $this->om->create(DefaultFilterConfig::class)->apply();

        $storage = $this->om->get(FilterConfigStorage::class);
        self::assertSame(Filter::DISPLAY_SWATCH, $storage->attributeRows()[$attributeId]['display_mode'] ?? null);
        self::assertSame('1', (string) ($storage->attributeRows()[$attributeId]['multiselect'] ?? null));
        self::assertEqualsCanonicalizing(Filter::BUILTIN_CODES, array_keys($storage->builtinRows()));
        self::assertSame('1', (string) $storage->builtinRows()['price']['is_active']);
        self::assertSame('0', (string) $storage->builtinRows()['rating']['is_active']);
    }

    private function slugs(): SlugRepositoryInterface
    {
        $this->slugProvider();

        return $this->om->get(SlugRepositoryInterface::class);
    }

    private function slugProvider(): SlugProvider
    {
        $this->om->get(CacheType::class)->clean();
        $provider = $this->om->get(SlugProvider::class);
        $provider->reset();

        return $provider;
    }

    private function storeRows(int $attributeId): int
    {
        $connection = $this->om->get(ResourceConnection::class)->getConnection();

        return (int) $connection->fetchOne($connection->select()
            ->from($connection->getTableName('openfilter_option_slug'), ['COUNT(*)'])
            ->where('attribute_id = ?', $attributeId)
            ->where('store_id = ?', $this->german));
    }

    /**
     * @return array{int, array<string, int>}
     */
    private function options(string $code): array
    {
        $this->om->get(EavConfig::class)->clear();
        /** @var Attribute $attribute */
        $attribute = $this->om->create(ProductAttributeRepositoryInterface::class)->get($code);
        $ids = [];
        foreach ($attribute->setStoreId(0)->getSource()->getAllOptions(false, true) as $option) {
            $ids[(string) $option['label']] = (int) $option['value'];
        }

        return [(int) $attribute->getId(), $ids];
    }

    private function store(): int
    {
        $store = $this->om->create(Store::class)->load(self::STORE, 'code');
        if (!$store->getId()) {
            $default = $this->om->get(StoreManagerInterface::class)->getStore('default');
            $store = $this->om->get(StoreFactory::class)->create();
            $store->setCode(self::STORE)->setName('Deutsch')->setWebsiteId((int) $default->getWebsiteId())
                ->setGroupId((int) $default->getStoreGroupId())->setSortOrder(10)->setIsActive(1)->save();
            $this->om->get(StoreManagerInterface::class)->reinitStores();
        }

        return (int) $store->getId();
    }

    /**
     * @param array<int, array{string, ?string}> $labels admin label, German label
     */
    private function attribute(string $code, array $labels, ?string $swatch = null): void
    {
        try {
            $this->om->get(ProductAttributeRepositoryInterface::class)->deleteById($code);
        } catch (NoSuchEntityException) {
            // fresh
        }
        $values = [];
        $order = [];
        foreach ($labels as $index => [$admin, $german]) {
            $values['option_' . $index] = array_filter([0 => $admin, $this->german => $german], 'is_string');
            $order['option_' . $index] = $index + 1;
        }
        /** @var Attribute $attribute */
        $attribute = $this->om->get(AttributeFactory::class)->create();
        $attribute->setData([
            'attribute_code' => $code,
            'entity_type_id' => (int) $this->om->get(EavConfig::class)->getEntityType('catalog_product')->getId(),
            'frontend_input' => 'select',
            'backend_type' => 'int',
            'frontend_label' => [ucfirst($code)],
            'is_user_defined' => 1,
            'is_global' => 1,
            'is_filterable' => 1,
            'is_filterable_in_search' => 1,
            'option' => ['value' => $values, 'order' => $order],
        ]);
        if ($swatch !== null) {
            $attribute->setData('additional_data', json_encode(['swatch_input_type' => $swatch]));
        }
        $attribute->save();
        $this->om->get(EavConfig::class)->clear();
    }
}
