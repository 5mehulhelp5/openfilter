<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Controller;

use Iranimij\OpenFilter\Controller\LandingRouter;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LandingRouterTest extends TestCase
{
    public function testALandingUrlKeyGoesToItsPage(): void
    {
        $request = $this->request('/black-tops/');
        $request->expects(self::once())->method('setAlias')->with(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, 'black-tops');
        $request->expects(self::once())->method('setModuleName')->with('openfilter')->willReturnSelf();
        $request->expects(self::once())->method('setControllerName')->with('landing')->willReturnSelf();
        $request->expects(self::once())->method('setActionName')->with('view')->willReturnSelf();
        $request->expects(self::once())->method('setParam')->with('page_id', '4');

        self::assertInstanceOf(Forward::class, $this->router(true)->match($request));
    }

    public function testOtherPathsOpenFilterOffAndMatchedRequestsAreLeftAlone(): void
    {
        self::assertNull($this->router(true)->match($this->request('/women.html')));
        self::assertNull($this->router(false)->match($this->request('/black-tops')), 'OpenFilter is off');
        self::assertNull($this->router(true)->match($this->request('/')));
        self::assertNull($this->router(true)->match($this->request('/black-tops', 'cms')), 'another router matched');
    }

    private function router(bool $on): LandingRouter
    {
        $pages = $this->createStub(LandingPages::class);
        $pages->method('byUrlKey')->willReturnCallback(
            static fn (string $key): ?LandingPage => $key === 'black-tops' ? new LandingPage(['page_id' => 4]) : null
        );
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($on);
        $factory = $this->createStub(ActionFactory::class);
        $factory->method('create')->willReturn($this->createStub(Forward::class));
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return new LandingRouter($factory, $pages, $config, $stores);
    }

    /**
     * @return HttpRequest&\PHPUnit\Framework\MockObject\MockObject
     */
    private function request(string $path, string $module = ''): HttpRequest
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getPathInfo')->willReturn($path);
        $request->method('getModuleName')->willReturn($module);
        $request->method('setModuleName')->willReturnSelf();
        $request->method('setControllerName')->willReturnSelf();
        $request->method('setActionName')->willReturnSelf();

        return $request;
    }
}
