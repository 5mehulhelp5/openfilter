<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\GraphQl;

use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Framework\App\Request\Http;
use Magento\GraphQl\Controller\GraphQl;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * M6 S7 `openfilter_landing_page(url_key)`: the page's head, text and filters for headless storefronts; null for an
 * unknown URL key. One GraphQL dispatch per test.
 *
 * @magentoAppArea graphql
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class LandingPageQueryTest extends TestCase
{
    private const KEY = 'of-m1-graphql-red';

    private const QUERY = 'query ($key: String!) { openfilter_landing_page(url_key: $key) {
        url_key url title heading meta_title meta_description description robots category_uid filters { code values }
    } }';

    /** @var array{category: int, store: int, options: array<string, array<string, int>>}|null */
    private static ?array $fixture = null;

    private static int $pageId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$fixture = M1Catalog::create();
        self::$pageId = (int) Bootstrap::getObjectManager()->get(LandingPages::class)->save(new LandingPage([
            'url_key' => self::KEY, 'title' => 'Red things', 'description' => '<p>Red</p>',
            'category_id' => self::$fixture['category'],
            'filters' => [M1Catalog::COLOR => [(string) self::$fixture['options'][M1Catalog::COLOR]['Red']]],
        ]))->getId();
    }

    public static function tearDownAfterClass(): void
    {
        Bootstrap::getObjectManager()->get(LandingPages::class)->delete([self::$pageId]);
        parent::tearDownAfterClass();
    }

    public function testThePageForHeadlessStorefronts(): void
    {
        $page = $this->graphQl(self::KEY)['data']['openfilter_landing_page'];

        self::assertSame(self::KEY, $page['url_key']);
        self::assertStringEndsWith('/' . self::KEY, $page['url']);
        self::assertSame('Red things', $page['heading']);
        self::assertSame('Red things', $page['meta_title']);
        self::assertNull($page['meta_description']);
        self::assertSame('<p>Red</p>', $page['description']);
        self::assertSame('INDEX,FOLLOW', $page['robots']);
        self::assertSame(base64_encode((string) self::$fixture['category']), $page['category_uid']);
        self::assertSame(
            [['code' => M1Catalog::COLOR, 'values' => [(string) self::$fixture['options'][M1Catalog::COLOR]['Red']]]],
            $page['filters']
        );
    }

    public function testAnUnknownUrlKeyIsNull(): void
    {
        self::assertNull($this->graphQl('of-m1-nothing')['data']['openfilter_landing_page']);
    }

    /**
     * @param string $key
     * @return array<string, mixed>
     */
    private function graphQl(string $key): array
    {
        $om = Bootstrap::getObjectManager();
        /** @var Http $request */
        $request = $om->get(Http::class);
        $request->setPathInfo('/graphql')->setMethod('POST');
        $request->setContent((string) json_encode(['query' => self::QUERY, 'variables' => ['key' => $key]]));
        $request->setHeaders($om->create(\Laminas\Http\Headers::class)->addHeaders(['Content-Type' => 'application/json', 'Store' => 'default']));
        $response = json_decode((string) $om->get(GraphQl::class)->dispatch($request)->getContent(), true);
        self::assertIsArray($response);
        self::assertArrayNotHasKey('errors', $response, (string) json_encode($response['errors'] ?? null));

        return $response;
    }
}
