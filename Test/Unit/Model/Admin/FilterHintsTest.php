<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Admin;

use Iranimij\OpenFilter\Model\Admin\FilterHints;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Api\Data\FilterConfigSearchResultsInterface;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Magento\Framework\Api\Search\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Iranimij\OpenFilter\Model\Doctor\Check;
use Iranimij\OpenFilter\Model\Doctor\SystemChecks;
use PHPUnit\Framework\TestCase;

class FilterHintsTest extends TestCase
{
    public function testFiltersSharingANameAndSearchPagesWithoutFilters(): void
    {
        $defaults = new Defaults();
        $warnings = $this->hints([
            'attribute:new' => $defaults->forAttribute('new', 150, 'boolean', null, 0)->with(['label' => 'New']),
            'new' => $defaults->forBuiltin('new')->with(['label' => 'new ', 'active' => true]),
            'color' => $defaults->forAttribute('color', 93, 'select', 'visual', 10)->with(['label' => 'Color']),
            'stock' => $defaults->forBuiltin('stock')->with(['label' => 'Color', 'active' => false]),
        ], false)->warnings();

        self::assertCount(2, $warnings);
        self::assertSame('Shoppers see 2 filters called "New" (attribute new, built-in new). Rename one in its Label and tooltip section, or hide one.', $warnings[0]);
        self::assertStringContainsString('Show on search pages', $warnings[1]);
    }

    public function testNothingToSay(): void
    {
        $defaults = new Defaults();

        self::assertSame([], $this->hints([
            'color' => $defaults->forAttribute('color', 93, 'select', 'visual', 10)->with(['label' => 'Color']),
            'size' => $defaults->forAttribute('size', 144, 'select', 'text', 20)->with(['label' => 'Size']),
        ], true)->warnings());
    }

    /**
     * @param array<string, \Iranimij\OpenFilter\Model\Config\FilterConfig> $active
     */
    private function hints(array $active, bool $searchOk): FilterHints
    {
        $results = $this->createStub(FilterConfigSearchResultsInterface::class);
        $results->method('getItems')->willReturn(array_values($active) + []);
        $repository = $this->createStub(FilterConfigRepositoryInterface::class);
        $repository->method('getList')->willReturn($results);
        $criteria = $this->createStub(SearchCriteriaBuilder::class);
        $criteria->method('create')->willReturn(new SearchCriteria());
        $checks = $this->createStub(SystemChecks::class);
        $checks->method('searchPageFilters')->willReturn(new Check(
            'search_page_filters',
            'Search result pages have attribute filters',
            $searchOk,
            $searchOk ? '3 attributes' : 'No attribute is used in search result layered navigation.',
            'Choose Actions > Show on search pages.'
        ));

        return new FilterHints($repository, $criteria, $checks);
    }
}
