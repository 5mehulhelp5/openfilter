<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Search;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Counts the search requests the engine served (`_nodes/stats` query_total; one per request on one-shard indices).
 */
class EngineRequests
{
    /**
     * @param callable $callback
     * @return int requests the engine served while the callback ran
     */
    public static function during(callable $callback): int
    {
        $before = self::total();
        $callback();

        return self::total() - $before;
    }

    /**
     * @return int
     */
    private static function total(): int
    {
        $om = Bootstrap::getObjectManager();
        $config = $om->get(ScopeConfigInterface::class);
        $engine = (string) $config->getValue('catalog/search/engine');
        $host = (string) $config->getValue("catalog/search/{$engine}_server_hostname");
        $port = (string) $config->getValue("catalog/search/{$engine}_server_port");
        $curl = $om->create(Curl::class);
        $curl->get(sprintf('http://%s:%s/_nodes/stats/indices/search', $host, $port));
        $stats = json_decode($curl->getBody(), true);
        $total = 0;
        foreach ($stats['nodes'] ?? [] as $node) {
            $total += (int) $node['indices']['search']['query_total'];
        }

        return $total;
    }
}
