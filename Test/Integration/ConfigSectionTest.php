<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration;

use Iranimij\OpenFilter\Model\Config;
use Magento\Config\Model\Config\Structure\Data as ConfigStructureData;
use Magento\Framework\Acl\AclResource\ProviderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 */
class ConfigSectionTest extends TestCase
{
    public function testSectionLivesUnderTheIranimijTabOfTheSharedBase(): void
    {
        $data = Bootstrap::getObjectManager()->get(ConfigStructureData::class)->get();

        self::assertArrayHasKey('iranimij', $data['tabs']);
        self::assertSame('iranimij', $data['sections']['openfilter']['tab']);
        self::assertSame('Iranimij_OpenFilter::config', $data['sections']['openfilter']['resource']);
        self::assertArrayHasKey('enabled', $data['sections']['openfilter']['children']['general']['children']);
        self::assertArrayHasKey('debug', $data['sections']['openfilter']['children']['general']['children']);
    }

    public function testEnabledByDefaultDebugOffByDefault(): void
    {
        $config = Bootstrap::getObjectManager()->get(Config::class);

        self::assertTrue($config->isEnabled());
        self::assertFalse($config->isDebug());
    }

    public function testThe11SettingsDefaultTo10Behaviour(): void
    {
        $config = Bootstrap::getObjectManager()->get(Config::class);

        self::assertSame(Config::LAYOUT_SIDEBAR, $config->getLayout());
        self::assertSame(Config::APPLY_INSTANT, $config->getApplyMode());
        self::assertFalse($config->isSticky());
        self::assertSame('', $config->getMetaTitleTemplate());
        self::assertSame('', $config->getMetaDescriptionTemplate());
        self::assertSame('', $config->getH1Template());
        self::assertFalse($config->isSitemapEnabled());
        self::assertSame(1, $config->getSitemapMinProducts());
        self::assertSame(5000, $config->getSitemapMaxUrls());
    }

    public function testAclResourcesAreDeclaredUnderCatalogAndBase(): void
    {
        $tree = Bootstrap::getObjectManager()->get(ProviderInterface::class)->getAclResources();
        $ids = [];
        $walk = static function (array $nodes, string $parent) use (&$walk, &$ids): void {
            foreach ($nodes as $node) {
                $ids[$node['id']] = $parent;
                $walk($node['children'] ?? [], $node['id']);
            }
        };
        $walk($tree, '');

        self::assertSame('Iranimij_Base::config', $ids['Iranimij_OpenFilter::config'] ?? null);
        self::assertSame('Magento_Catalog::catalog', $ids['Iranimij_OpenFilter::openfilter'] ?? null);
        self::assertSame('Iranimij_OpenFilter::openfilter', $ids['Iranimij_OpenFilter::filters'] ?? null);
    }
}
