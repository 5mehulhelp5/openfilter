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

    private ObjectManagerInterface $om;

    private FilterConfigRepositoryInterface $repository;

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
