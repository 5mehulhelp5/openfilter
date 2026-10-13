<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Plugin\Search;

use Iranimij\OpenFilter\Model\Search\QueryState;
use Iranimij\OpenFilter\Plugin\Search\DynamicBucketPlugin;
use Magento\Elasticsearch\SearchAdapter\Aggregation\Builder\Dynamic;
use Magento\Framework\Search\Dynamic\DataProviderInterface;
use Magento\Framework\Search\Request\Aggregation\DynamicBucket;
use PHPUnit\Framework\TestCase;

class DynamicBucketPluginTest extends TestCase
{
    public function testLayeredResponsesUseTheInRequestStats(): void
    {
        $state = new QueryState();
        $plugin = new DynamicBucketPlugin($state);
        $bucket = new DynamicBucket('price_bucket', 'price', 'auto');
        $result = ['aggregations' => ['price_bucket' => ['count' => 7, 'min' => 8.0, 'max' => 56.0, 'avg' => 30.1]]];
        $core = static fn (): array => ['10_20' => ['value' => '10_20', 'count' => 3]];
        $subject = $this->createStub(Dynamic::class);
        $provider = $this->createStub(DataProviderInterface::class);

        self::assertSame(['10_20' => ['value' => '10_20', 'count' => 3]], $plugin->aroundBuild($subject, $core, $bucket, [], $result, $provider));

        $state->setHandling(true);
        self::assertTrue($state->isHandling());
        self::assertSame(
            ['stats' => ['value' => 'stats', 'min' => 8.0, 'max' => 56.0, 'count' => 7]],
            $plugin->aroundBuild($subject, $core, $bucket, [], $result, $provider)
        );
        self::assertSame([], $plugin->aroundBuild($subject, $core, $bucket, [], ['aggregations' => []], $provider));
        $empty = ['aggregations' => ['price_bucket' => ['count' => 0, 'min' => null, 'max' => null]]];
        self::assertSame([], $plugin->aroundBuild($subject, $core, $bucket, [], $empty, $provider));
    }
}
