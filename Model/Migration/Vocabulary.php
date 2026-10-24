<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Migration;

use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Url\SlugProvider;

/**
 * The words old filter URLs of other modules may use for a store view's option filters (1.2 migration): each
 * filter's attribute code and URL name, each option's URL name, each written with dashes, underscores or nothing
 * between its words, lower case. Built from OpenFilter's cached settings and URL names.
 */
class Vocabulary
{
    /** @var array<int, array{params: array<string, string>, attributes: array<string, string>, options: array<string, array<string, string>>}> */
    private array $loaded = [];

    /**
     * @param FilterConfigProvider $configProvider
     * @param SlugProvider $slugProvider
     * @param Spellings $spellings
     */
    public function __construct(
        private readonly FilterConfigProvider $configProvider,
        private readonly SlugProvider $slugProvider,
        private readonly Spellings $spellings
    ) {
    }

    /**
     * @param int $storeId
     * @return array{params: array<string, string>, attributes: array<string, string>, options: array<string, array<string, string>>}
     *     params: filter key => OpenFilter URL name; attributes: written name => filter key; options: written name =>
     *     filter key => OpenFilter option URL name
     */
    public function forStore(int $storeId): array
    {
        if (isset($this->loaded[$storeId])) {
            return $this->loaded[$storeId];
        }
        $slugs = $this->slugProvider->forStore($storeId);
        $vocabulary = ['params' => [], 'attributes' => [], 'options' => []];
        foreach ($this->configProvider->getActive($storeId) as $key => $config) {
            $attributeId = (int) $config->getAttributeId();
            if ($config->isBuiltin() || $attributeId === 0 || in_array($config->getInputType(), ['decimal', 'boolean', 'price'], true)) {
                continue;
            }
            $param = $slugs['attributes'][$attributeId] ?? $config->getCode();
            $vocabulary['params'][(string) $key] = $param;
            foreach ([$config->getCode(), $param] as $name) {
                foreach ($this->spellings->of($name) as $spelling) {
                    $vocabulary['attributes'][$spelling] ??= (string) $key;
                }
            }
            foreach ($slugs['options'][$attributeId] ?? [] as $slug) {
                foreach ($this->spellings->of($slug) as $spelling) {
                    $vocabulary['options'][$spelling][(string) $key] ??= $slug;
                }
            }
        }

        return $this->loaded[$storeId] = $vocabulary;
    }
}
