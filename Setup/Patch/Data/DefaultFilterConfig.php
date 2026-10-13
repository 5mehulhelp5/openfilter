<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Setup\Patch\Data;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Stores the starting settings of every filterable attribute and built-in filter, so later changes to the defaults
 * never change a running shop. Rows that exist are left alone.
 */
class DefaultFilterConfig implements DataPatchInterface
{
    /**
     * @param FilterConfigStorage $storage
     * @param Defaults $defaults
     */
    public function __construct(
        private readonly FilterConfigStorage $storage,
        private readonly Defaults $defaults
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $rows = $this->storage->attributeRows();
        foreach ($this->storage->filterableAttributes(0) as $id => $attribute) {
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
            $this->storage->saveAttribute($id, $config->toRow());
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
