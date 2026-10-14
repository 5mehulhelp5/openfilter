<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Phrase;

/**
 * Turns posted admin values (grid inline edit, filter form, attribute fieldset) into filter settings. Unknown keys
 * are ignored; numbers are checked here, whether a display type fits is checked by the Validator on save.
 */
class FilterConfigEditor
{
    /**
     * @param FilterConfig $config
     * @param array<string, mixed> $values
     * @return FilterConfig a changed copy
     * @throws InputException when a number is not a number of the right kind
     */
    public function apply(FilterConfig $config, array $values): FilterConfig
    {
        $edited = $config->with([]);
        if (isset($values['display_mode'])) {
            $edited->setDisplayMode(trim((string) $values['display_mode']));
        }
        if (isset($values['sort_by'])) {
            $edited->setSortBy(trim((string) $values['sort_by']));
        }
        if (isset($values['logic'])) {
            $edited->setLogic(trim((string) $values['logic']));
        }
        if (array_key_exists('ranges', $values)) {
            $ranges = preg_replace('/\s+/', '', (string) $values['ranges']);
            $edited->setRanges($ranges === '' || $ranges === null ? null : $ranges);
        }
        if (array_key_exists('tree_depth', $values)) {
            $edited->setTreeDepth($this->count($values['tree_depth'], __('Category tree levels')));
        }
        foreach (['multiselect' => 'setMultiselect', 'hide_single_option' => 'setHideSingleOption',
            'seo_indexable' => 'setSeoIndexable', 'active' => 'setActive'] as $key => $setter) {
            if (array_key_exists($key, $values)) {
                $edited->{$setter}($this->flag($values[$key]));
            }
        }
        if (array_key_exists('visible_options', $values)) {
            $edited->setVisibleOptions($this->count($values['visible_options'], __('Options before "Show more"')));
        }
        if (array_key_exists('search_threshold', $values)) {
            $edited->setSearchThreshold($this->count($values['search_threshold'], __('Search box from')));
        }
        if (array_key_exists('expanded', $values)) {
            $expanded = trim((string) $values['expanded']);
            $edited->setExpanded($expanded === '' || $expanded === 'auto' ? null : $this->flag($expanded));
        }
        if (array_key_exists('position_override', $values)) {
            $position = trim((string) $values['position_override']);
            $edited->setPositionOverride($position === '' ? null : $this->integer($position, __('Position')));
        } elseif (array_key_exists('position', $values)) {
            $position = $this->integer(trim((string) $values['position']), __('Position'));
            if ($position !== $config->getPosition()) {
                $edited->setPositionOverride($position);
            }
        }

        return $edited;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function flag(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param mixed $value
     * @param Phrase $field
     * @return int
     * @throws InputException
     */
    private function count(mixed $value, Phrase $field): int
    {
        $value = trim((string) $value);
        if (preg_match('/^\d+$/', $value) !== 1) {
            throw new InputException(__('"%1" needs a whole number of 0 or more.', $field));
        }

        return (int) $value;
    }

    /**
     * @param string $value
     * @param Phrase $field
     * @return int
     * @throws InputException
     */
    private function integer(string $value, Phrase $field): int
    {
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new InputException(__('"%1" needs a whole number.', $field));
        }

        return (int) $value;
    }
}
