<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\OpenFilter\Model\Config\Source\CountStrategy;
use PHPUnit\Framework\TestCase;

class CountStrategyTest extends TestCase
{
    public function testAutomaticComesFirstAndEveryStrategyIsOffered(): void
    {
        $values = array_column((new CountStrategy())->toOptionArray(), 'value');

        self::assertSame(['auto', 'single_query', 'per_attribute'], $values);
    }
}
