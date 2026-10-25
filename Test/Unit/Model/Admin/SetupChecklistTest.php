<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Admin;

use Iranimij\OpenFilter\Model\Admin\FirstRunNotice;
use Iranimij\OpenFilter\Model\Admin\SetupChecklist;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Setup\Patch\Data\DefaultFilterConfig;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\FlagManager;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SetupChecklistTest extends TestCase
{
    public function testAFreshInstallHasEverythingToDoButTheCaches(): void
    {
        $steps = $this->states($this->checklist([DefaultFilterConfig::FLAG_ORDERED => '1'], [], true, true));

        self::assertSame(['review' => false, 'order' => false, 'display' => false, 'seo' => false, 'cache' => true], $steps);
    }

    public function testStepsFollowWhatTheMerchantDid(): void
    {
        $steps = $this->states($this->checklist(
            [FirstRunNotice::FLAG => '1', SetupChecklist::FLAG_SORTED => '1'],
            ['openfilter/display/layout', 'openfilter/general/enabled', 'openfilter/seo/meta_title'],
            false
        ));

        self::assertSame(['review' => true, 'order' => true, 'display' => true, 'seo' => true, 'cache' => false], $steps);
    }

    public function testAnOrderFromBefore12CountsButNotTheInstallOrder(): void
    {
        self::assertTrue($this->states($this->checklist([], [], true, true))['order'], 'a sort position from before 1.2');
        self::assertFalse(
            $this->states($this->checklist([DefaultFilterConfig::FLAG_ORDERED => '1'], [], true, true))['order'],
            'positions written by the install'
        );
    }

    public function testHiding(): void
    {
        $flags = $this->createMock(FlagManager::class);
        $flags->expects(self::once())->method('saveFlag')->with(SetupChecklist::FLAG_HIDDEN, '1');
        $flags->method('getFlagData')->willReturn(null);

        $checklist = $this->checklist([], [], true, false, $flags);
        self::assertFalse($checklist->isHidden());
        $checklist->hide();
    }

    /**
     * @return array<string, bool>
     */
    private function states(SetupChecklist $checklist): array
    {
        return array_column($checklist->steps(), 'done', 'code');
    }

    /**
     * @param array<string, string> $flagData
     * @param string[] $paths saved setting paths
     */
    private function checklist(array $flagData, array $paths, bool $cache, bool $sorted = false, ?FlagManager $flags = null): SetupChecklist
    {
        if ($flags === null) {
            $flags = $this->createStub(FlagManager::class);
            $flags->method('getFlagData')->willReturnCallback(static fn (string $code) => $flagData[$code] ?? null);
        }
        $defaults = new Defaults();
        $color = $defaults->forAttribute('color', 93, 'select', 'visual', 1);
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('getForStore')->willReturn(['color' => $sorted ? $color->setPositionOverride(10) : $color]);
        $select = $this->createStub(Select::class);
        $select->method('distinct')->willReturnSelf();
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn($paths);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $state = $this->createStub(StateInterface::class);
        $state->method('isEnabled')->willReturn($cache);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturn('https://admin/x');

        return new SetupChecklist($flags, $provider, $resource, $state, $url);
    }
}
