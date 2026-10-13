<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Ui\Component\MassAction;

use Iranimij\OpenFilter\Ui\DataProvider\FilterListing;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Keys of the rows a mass action applies to: the ticked rows, or every row matching the grid filters ("select
 * all"), minus the excluded ones. Core's Filter applies the selection and the grid filters to our data provider;
 * its getCollection() needs a database collection, which the Filters grid does not have.
 */
class SelectedFilters
{
    /**
     * @param Filter $filter
     */
    public function __construct(private readonly Filter $filter)
    {
    }

    /**
     * @return string[]
     * @throws LocalizedException
     */
    public function keys(): array
    {
        $this->filter->applySelectionOnTargetProvider();
        $provider = $this->filter->getComponent()->getContext()->getDataProvider();
        if (!$provider instanceof FilterListing) {
            throw new LocalizedException(__('Please correct the data sent.'));
        }

        return $provider->getMatchingKeys();
    }
}
