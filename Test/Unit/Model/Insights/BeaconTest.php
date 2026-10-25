<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Insights;

use Iranimij\Base\Serializer\SafeJson;
use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Insights\Beacon;
use Iranimij\OpenFilter\Model\Layer\CategoryTree;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M6 S9 beacon validation: only beacons whose store, category, filters and values match the store view count; range
 * filters count without their ranges; picks come back sorted in the codec's form.
 */
#[AllowMockObjectsWithoutExpectations]
class BeaconTest extends TestCase
{
    private const STORE = 1;

    public function testAValidBeaconIsReadInTheCodecsForm(): void
    {
        $beacon = $this->beacon()->read($this->json(['f' => ['size' => ['m'], 'color' => ['13', 12], 'price' => ['10-20']]]), self::STORE);

        self::assertSame([
            'category' => 20,
            'picks' => ['color' => ['12', '13'], 'price' => [], 'size' => ['7']],
            'bucket' => 2,
        ], $beacon);
    }

    public function testAPageWithoutFiltersCountsAsAView(): void
    {
        self::assertSame(
            ['category' => 0, 'picks' => [], 'bucket' => -1],
            $this->beacon()->read($this->json(['c' => 0, 'b' => -1, 'f' => []]), self::STORE)
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalid(): array
    {
        $json = static fn (array $data): string => (string) json_encode($data + ['s' => 'default', 'c' => 20, 'b' => 2, 'f' => []]);

        return [
            'empty' => [''],
            'not json' => ['{"s":'],
            'too long' => [str_repeat(' ', Beacon::MAX_BYTES + 1)],
            'other store' => [$json(['s' => 'german'])],
            'no category' => [$json(['c' => '20'])],
            'unknown category' => [$json(['c' => 99])],
            'bucket too high' => [$json(['b' => 4])],
            'bucket too low' => [$json(['b' => -2])],
            'filters not an object' => [$json(['f' => 'color'])],
            'unknown filter' => [$json(['f' => ['brand' => ['1']]])],
            'inactive filter' => [$json(['f' => ['material' => ['1']]])],
            'no values' => [$json(['f' => ['color' => []]])],
            'unknown value' => [$json(['f' => ['color' => ['99']]])],
            'value not a string' => [$json(['f' => ['color' => [['12']]]])],
            'value with a comma' => [$json(['f' => ['color' => ['12,13']]])],
            'value too long' => [$json(['f' => ['color' => [str_repeat('1', 65)]]])],
            'too many values' => [$json(['f' => ['color' => array_map('strval', range(1, Beacon::MAX_VALUES + 1))]])],
        ];
    }

    /**
     * @dataProvider invalid
     */
    #[DataProvider('invalid')]
    public function testAnythingElseIsNotABeacon(string $body): void
    {
        self::assertNull($this->beacon()->read($body, self::STORE));
    }

    /**
     * @param array<string, mixed> $data
     * @return string
     */
    private function json(array $data): string
    {
        return (string) json_encode($data + ['s' => 'default', 'c' => 20, 'b' => 2, 'f' => []]);
    }

    private function beacon(): Beacon
    {
        $provider = $this->createMock(FilterConfigProvider::class);
        $provider->method('getActive')->willReturn([
            'color' => new FilterConfig(['code' => 'color', 'attribute_id' => 93, 'input_type' => 'select']),
            'size' => new FilterConfig(['code' => 'size', 'attribute_id' => 144, 'input_type' => 'select']),
            'price' => new FilterConfig(['code' => 'price', 'builtin' => true]),
            'width' => new FilterConfig(['code' => 'width', 'attribute_id' => 150, 'input_type' => 'decimal']),
        ]);
        $codec = $this->createMock(UrlCodecInterface::class);
        $codec->method('getParameterNames')->willReturn(['color' => 'color', 'size' => 'size', 'price' => 'price', 'width' => 'width', 'material' => 'material']);
        // A tiny codec: color ids 12 and 13, size "m" = option 7, any range.
        $codec->method('decode')->willReturnCallback(static function (array $params): FilterState {
            $values = [];
            foreach ($params as $param => $raw) {
                $tokens = explode(',', (string) $raw);
                $values[$param] = array_values(array_filter(array_map(static fn (string $token): ?string => match ($param) {
                    'color' => in_array($token, ['12', '13'], true) ? $token : null,
                    'size' => $token === 'm' ? '7' : null,
                    default => $token,
                }, $tokens)));
            }

            return new FilterState(array_filter($values));
        });
        $tree = $this->createMock(CategoryTree::class);
        $tree->method('forStore')->willReturn([
            2 => ['name' => '', 'parent' => 1, 'position' => 0, 'children' => [20]],
            20 => ['name' => 'Tops', 'parent' => 2, 'position' => 1, 'children' => []],
        ]);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getCode')->willReturn('default');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new Beacon(new SafeJson(), $provider, $codec, $tree, $storeManager);
    }
}
