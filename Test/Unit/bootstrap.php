<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

/*
 * Local unit-test bootstrap. Finds the Magento root (the directory holding dev/tests/unit/framework/bootstrap.php)
 * from MAGENTO_ROOT, the current working directory, or by walking up from this file (which may be symlinked).
 */
$candidates = array_filter([getenv('MAGENTO_ROOT') ?: null, getcwd() ?: null, dirname(__DIR__, 2)]);
foreach ($candidates as $start) {
    $dir = (string) $start;
    for ($i = 0; $i < 6; $i++) {
        $bootstrap = $dir . '/dev/tests/unit/framework/bootstrap.php';
        if (is_file($bootstrap)) {
            require $bootstrap;
            return;
        }
        $dir = dirname($dir);
    }
}
throw new RuntimeException('Magento root not found. Run from a Magento root or set MAGENTO_ROOT.');
