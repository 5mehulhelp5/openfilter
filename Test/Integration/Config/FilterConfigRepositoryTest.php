<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 */
class FilterConfigRepositoryTest extends TestCase
{
    private const CODE = 'of_m1_config_attr';

    private ?ObjectManagerInterface $om = null;

    private ?FilterConfigRepositoryInterface $repository = null;

    protected function setUp(): void
    {
        $this->om = Bootstrap::getObjectManager();
        $this->repository = $this->om->get(FilterConfigRepositoryInterface::class);
        $this->attribute(['Yes-ish', 'No-ish']);
        $this->clean();
    }

    protected function tearDown(): void
    {
        try {
            $this->om->get(ProductAttributeRepositoryInterface::class)->deleteById(self::CODE);
        } catch (NoSuchEntityException) {
            // already gone
        }
        $connection = $this->om->get(ResourceConnection::class)->getConnection();
        $connection->delete($connection->getTableName('openfilter_builtin'));
        $this->clean();
    }

    public function testDefaultsThenSaveValidatesAndPersists(): void
    {
        $config = $this->repository->getByCode(self::CODE);
        self::assertSame(Filter::DISPLAY_LIST, $config->getDisplayMode());
        self::assertTrue($config->isMultiselect());
        self::assertSame($config->getCode(), $this->repository->getByAttributeId((int) $config->getAttributeId())->getCode());

        $config->setDisplayMode(Filter::DISPLAY_TOGGLE)->setMultiselect(false)->setSeoIndexable(true)
            ->setPositionOverride(-5);
        $saved = $this->repository->save($config);

        self::assertSame(Filter::DISPLAY_TOGGLE, $saved->getDisplayMode(), 'two-option select may be a toggle');
        self::assertTrue($saved->isSeoIndexable());
        self::assertSame(-5, $saved->getPosition());
        $this->freshProvider();
        self::assertSame(Filter::DISPLAY_TOGGLE, $this->repository->getByCode(self::CODE)->getDisplayMode());
        self::assertTrue(
            $this->om->get(TypeListInterface::class)->getInvalidated() !== []
                || !$this->om->get(StateInterface::class)->isEnabled('full_page'),
            'full page cache marked invalid'
        );

        $this->expectException(InputException::class);
        $this->repository->save($saved->setDisplayMode(Filter::DISPLAY_SLIDER));
    }

    public function testBuiltinsSwitchOnAndResetKeepsLabels(): void
    {
        $stock = $this->repository->getByCode(Filter::BUILTIN_STOCK);
        self::assertFalse($stock->isActive());
        $this->repository->save($stock->setActive(true));
        $storeId = (int) $this->om->get(StoreManagerInterface::class)->getStore('default')->getId();
        $this->repository->saveLabel(Filter::BUILTIN_STOCK, $storeId, 'Available now');
        $this->repository->saveLabel(self::CODE, $storeId, 'Store label');

        $active = $this->freshProvider()->getActive($storeId);
        self::assertArrayHasKey(Filter::BUILTIN_STOCK, $active);
        self::assertSame('Available now', $active[Filter::BUILTIN_STOCK]->getLabel());
        self::assertSame('Store label', $active[self::CODE]->getLabel());

        $reset = $this->repository->resetToDefault(Filter::BUILTIN_STOCK);
        self::assertFalse($reset->isActive());
        self::assertSame('Available now', $this->freshProvider()->getForStore($storeId)['stock']->getLabel());
        $this->repository->saveLabel(self::CODE, $storeId, null);
        self::assertSame('Of M1', $this->freshProvider()->getForStore($storeId)[self::CODE]->getLabel());
        self::assertNotEmpty($this->repository->getAll());
    }

