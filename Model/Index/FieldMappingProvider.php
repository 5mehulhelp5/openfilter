<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Index;

use Iranimij\OpenFilter\Model\ResourceModel\BuiltinDataSource;
use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\FieldProviderInterface;

/**
 * Index mapping of OpenFilter's fields (OpenSearch and Elasticsearch 8 share the provider pool).
 */
class FieldMappingProvider implements FieldProviderInterface
{
    /**
     * @param BuiltinDataSource $source
     * @param Fields $fields
     */
    public function __construct(
        private readonly BuiltinDataSource $source,
        private readonly Fields $fields
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, array<string, string>>
     */
    public function getFields(array $context = []): array
    {
        $mapping = [
            Fields::RATING => ['type' => 'integer'],
            Fields::IN_STOCK => ['type' => 'integer'],
            Fields::NEWS_FROM => ['type' => 'date', 'format' => Fields::ENGINE_DATE_FORMAT],
            Fields::NEWS_TO => ['type' => 'date', 'format' => Fields::ENGINE_DATE_FORMAT],
        ];
        $websites = isset($context['websiteId']) ? [(int) $context['websiteId']] : $this->source->websiteIds();
        foreach ($this->source->groupIds() as $groupId) {
            foreach ($websites as $websiteId) {
                $mapping[$this->fields->onSale($groupId, $websiteId)] = ['type' => 'integer'];
            }
        }

        return $mapping;
    }
}
