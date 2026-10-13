<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Admin;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Config\Backend\NonNegativeNumber;
use Iranimij\OpenFilter\Model\Config\Backend\PageCacheValue;
use Magento\Config\Model\Config as ConfigModel;
use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Config\Model\Config\Structure\Data as ConfigStructureData;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * System configuration of M2: storefront settings with server-side validation and page cache invalidation.
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class SettingsTest extends TestCase
{
    public function testEveryStorefrontSettingHasADefaultAndABackendModel(): void
    {
        $sections = Bootstrap::getObjectManager()->get(ConfigStructureData::class)->get()['sections'];
        $groups = $sections['openfilter']['children'];
        $numbers = [
            'display' => ['expanded_groups', 'price_step'],
            'seo' => ['nofollow_threshold', 'max_active_values'],
        ];
        foreach ($numbers as $group => $fields) {
            foreach ($fields as $field) {
                self::assertSame(
                    NonNegativeNumber::class,
                    ltrim($groups[$group]['children'][$field]['backend_model'] ?? '', '\\'),
                    "$group/$field"
                );
            }
        }
        self::assertArrayHasKey('ajax', $groups['general']['children']);
        self::assertSame(
            PageCacheValue::class,
            ltrim($groups['general']['children']['debug']['backend_model'] ?? '', '\\'),
            'debug markup is part of cached pages'
        );

        $config = Bootstrap::getObjectManager()->get(Config::class);
        self::assertTrue($config->isAjaxEnabled());
        self::assertSame(3, $config->getExpandedGroups());
        self::assertSame(1.0, $config->getPriceStep());
        self::assertSame(2, $config->getNofollowThreshold());
        self::assertSame(5, $config->getMaxActiveValues());
    }

    /**
     * @magentoCache full_page enabled
     */
    public function testSavingAChangedSettingInvalidatesTheFullPageCache(): void
    {
        $typeList = Bootstrap::getObjectManager()->get(TypeListInterface::class);
        $typeList->cleanType('full_page');

        $this->save('seo', ['max_active_values' => ['value' => '7']]);

        self::assertArrayHasKey('full_page', $typeList->getInvalidated());
        Bootstrap::getObjectManager()->get(ReinitableConfigInterface::class)->reinit();
        self::assertSame(7, Bootstrap::getObjectManager()->create(Config::class)->getMaxActiveValues());
    }

    public function testANegativeNumberIsRefusedOnTheServer(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('needs a whole number of 0 or more.');

        $this->save('seo', ['nofollow_threshold' => ['value' => '-1']]);
    }

    /**
     * @param string $group
     * @param array<string, array{value: string}> $fields
     * @return void
     */
    private function save(string $group, array $fields): void
    {
        /** @var ConfigModel $model */
        $model = Bootstrap::getObjectManager()->get(ConfigFactory::class)->create([
            'data' => ['section' => 'openfilter', 'website' => null, 'store' => null, 'groups' => [$group => ['fields' => $fields]]],
        ]);
        $model->save();
    }
}
