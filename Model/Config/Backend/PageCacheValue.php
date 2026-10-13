<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

/**
 * A setting that changes how filtered pages render: a changed value marks the full page cache invalid, like a
 * filter settings save does.
 */
class PageCacheValue extends Value
{
    /** Core's full page cache type (Magento_PageCache), named here to avoid a module dependency. */
    private const FULL_PAGE_CACHE = 'full_page';

    /**
     * @inheritDoc
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            $this->cacheTypeList->invalidate(self::FULL_PAGE_CACHE);
        }

        return parent::afterSave();
    }
}
