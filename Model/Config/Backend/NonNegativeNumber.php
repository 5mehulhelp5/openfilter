<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config\Backend;

use Magento\Framework\Exception\LocalizedException;

/**
 * A number of 0 or more (a whole number when the field validates digits), checked on the server too.
 */
class NonNegativeNumber extends PageCacheValue
{
    /**
     * @inheritDoc
     */
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());
        $field = (array) $this->getData('field_config');
        $label = (string) ($field['label'] ?? $this->getPath());
        $whole = str_contains((string) ($field['validate'] ?? ''), 'validate-digits');
        $pattern = $whole ? '/^\d+$/' : '/^\d+(\.\d+)?$/';
        if (preg_match($pattern, $value) !== 1) {
            throw new LocalizedException(
                $whole
                    ? __('"%1" needs a whole number of 0 or more.', $label)
                    : __('"%1" needs a number of 0 or more.', $label)
            );
        }
        $this->setValue($value);

        return parent::beforeSave();
    }
}
