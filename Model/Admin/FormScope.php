<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Admin;

use Magento\Store\Model\StoreManagerInterface;

/**
 * The store view the filter form edits (request parameter "store", chosen with Magento's store switcher):
 * 0 = all store views. Unknown ids and single-store mode fall back to 0, so a form never edits a store view
 * that does not exist.
 */
class FormScope
{
    public const PARAM = 'store';

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param mixed $value the request parameter
     * @return int store view id, 0 = all store views
     */
    public function resolve(mixed $value): int
    {
        if ($this->isSingleStore() || !is_scalar($value) || !ctype_digit((string) $value)) {
            return 0;
        }
        $storeId = (int) $value;

        return isset($this->stores()[$storeId]) ? $storeId : 0;
    }

    /**
     * @return array<int, string> store view id => name, in id order (admin left out)
     */
    public function stores(): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            $stores[(int) $store->getId()] = (string) $store->getName();
        }
        ksort($stores);

        return $stores;
    }

    /**
     * One store view only: the form shows no scope and no store view fields.
     *
     * @return bool
     */
    public function isSingleStore(): bool
    {
        return $this->storeManager->isSingleStoreMode();
    }
}
