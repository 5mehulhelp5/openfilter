<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Landing;

use Iranimij\Base\Serializer\SafeJson;
use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\InputException;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LandingPagesTest extends TestCase
{
    private const ROWS = [
        ['page_id' => 2, 'is_active' => 1, 'category_id' => 21, 'filters' => '{"size":["m"],"color":["49","50"]}',
            'url_key' => 'black-tops', 'title' => 'Black tops'],
        ['page_id' => 1, 'is_active' => 0, 'category_id' => 21, 'filters' => '{"color":["52"]}', 'url_key' => 'red-tops',
            'title' => 'Red tops'],
    ];

    private const STORES = [['page_id' => 2, 'store_id' => 1], ['page_id' => 1, 'store_id' => 0]];

    public function testTheStorefrontFindsActivePagesByUrlKeyAndByState(): void
    {
        $pages = $this->pages();

        self::assertSame(2, $pages->byUrlKey('/Black-Tops/', 1)?->getId());
        self::assertNull($pages->byUrlKey('black-tops', 2), 'not in that store view');
        self::assertNull($pages->byUrlKey('red-tops', 1), 'disabled');
        self::assertSame(2, $pages->forState(21, ['color' => ['50', '49'], 'size' => ['m']], 1)?->getId(), 'order does not matter');
        self::assertNull($pages->forState(21, ['color' => ['49']], 1), 'a different state');
        self::assertNull($pages->forState(20, ['color' => ['49', '50'], 'size' => ['m']], 1), 'another category');
        self::assertNull($pages->forState(21, [], 1), 'no filters');
        self::assertCount(1, $pages->forStore(1));
        self::assertSame([], $pages->forStore(2));
    }

    public function testActivePagesComeFromTheCacheOnceCached(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('load')->willReturn((new SafeJson())->encode([
            (new LandingPage(['page_id' => 5, 'url_key' => 'cached', 'title' => 'Cached', 'store_ids' => [0]]))->toArray(),
        ]));
        $cache->expects(self::never())->method('save');
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');

        $pages = new LandingPages($resource, $cache, new SafeJson(), $this->createStub(TypeListInterface::class), $this->createStub(UrlFinderInterface::class));

        self::assertSame(5, $pages->byUrlKey('cached', 3)?->getId());
    }

    public function testStateKeysIgnoreOrder(): void
    {
        $pages = $this->pages();

        self::assertSame(
            $pages->stateKey(['size' => ['m'], 'color' => ['50', '49']]),
            $pages->stateKey(['color' => ['49', 50], 'size' => ['m']])
        );
        self::assertNotSame($pages->stateKey(['color' => ['49']]), $pages->stateKey(['size' => ['49']]));
    }

    public function testSavingRefusesEveryProblemAtOnce(): void
    {
        try {
            $this->pages()->save(new LandingPage(['url_key' => 'Black tops!', 'store_ids' => [1]]));
            self::fail('saved');
        } catch (InputException $e) {
            self::assertStringContainsString('Enter a title.', $e->getMessage());
            self::assertStringContainsString('may use lower-case letters', $e->getMessage());
            self::assertStringContainsString('Paste the address', $e->getMessage());
        }
    }

    public function testSavingRefusesATakenUrlKey(): void
    {
        $page = ['title' => 'T', 'category_id' => 21, 'filters' => ['color' => ['49']]];
        try {
            $this->pages()->save(new LandingPage($page + ['url_key' => 'black-tops', 'store_ids' => [0]]));
            self::fail('saved');
        } catch (InputException $e) {
            self::assertStringContainsString('"Black tops" already uses the URL key', $e->getMessage(), 'all store views overlap store 1');
        }
        $rewrite = $this->createStub(UrlRewrite::class);
        $rewrite->method('getEntityType')->willReturn('category');
        try {
            $this->pages($rewrite)->save(new LandingPage($page + ['url_key' => 'women.html']));
            self::fail('saved');
        } catch (InputException $e) {
            self::assertStringContainsString('Another page (category) already has the address "women.html"', $e->getMessage());
        }
    }

    public function testSavingWritesThePageItsStoreViewsAndCleansTheCaches(): void
    {
        // getAll() while checking (pages, store views), then the saved page's store views.
        $connection = $this->connection(self::ROWS, self::STORES, [['page_id' => 9, 'store_id' => 1], ['page_id' => 9, 'store_id' => 2]]);
        $connection->expects(self::once())->method('insert')->with('openfilter_landing_page', self::callback(
            static fn (array $row): bool => $row['filters'] === '{"color":["49"]}' && $row['is_active'] === 1 && !isset($row['store_ids'])
        ));
        $connection->method('lastInsertId')->willReturn('9');
        $connection->expects(self::once())->method('insertMultiple')->with('openfilter_landing_page_store', [
            ['page_id' => 9, 'store_id' => 1],
            ['page_id' => 9, 'store_id' => 2],
        ]);
        $connection->method('fetchRow')->willReturn(['page_id' => 9, 'url_key' => 'new', 'title' => 'New']);
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())->method('remove')->with('openfilter_landing_pages');
        $types = $this->createMock(TypeListInterface::class);
        $types->expects(self::once())->method('invalidate')->with('full_page');

        $saved = $this->pages(null, $connection, $cache, $types)->save(new LandingPage([
            'title' => 'New', 'url_key' => 'new', 'category_id' => 21, 'filters' => ['color' => ['49']], 'store_ids' => [2, 1],
        ]));

        self::assertSame(9, $saved->getId());
        self::assertSame([1, 2], $saved->getStoreIds());
    }

    public function testDeletingNothingTouchesNothing(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::never())->method('remove');
        $this->pages(null, null, $cache)->delete([]);
    }

    /**
     * @param array<int, array<string, mixed>> ...$fetchAll the rows of each fetchAll, in order
     * @return Mysql&\PHPUnit\Framework\MockObject\MockObject
     */
    private function connection(array ...$fetchAll): Mysql
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $connection = $this->createMock(Mysql::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(...($fetchAll ?: [self::ROWS, self::STORES, self::ROWS, self::STORES]));

        return $connection;
    }

    private function pages(
        ?UrlRewrite $taken = null,
        ?AdapterInterface $connection = null,
        ?FrontendInterface $cache = null,
        ?TypeListInterface $types = null
    ): LandingPages {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection ?? $this->connection());
        $resource->method('getTableName')->willReturnArgument(0);
        $finder = $this->createStub(UrlFinderInterface::class);
        $finder->method('findOneByData')->willReturn($taken);

        return new LandingPages(
            $resource,
            $cache ?? $this->createStub(FrontendInterface::class),
            new SafeJson(),
            $types ?? $this->createStub(TypeListInterface::class),
            $finder
        );
    }
}
