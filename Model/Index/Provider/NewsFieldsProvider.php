<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Index\Provider;

use Iranimij\OpenFilter\Model\Index\BuiltinDataSource;
use Iranimij\OpenFilter\Model\Index\Fields;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;

/**
 * of_news_from / of_news_to: the "Set Product as New" window, open ends filled, compared to "now" at query time
 * (no daily reindex needed).
 */
class NewsFieldsProvider implements AdditionalFieldsProviderInterface
{
    /**
     * @param BuiltinDataSource $source
     */
    public function __construct(
        private readonly BuiltinDataSource $source
    ) {
    }

    /**
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, array<string, string>>
     */
    public function getFields(array $productIds, $storeId)
    {
        $fields = [];
        foreach ($this->source->newsDates($productIds, (int) $storeId) as $productId => $dates) {
            if (($dates['from'] ?? null) === null && ($dates['to'] ?? null) === null) {
                continue;
            }
            $fields[$productId] = [
                Fields::NEWS_FROM => $this->normalise($dates['from'], Fields::OPEN_START),
                Fields::NEWS_TO => $this->normalise($dates['to'], Fields::OPEN_END),
            ];
        }

        return $fields;
    }

    /**
     * @param string|null $date
     * @param string $default
     * @return string
     */
    private function normalise(?string $date, string $default): string
    {
        if ($date === null || $date === '') {
            return $default;
        }
        $time = strtotime($date);

        return $time === false ? $default : date(Fields::DATE_FORMAT, $time);
    }
}
