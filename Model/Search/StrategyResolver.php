<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenFilter\Api\FacetCountStrategyInterface;
use Iranimij\OpenFilter\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Picks the facet count strategy: the configured one, or for "auto" the one proven for the active search engine
 * (docs/facet-counts.md). Logs its choice once per process in debug mode.
 */
class StrategyResolver
{
    public const XML_PATH_STRATEGY = 'openfilter/search/count_strategy';
    public const AUTO = 'auto';
    public const SINGLE_QUERY = 'single_query';
    public const PER_ATTRIBUTE = 'per_attribute';

    /** @var bool */
    private bool $logged = false;

    /**
     * @param TypedReader $reader
     * @param Config $config
     * @param LoggerInterface $logger
     * @param array<string, FacetCountStrategyInterface> $strategies by code
     * @param array<string, string> $autoByEngine strategy code by search engine for "auto"
     */
    public function __construct(
        private readonly TypedReader $reader,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly array $strategies = [],
        private readonly array $autoByEngine = []
    ) {
    }

    /**
     * @return FacetCountStrategyInterface
     */
    public function resolve(): FacetCountStrategyInterface
    {
        $code = $this->reader->getString(self::XML_PATH_STRATEGY, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null);
        $engine = $this->reader->getString('catalog/search/engine', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null);
        if (!isset($this->strategies[$code])) {
            $code = $this->autoByEngine[$engine] ?? self::PER_ATTRIBUTE;
        }
        if (!$this->logged && $this->config->isDebug()) {
            $this->logger->info(sprintf('OpenFilter facet count strategy: %s (engine %s)', $code, $engine));
            $this->logged = true;
        }

        return $this->strategies[$code] ?? $this->strategies[self::PER_ATTRIBUTE];
    }
}
