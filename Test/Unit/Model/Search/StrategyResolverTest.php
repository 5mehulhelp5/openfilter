<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenFilter\Api\FacetCountStrategyInterface;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StrategyResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function choices(): array
    {
        return [
            'configured single' => ['single_query', 'opensearch', 'single_query'],
            'configured per attribute' => ['per_attribute', 'opensearch', 'per_attribute'],
            'auto on a proven engine' => ['auto', 'opensearch', 'single_query'],
            'auto on an unknown engine falls back' => ['auto', 'something_else', 'per_attribute'],
        ];
    }

    /**
     * @dataProvider choices
     */
    #[DataProvider('choices')]
    public function testResolve(string $configured, string $engine, string $expected): void
    {
        $reader = $this->createStub(TypedReader::class);
        $reader->method('getString')->willReturnMap([
            [StrategyResolver::XML_PATH_STRATEGY, 'default', null, $configured],
            ['catalog/search/engine', 'default', null, $engine],
        ]);
        $config = $this->createStub(Config::class);
        $config->method('isDebug')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::stringContains($expected));
        $resolver = new StrategyResolver($reader, $config, $logger, [
            'single_query' => $this->strategy('single_query'),
            'per_attribute' => $this->strategy('per_attribute'),
        ], ['opensearch' => 'single_query']);

        self::assertSame($expected, $resolver->resolve()->getCode());
        self::assertSame($expected, $resolver->resolve()->getCode(), 'logged once only');
    }

    private function strategy(string $code): FacetCountStrategyInterface
    {
        $strategy = $this->createStub(FacetCountStrategyInterface::class);
        $strategy->method('getCode')->willReturn($code);

        return $strategy;
    }
}
