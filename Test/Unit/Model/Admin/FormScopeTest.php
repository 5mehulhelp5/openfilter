<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Admin;

use Iranimij\OpenFilter\Model\Admin\FormScope;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormScopeTest extends TestCase
{
    /**
     * @dataProvider values
     */
    #[DataProvider('values')]
    public function testOnlyExistingStoreViewsAreAScope(mixed $value, int $expected): void
    {
        self::assertSame($expected, $this->scope(false)->resolve($value));
    }

    /**
     * @return array<string, array{mixed, int}>
     */
    public static function values(): array
    {
        return [
            'store view' => ['2', 2],
            'as int' => [3, 3],
            'all store views' => ['0', 0],
            'missing' => [null, 0],
            'unknown id' => ['99', 0],
            'not a number' => ['de', 0],
            'negative' => ['-2', 0],
            'array' => [['2'], 0],
        ];
    }

    public function testSingleStoreModeHasNoStoreViewScope(): void
    {
        self::assertSame(0, $this->scope(true)->resolve('2'));
        self::assertTrue($this->scope(true)->isSingleStore());
    }

    public function testStoresAreListedInIdOrder(): void
    {
        self::assertSame([2 => 'Deutsch', 3 => 'Hyvä 1.5.2'], $this->scope(false)->stores());
    }

    private function scope(bool $single): FormScope
    {
        $stores = [];
        foreach ([3 => 'Hyvä 1.5.2', 2 => 'Deutsch'] as $id => $name) {
            $store = $this->createStub(StoreInterface::class);
            $store->method('getId')->willReturn($id);
            $store->method('getName')->willReturn($name);
            $stores[$id] = $store;
        }
        $manager = $this->createStub(StoreManagerInterface::class);
        $manager->method('getStores')->willReturn($stores);
        $manager->method('isSingleStoreMode')->willReturn($single);

        return new FormScope($manager);
    }
}
