<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 *
 * Fails when the statement coverage of any given namespace directory is below the threshold.
 * Usage: php coverage-gate.php clover.xml 85 Model/Variable Model/Condition ...
 */

declare(strict_types=1);

// phpcs:ignoreFile -- CI helper script, runs outside the Magento application.

$clover = $argv[1] ?? '';
$threshold = (float) ($argv[2] ?? 85);
$directories = array_slice($argv, 3);
if (!is_file($clover) || $directories === []) {
    fwrite(STDERR, "Usage: coverage-gate.php <clover.xml> <threshold> <dir> [<dir> ...]\n");
    exit(2); // phpcs:ignore Magento2.Security.LanguageConstruct.ExitUsage
}
$xml = simplexml_load_file($clover);
if ($xml === false) {
    fwrite(STDERR, "Cannot parse $clover\n");
    exit(2); // phpcs:ignore Magento2.Security.LanguageConstruct.ExitUsage
}
$totals = [];
foreach ($directories as $directory) {
    $totals[$directory] = ['statements' => 0, 'covered' => 0, 'files' => 0];
}
foreach ($xml->xpath('//file') as $file) {
    $name = str_replace('\\', '/', (string) $file['name']);
    foreach ($directories as $directory) {
        if (!str_contains($name, '/' . trim($directory, '/') . '/')) {
            continue;
        }
        $metrics = $file->metrics;
        $totals[$directory]['statements'] += (int) $metrics['statements'];
        $totals[$directory]['covered'] += (int) $metrics['coveredstatements'];
        $totals[$directory]['files']++;
    }
}
$failed = false;
foreach ($totals as $directory => $total) {
    $percent = $total['statements'] === 0 ? 0.0 : $total['covered'] / $total['statements'] * 100;
    $ok = $total['files'] > 0 && $percent >= $threshold;
    $failed = $failed || !$ok;
    printf("%-22s %6.2f %% (%d/%d statements, %d files) %s\n", $directory, $percent, $total['covered'], $total['statements'], $total['files'], $ok ? 'ok' : 'BELOW ' . $threshold . ' %');
}
exit($failed ? 1 : 0); // phpcs:ignore Magento2.Security.LanguageConstruct.ExitUsage
