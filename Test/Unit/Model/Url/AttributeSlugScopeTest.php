<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Url;

use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Model\Url\AttributeSlugScope;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AttributeSlugScopeTest extends TestCase
{
    private const ATTRIBUTE = 93;
    private const DE = 2;

    private SlugRepositoryInterface&MockObject $slugs;

    private SlugStorage&MockObject $storage;

    /** @var array<int, string> store id => effective name */
    private array $names = [0 => 'color', self::DE => 'farbe'];

    protected function setUp(): void
    {
        $this->slugs = $this->createMock(SlugRepositoryInterface::class);
        $this->slugs->method('getAttributeSlug')->willReturnCallback(
            fn (int $attributeId, int $storeId): string => $this->names[$storeId] ?? $this->names[0]
        );
        $this->storage = $this->createMock(SlugStorage::class);
    }

    public function testOwnIsEmptyWhenTheStoreViewFollowsTheSharedName(): void
    {
        self::assertSame('farbe', $this->scope()->own(self::ATTRIBUTE, self::DE));
        self::assertSame('', $this->scope()->own(self::ATTRIBUTE, 3));
        self::assertSame('color', $this->scope()->own(self::ATTRIBUTE, 0));
    }

    public function testTheSharedNameIsRequired(): void
    {
        $this->slugs->expects(self::never())->method('saveAttributeSlug');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The URL name for all store views cannot be empty.');

        $this->scope()->save(self::ATTRIBUTE, 0, '  ');
    }

    public function testAChangedSharedNameIsSavedAndAnUnchangedOneIsNot(): void
    {
        $this->slugs->expects(self::once())->method('saveAttributeSlug')->with(self::ATTRIBUTE, 0, 'colour');

        $this->scope()->save(self::ATTRIBUTE, 0, 'color');
        $this->scope()->save(self::ATTRIBUTE, 0, ' colour ');
    }

    public function testAStoreViewGetsItsOwnName(): void
    {
        $this->slugs->expects(self::once())->method('saveAttributeSlug')->with(self::ATTRIBUTE, 3, 'lon');
        $this->storage->expects(self::never())->method('writeAttributeSlug');

        $this->scope()->save(self::ATTRIBUTE, 3, 'lon');
    }

    public function testEmptyOrTheSharedNameInAStoreViewRemovesItsOwnName(): void
    {
        $this->slugs->expects(self::never())->method('saveAttributeSlug');
        $this->storage->expects(self::once())->method('writeAttributeSlug')->with(self::ATTRIBUTE, self::DE, null);

        $this->scope()->save(self::ATTRIBUTE, self::DE, '');
    }

    public function testAStoreViewWithoutItsOwnNameWritesNothingForEmpty(): void
    {
        $this->slugs->expects(self::never())->method('saveAttributeSlug');
        $this->storage->expects(self::never())->method('writeAttributeSlug');

        $this->scope()->save(self::ATTRIBUTE, 3, 'color');
    }

    private function scope(): AttributeSlugScope
    {
        return new AttributeSlugScope(
            $this->slugs,
            $this->storage,
            $this->createStub(SlugProvider::class),
            $this->createStub(FrontendInterface::class),
            $this->createStub(TypeListInterface::class)
        );
    }
}