    public function testThe11SettingsAreValidatedAndPersisted(): void
    {
        $this->repository->save($this->repository->getByCode(self::CODE)->setLogic(Filter::LOGIC_AND));
        $price = $this->repository->getByCode(Filter::BUILTIN_PRICE);
        $this->repository->save($price->setDisplayMode(Filter::DISPLAY_RANGES)->setRanges('0-50,50-100,100-'));
        $category = $this->repository->getByCode(Filter::BUILTIN_CATEGORY);
        $this->repository->save($category->setDisplayMode(Filter::DISPLAY_TREE)->setTreeDepth(2));

        $configs = $this->freshProvider()->getForStore(0);
        self::assertSame(Filter::LOGIC_AND, $configs[self::CODE]->getLogic());
        self::assertSame(Filter::DISPLAY_RANGES, $configs[Filter::BUILTIN_PRICE]->getDisplayMode());
        self::assertSame('0-50,50-100,100-', $configs[Filter::BUILTIN_PRICE]->getRanges());
        self::assertSame(Filter::DISPLAY_TREE, $configs[Filter::BUILTIN_CATEGORY]->getDisplayMode());
        self::assertSame(2, $configs[Filter::BUILTIN_CATEGORY]->getTreeDepth());

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('not a list like');
        $this->repository->save($this->freshRepository()->getByCode(Filter::BUILTIN_PRICE)->setRanges('0-60,50-100'));
    }

    public function testThe12SettingsArePersistedForAttributesAndBuiltins(): void
    {
        $attribute = $this->repository->getByCode(self::CODE);
        $this->repository->save($attribute->setDependsOn(Filter::BUILTIN_PRICE)->setDependsValues(['0-50'])->setQuickBar(true));
        $stock = $this->repository->getByCode(Filter::BUILTIN_STOCK);
        $this->repository->save($stock->setActive(true)->setDependsOn(self::CODE)->setQuickBar(true));

        $configs = $this->freshProvider()->getForStore(0);
        self::assertSame([Filter::BUILTIN_PRICE, ['0-50'], true], [
            $configs[self::CODE]->getDependsOn(), $configs[self::CODE]->getDependsValues(), $configs[self::CODE]->isQuickBar(),
        ]);
        self::assertSame([self::CODE, [], true], [
            $configs['stock']->getDependsOn(), $configs['stock']->getDependsValues(), $configs['stock']->isQuickBar(),
        ]);

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('cannot wait for a pick in itself');
        $this->repository->save($this->freshRepository()->getByCode(self::CODE)->setDependsOn(self::CODE));
    }

    public function testTooltipsPerStoreViewAndALabelClearedKeepsTheTooltip(): void
    {
        $storeId = (int) $this->om->get(StoreManagerInterface::class)->getStore('default')->getId();
        $this->repository->saveTooltip(Filter::BUILTIN_STOCK, 0, 'Ships today');
        $this->repository->saveTooltip(Filter::BUILTIN_STOCK, $storeId, 'Ready to ship');
        $this->repository->saveTooltip(self::CODE, 0, 'For all');
        $this->repository->saveLabel(Filter::BUILTIN_STOCK, $storeId, 'Available');

        self::assertSame('Ready to ship', $this->freshProvider()->getForStore($storeId)[Filter::BUILTIN_STOCK]->getTooltip());
        self::assertSame('Ships today', $this->freshProvider()->getForStore(0)[Filter::BUILTIN_STOCK]->getTooltip());
        self::assertSame('For all', $this->freshProvider()->getForStore($storeId)[self::CODE]->getTooltip());

        $this->repository->saveLabel(Filter::BUILTIN_STOCK, $storeId, null);
        self::assertSame('Ready to ship', $this->freshProvider()->getForStore($storeId)[Filter::BUILTIN_STOCK]->getTooltip());

        $this->repository->saveTooltip(Filter::BUILTIN_STOCK, $storeId, '');
        $this->repository->saveTooltip(self::CODE, 0, null);
        $connection = $this->om->get(ResourceConnection::class)->getConnection();
        $rows = $connection->fetchOne($connection->select()
            ->from($connection->getTableName('openfilter_builtin_store'), ['COUNT(*)'])
            ->where('code = ?', Filter::BUILTIN_STOCK)->where('store_id = ?', $storeId));
        self::assertSame('0', (string) $rows, 'a store row without label and tooltip is removed');
        self::assertSame('Ships today', $this->freshProvider()->getForStore($storeId)[Filter::BUILTIN_STOCK]->getTooltip());
        self::assertNull($this->freshProvider()->getForStore($storeId)[self::CODE]->getTooltip());
    }

