<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Setup\Patch\Data;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Controller\Adminhtml\Filter\SortSave;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Magento\Framework\FlagManager;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Stores the starting settings of every filterable attribute and built-in filter, so later changes to the defaults
 * never change a running shop. Rows that exist are left alone.
 *
 * Since 1.2 a new install also gets a useful order (Review #1): category, price, then the filters with swatches,
 * then the others, each group in Magento's attribute order, written as the filters' sort positions (as *Sort filters*
 * saves them). A patch runs once, so shops that installed an earlier version keep their order.
 */
class DefaultFilterConfig implements DataPatchInterface
{
    /** Set when the order above was written, so "Set up OpenFilter" does not count it as the merchant's own. */
    public const FLAG_ORDERED = 'openfilter_default_order';

    /** Positions of the attribute filters: after price (0), before the switches that start off (1000). */
    private const FIRST_POSITION = 100;

    /**
     * @param FilterConfigStorage $storage
     * @param Defaults $defaults
     * @param FlagManager $flags
     */
    public function __construct(
        private readonly FilterConfigStorage $storage,
        private readonly Defaults $defaults,
        private readonly FlagManager $flags
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $rows = $this->storage->attributeRows();
        $fresh = $rows === [];
        $attributes = $this->storage->filterableAttributes(0);
        $order = $this->order($attributes);
        foreach ($attributes as $id => $attribute) {
            if (isset($rows[$id]) || $attribute['code'] === FilterConfigInterface::BUILTIN_PRICE) {
                continue;
            }
            $config = $this->defaults->forAttribute(
                $attribute['code'],
                $id,
                $attribute['input_type'],
                $attribute['swatch_type'],
                $attribute['position']
            );
            if ($fresh && isset($order[$id])) {
                $config = $config->setPositionOverride($order[$id]);
            }
            $this->storage->saveAttribute($id, $config->toRow());
        }
        if ($fresh && $order !== []) {
            $this->flags->saveFlag(self::FLAG_ORDERED, '1');
        }
        $builtins = $this->storage->builtinRows();
        foreach (FilterConfigInterface::BUILTIN_CODES as $code) {
            if (!isset($builtins[$code])) {
                $config = $this->defaults->forBuiltin($code);
                $this->storage->saveBuiltin($code, $config->isActive(), $config->toRow());
            }
        }

        return $this;
    }

    /**
     * Sort positions of a new install: swatch filters first, then the others, each in Magento's attribute order.
     *
     * @param array<int, array{code: string, swatch_type: string|null, position: int}> $attributes by attribute id
     * @return array<int, int> attribute id => position
     */
    private function order(array $attributes): array
    {
        $ids = array_keys(array_filter(
            $attributes,
            static fn (array $attribute): bool => $attribute['code'] !== FilterConfigInterface::BUILTIN_PRICE
        ));
        usort($ids, static fn (int $a, int $b): int => [
            $attributes[$a]['swatch_type'] === null,
            (int) $attributes[$a]['position'],
            $a,
        ] <=> [$attributes[$b]['swatch_type'] === null, (int) $attributes[$b]['position'], $b]);
        $order = [];
        foreach ($ids as $rank => $id) {
            $order[$id] = self::FIRST_POSITION + $rank * SortSave::STEP;
        }

        return $order;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
