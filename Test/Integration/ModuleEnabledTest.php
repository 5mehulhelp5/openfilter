<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration;

use Magento\Framework\Module\ModuleList;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ModuleEnabledTest extends TestCase
{
    public function testModuleAndTheSharedBaseAreEnabled(): void
    {
        $moduleList = Bootstrap::getObjectManager()->get(ModuleList::class);

        self::assertTrue($moduleList->has('Iranimij_Base'));
        self::assertTrue($moduleList->has('Iranimij_OpenFilter'));
    }
}