    public function testAFilterIsShownInCategoriesThroughItsOwnTable(): void
    {
        $connection = $this->om->get(ResourceConnection::class)->getConnection();
        $table = $connection->getTableName('openfilter_filter_category');
        $connection->insert($table, ['filter_key' => self::CODE, 'category_id' => 2, 'mode' => 'show']);
        try {
            self::assertSame(
                ['filter_key' => self::CODE, 'category_id' => '2', 'mode' => 'show'],
                $connection->fetchRow($connection->select()->from($table)->where('filter_key = ?', self::CODE))
            );
        } finally {
            $connection->delete($table, ['filter_key = ?' => self::CODE]);
        }
    }

    public function testDeletingTheAttributeRemovesItsSettings(): void
    {
        $config = $this->repository->getByCode(self::CODE);
        $this->repository->save($config->setVisibleOptions(3));
        $attributeId = (int) $config->getAttributeId();

        $this->om->get(ProductAttributeRepositoryInterface::class)->deleteById(self::CODE);
        $connection = $this->om->get(ResourceConnection::class)->getConnection();
        $rows = $connection->fetchOne(
            $connection->select()->from($connection->getTableName('openfilter_attribute'), ['COUNT(*)'])
                ->where('attribute_id = ?', $attributeId)
        );

        self::assertSame('0', (string) $rows);
        $this->clean();
        $this->expectException(NoSuchEntityException::class);
        $this->freshRepository()->getByCode(self::CODE);
    }

    public function testWarmCacheRunsNoSqlQuery(): void
    {
        $storeId = (int) $this->om->get(StoreManagerInterface::class)->getStore('default')->getId();
        $this->freshProvider()->getForStore($storeId);
        $provider = $this->freshProvider();
        $profiler = $this->om->get(ResourceConnection::class)->getConnection()->getProfiler();
        $profiler->clear();
        $profiler->setEnabled(true);
        $configs = $provider->getForStore($storeId);
        $queries = (int) $profiler->getTotalNumQueries();
        $profiler->setEnabled(false);

        self::assertArrayHasKey(self::CODE, $configs);
        self::assertSame(0, $queries);
    }

    private function freshProvider(): FilterConfigProvider
    {
        $provider = $this->om->get(FilterConfigProvider::class);
        $provider->reset();

        return $provider;
    }

    private function freshRepository(): FilterConfigRepositoryInterface
    {
        $this->freshProvider();

        return $this->repository;
    }

    private function clean(): void
    {
        $this->om->get(CacheType::class)->clean();
        $this->freshProvider();
    }

    /**
     * @param string[] $labels
     */
    private function attribute(array $labels): void
    {
        try {
            $this->om->get(ProductAttributeRepositoryInterface::class)->get(self::CODE);

            return;
        } catch (NoSuchEntityException) {
            // create below
        }
        $values = [];
        foreach ($labels as $index => $label) {
            $values['option_' . $index] = [$label];
        }
        /** @var Attribute $attribute */
        $attribute = $this->om->get(AttributeFactory::class)->create();
        $attribute->setData([
            'attribute_code' => self::CODE,
            'entity_type_id' => (int) $this->om->get(EavConfig::class)->getEntityType('catalog_product')->getId(),
            'frontend_input' => 'select',
            'backend_type' => 'int',
            'frontend_label' => ['Of M1'],
            'is_user_defined' => 1,
            'is_global' => 1,
            'is_filterable' => 1,
            'is_filterable_in_search' => 1,
            'option' => ['value' => $values],
        ]);
        $attribute->save();
        $this->om->get(EavConfig::class)->clear();
    }
}
