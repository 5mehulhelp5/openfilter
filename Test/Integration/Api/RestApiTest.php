<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Api;

use Iranimij\OpenFilter\Api\ConfigTransferInterface;
use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigManagementInterface;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Framework\Webapi\ServiceOutputProcessor;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Webapi\Model\Config;
use PHPUnit\Framework\TestCase;

/**
 * M5 S9 REST: the routes and their ACL resource, a request's fields reaching the services through Magento's input
 * processor (only the sent settings change; read-only fields are refused), the output a client reads, and the config
 * document's dry run versus apply. HTTP and the 401 for anonymous calls are covered by Playwright (m5-rest).
 *
 * @magentoAppArea webapi_rest
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class RestApiTest extends TestCase
{
    private const ROUTES = [
        '/V1/openfilter/filters' => ['GET' => 'getList'],
        '/V1/openfilter/filters/:code' => ['GET' => 'getByCode', 'PUT' => 'update'],
        '/V1/openfilter/config' => ['GET' => 'export', 'POST' => 'import'],
    ];

    /** @var array{sort_by: string, visible_options: int}|null */
    private ?array $before = null;

    public static function setUpBeforeClass(): void
    {
        M1Catalog::create();
    }

    protected function setUp(): void
    {
        $color = $this->repository()->getByCode(M1Catalog::COLOR);
        $this->before = ['sort_by' => $color->getSortBy(), 'visible_options' => $color->getVisibleOptions()];
    }

    protected function tearDown(): void
    {
        $color = $this->repository()->getByCode(M1Catalog::COLOR);
        $this->repository()->save($color->setSortBy($this->before['sort_by'])
            ->setVisibleOptions($this->before['visible_options'])->setCategoryMode(Filter::CATEGORIES_ALL)->setCategoryIds([]));
        $this->fresh();
    }

    public function testEveryRouteNeedsTheFiltersResource(): void
    {
        $routes = Bootstrap::getObjectManager()->get(Config::class)->getServices()['routes'];

        foreach (self::ROUTES as $url => $methods) {
            foreach ($methods as $method => $serviceMethod) {
                self::assertSame($serviceMethod, $routes[$url][$method]['service']['method'] ?? null, "$method $url");
                self::assertSame(['Iranimij_OpenFilter::filters'], array_keys($routes[$url][$method]['resources']), "$method $url");
            }
        }
    }

    public function testOnlyTheSentSettingsChange(): void
    {
        $before = $this->repository()->getByCode(M1Catalog::COLOR);
        $args = Bootstrap::getObjectManager()->get(ServiceInputProcessor::class)->process(
            FilterConfigManagementInterface::class,
            'update',
            ['code' => M1Catalog::COLOR, 'config' => ['sort_by' => 'name', 'category_mode' => 'except', 'category_ids' => ['7', 3]]]
        );

        $saved = Bootstrap::getObjectManager()->get(FilterConfigManagementInterface::class)->update(...$args);
        $this->fresh();
        $stored = $this->repository()->getByCode(M1Catalog::COLOR);

        foreach ([$saved, $stored] as $config) {
            self::assertSame(['name', 'except', [3, 7]], [$config->getSortBy(), $config->getCategoryMode(), $config->getCategoryIds()]);
            self::assertSame($before->getDisplayMode(), $config->getDisplayMode());
            self::assertSame($before->getVisibleOptions(), $config->getVisibleOptions());
            self::assertSame($before->isSeoIndexable(), $config->isSeoIndexable());
        }
    }

    public function testReadOnlyFieldsAreRefused(): void
    {
        $this->expectException(InputException::class);

        Bootstrap::getObjectManager()->get(ServiceInputProcessor::class)->process(
            FilterConfigManagementInterface::class,
            'update',
            ['code' => M1Catalog::COLOR, 'config' => ['code' => 'size', 'sort_by' => 'name']]
        );
    }

    public function testAClientReadsTheSettingsAndTheWhereItShowsRule(): void
    {
        $output = Bootstrap::getObjectManager()->get(ServiceOutputProcessor::class)->process(
            $this->repository()->getByCode(M1Catalog::COLOR),
            FilterConfigRepositoryInterface::class,
            'getByCode'
        );

        self::assertSame(M1Catalog::COLOR, $output['code']);
        self::assertFalse($output['builtin']);
        foreach (['display_mode', 'multiselect', 'logic', 'sort_by', 'seo_indexable', 'active', 'position', 'label', 'category_mode', 'category_ids'] as $field) {
            self::assertArrayHasKey($field, $output);
        }
    }

    public function testTheConfigDocumentDryRunChangesNothingAndApplySavesIt(): void
    {
        $transfer = Bootstrap::getObjectManager()->get(ConfigTransferInterface::class);
        $document = json_decode($transfer->export(), true);
        $document['filters'][M1Catalog::COLOR]['settings']['visible_options'] = 11;
        $document['filters'][M1Catalog::COLOR]['settings']['category_mode'] = 'only';
        $document['filters'][M1Catalog::COLOR]['settings']['category_ids'] = [2];

        $dryRun = $transfer->import((string) json_encode($document), true);
        $this->fresh();
        self::assertFalse($dryRun->isApplied());
        self::assertSame([], $dryRun->getErrors());
        self::assertContains(M1Catalog::COLOR . '.settings.visible_options: ' . $this->before['visible_options'] . ' → 11', $dryRun->getChanges());
        self::assertContains(M1Catalog::COLOR . '.settings.category_ids: [] → [2]', $dryRun->getChanges());
        self::assertSame($this->before['visible_options'], $this->repository()->getByCode(M1Catalog::COLOR)->getVisibleOptions());

        $applied = $transfer->import((string) json_encode($document), false);
        $this->fresh();
        self::assertTrue($applied->isApplied());
        $color = $this->repository()->getByCode(M1Catalog::COLOR);
        self::assertSame([11, 'only', [2]], [$color->getVisibleOptions(), $color->getCategoryMode(), $color->getCategoryIds()]);
        self::assertSame([], $transfer->import((string) json_encode($document), true)->getChanges(), 'the export round-trips');
    }

    private function repository(): FilterConfigRepositoryInterface
    {
        return Bootstrap::getObjectManager()->get(FilterConfigRepositoryInterface::class);
    }

    private function fresh(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();
    }
}
