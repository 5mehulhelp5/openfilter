<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Coexistence;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenFilter\Model\Coexistence\AdminNotice;
use Iranimij\OpenFilter\Model\Coexistence\Detector;
use Iranimij\OpenFilter\Model\Config;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Notification\MessageInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CoexistenceTest extends TestCase
{
    public function testElasticSuiteSwitchesOpenFilterOffWithANotice(): void
    {
        $detector = $this->detector(['Smile_ElasticsuiteCatalog']);
        $reader = $this->createStub(TypedReader::class);
        $reader->method('getBool')->willReturn(true);
        $notice = new AdminNotice($detector);

        self::assertSame('ElasticSuite', $detector->getActiveReplacement());
        self::assertFalse((new Config($reader, $detector))->isEnabled());
        self::assertTrue($notice->isDisplayed());
        self::assertStringContainsString('ElasticSuite provides the layered navigation', (string) $notice->getText());
        self::assertSame('openfilter_coexistence', $notice->getIdentity());
        self::assertSame(MessageInterface::SEVERITY_NOTICE, $notice->getSeverity());
    }

    public function testDebugModeLogsWhyOpenFilterStepsAsideOnce(): void
    {
        $reader = $this->createStub(TypedReader::class);
        $reader->method('getBool')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')
            ->with('OpenFilter steps aside: ElasticSuite provides the layered navigation.');
        $config = new Config($reader, $this->detector(['Smile_ElasticsuiteCatalog']), $logger);

        self::assertFalse($config->isEnabled());
        self::assertFalse($config->isEnabled());
    }

    public function testLiveSearchToo(): void
    {
        self::assertSame('Adobe Live Search', $this->detector(['Magento_LiveSearchProductListing'])->getActiveReplacement());
    }

    public function testStockSearchKeepsOpenFilterOn(): void
    {
        $detector = $this->detector([]);
        $reader = $this->createStub(TypedReader::class);
        $reader->method('getBool')->willReturn(true);

        self::assertNull($detector->getActiveReplacement());
        self::assertTrue((new Config($reader, $detector))->isEnabled());
        self::assertTrue((new Config($reader))->isEnabled(), 'no detector: configuration only');
        self::assertFalse((new AdminNotice($detector))->isDisplayed());
    }

    /**
     * @param string[] $enabled
     */
    private function detector(array $enabled): Detector
    {
        $manager = $this->createMock(ModuleManager::class);
        $manager->expects(self::atLeastOnce())->method('isEnabled')
            ->willReturnCallback(static fn (string $module): bool => in_array($module, $enabled, true));

        return new Detector($manager);
    }
}
