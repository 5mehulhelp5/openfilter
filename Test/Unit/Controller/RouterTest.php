<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Controller;

use Iranimij\OpenFilter\Controller\Router;
use Iranimij\OpenFilter\Model\AllProducts;
use Iranimij\OpenFilter\Model\Config;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class RouterTest extends TestCase
{
    public function testTheUrlKeyGoesToThePageWithTheRewriteAlias(): void
    {
        $request = $this->request('/all-products/');
        $request->expects(self::once())->method('setAlias')->with(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, 'all-products');
        $request->expects(self::once())->method('setModuleName')->with('openfilter')->willReturnSelf();
        $request->expects(self::once())->method('setControllerName')->with('all')->willReturnSelf();
        $request->expects(self::once())->method('setActionName')->with('index')->willReturnSelf();

        self::assertInstanceOf(Forward::class, $this->router(true)->match($request));
    }

    public function testOtherPathsOffPagesAndMatchedRequestsAreLeftAlone(): void
    {
        self::assertNull($this->router(true)->match($this->request('/women/tops-women.html')));
        self::assertNull($this->router(false)->match($this->request('/all-products')), 'the page is off');
        self::assertNull($this->router(true)->match($this->request('/')));
        self::assertNull($this->router(true)->match($this->request('/all-products', 'cms')), 'another router matched');
    }

    private function router(bool $on): Router
    {
        $page = $this->createStub(AllProducts::class);
        $page->method('isEnabled')->willReturn($on);
        $page->method('urlKey')->willReturn('all-products');
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $factory = $this->createStub(ActionFactory::class);
        $factory->method('create')->willReturn($this->createStub(Forward::class));

        return new Router($factory, $page, $config);
    }

    /**
     * @return HttpRequest&\PHPUnit\Framework\MockObject\MockObject
     */
    private function request(string $path, string $module = ''): HttpRequest
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getPathInfo')->willReturn($path);
        $request->method('getModuleName')->willReturn($module);

        return $request;
    }
}
