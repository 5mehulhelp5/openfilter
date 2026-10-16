<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Api\FilterStateInterface;
use Magento\Framework\Phrase;

/**
 * Dependent filters (1.2): a filter that waits for another one shows only after a pick there (any value, or one of
 * the chosen values). Its own picks leave with the parent's: URLs and the page state never keep a pick of a filter
 * that does not show.
 */
class DependencyRule
{
    /**
     * Whether the filter shows with this state.
     *
     * @param FilterConfigInterface $config
     * @param FilterStateInterface $state
     * @return bool
     */
    public function allows(FilterConfigInterface $config, FilterStateInterface $state): bool
    {
        $parent = $config->getDependsOn();
        if ($parent === null) {
            return true;
        }
        $picked = $state->get($parent);
        $wanted = $config->getDependsValues();

        return $picked !== [] && ($wanted === [] || array_intersect($picked, $wanted) !== []);
    }

    /**
     * The state without the picks of filters that do not show (a chain loses every level below a missing pick).
     *
     * @param FilterStateInterface $state
     * @param array<string, FilterConfigInterface> $configs the store view's filters by key
     * @return FilterStateInterface
     */
    public function prune(FilterStateInterface $state, array $configs): FilterStateInterface
    {
        do {
            $before = $state;
            foreach (array_keys($state->getValues()) as $key) {
                $config = $configs[$key] ?? null;
                if ($config !== null && !$this->allows($config, $state)) {
                    $state = $state->without($key);
                }
            }
        } while ($state !== $before);

        return $state;
    }

    /**
     * Why a filter cannot wait for $parent: no such filter, or filters that would wait for each other.
     *
     * @param string $key the filter's key
     * @param string|null $parent the filter it would wait for
     * @param array<string, FilterConfigInterface> $configs every filter by key (saved settings)
     * @return Phrase|null null = fine
     */
    public function problem(string $key, ?string $parent, array $configs): ?Phrase
    {
        $seen = [$key => true];
        $next = $parent;
        while ($next !== null) {
            if (!isset($configs[$next])) {
                return __('There is no filter "%1" to wait for.', $next);
            }
            if (isset($seen[$next])) {
                return __('"%1" and "%2" would wait for each other, so neither would ever show.', $key, (string) $parent);
            }
            $seen[$next] = true;
            $next = $configs[$next]->getDependsOn();
        }

        return null;
    }
}
