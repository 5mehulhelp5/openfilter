<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config\Backend;

use Iranimij\OpenFilter\Model\Config\Backend\NonNegativeNumber;
use Iranimij\OpenFilter\Model\Config\Backend\PageCacheValue;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PageCacheValueTest extends TestCase
{
    private TypeListInterface&MockObject $typeList;

    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->typeList = $this->createMock(TypeListInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    public function testAChangedValueInvalidatesTheFullPageCache(): void
    {
        $value = $this->value(PageCacheValue::class, '1', '0');
        $invalidated = [];
        $this->typeList->method('invalidate')->willReturnCallback(
            static function ($type) use (&$invalidated): void {
                $invalidated[] = $type;
            }
        );

        $value->afterSave();

        self::assertContains('full_page', $invalidated);
    }

    public function testAnUnchangedValueLeavesThePageCacheAlone(): void
    {
        $value = $this->value(PageCacheValue::class, '1', '1');
        $this->typeList->expects(self::never())->method('invalidate');

        $value->afterSave();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validNumbers(): array
    {
        return ['zero' => ['0'], 'integer' => ['5'], 'decimal' => ['0.5'], 'padded' => [' 2 ']];
    }

    /**
     * @dataProvider validNumbers
     */
    #[DataProvider('validNumbers')]
    public function testNonNegativeNumbersAreAccepted(string $number): void
    {
        $value = $this->value(NonNegativeNumber::class, $number, '1');

        $value->beforeSave();

        self::assertSame(trim($number), $value->getValue());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumbers(): array
    {
        return ['negative' => ['-1'], 'text' => ['many'], 'empty' => [''], 'exponent' => ['1e3']];
    }

    /**
     * @dataProvider invalidNumbers
     */
    #[DataProvider('invalidNumbers')]
    public function testOtherInputIsRejectedWithTheFieldLabel(string $number): void
    {
        $value = $this->value(NonNegativeNumber::class, $number, '1');
        $value->setData('field_config', ['label' => 'Max active values']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('"Max active values" needs a number of 0 or more.');

        $value->beforeSave();
    }

    public function testWholeNumbersOnlyWhenTheFieldValidatesDigits(): void
    {
        $value = $this->value(NonNegativeNumber::class, '2.5', '1');
        $value->setData('field_config', ['label' => 'Max active values', 'validate' => 'validate-digits validate-zero-or-greater']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('"Max active values" needs a whole number of 0 or more.');

        $value->beforeSave();
    }

    /**
     * @template T of PageCacheValue
     * @param class-string<T> $class
     * @param string $new
     * @param string $old
     * @return T
     */
    private function value(string $class, string $new, string $old): PageCacheValue
    {
        $this->scopeConfig->method('getValue')->willReturn($old);
        $value = (new ObjectManager($this))->getObject($class, [
            'config' => $this->scopeConfig,
            'cacheTypeList' => $this->typeList,
        ]);
        $value->setValue($new)->setPath('openfilter/seo/max_active_values')->setScope('default')->setScopeId(0);

        return $value;
    }
}
