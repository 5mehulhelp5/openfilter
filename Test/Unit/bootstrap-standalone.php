<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

/*
 * Unit-test bootstrap for the coverage job, where only the module and its Composer dependencies are installed
 * (no Magento application): autoload, the translation helper the code relies on, and an autoloader that
 * generates the *Factory classes Magento would otherwise generate at runtime (the unit helper mocks them).
 */
require __DIR__ . '/../../vendor/autoload.php';

if (!function_exists('__')) {
    /**
     * @param mixed ...$argc
     * @return \Magento\Framework\Phrase
     */
    function __(...$argc): \Magento\Framework\Phrase
    {
        $text = array_shift($argc);
        if (!empty($argc) && is_array($argc[0])) {
            $argc = $argc[0];
        }

        return new \Magento\Framework\Phrase((string) $text, $argc);
    }
}

spl_autoload_register(static function (string $class): void {
    $position = (int) strrpos($class, '\\');
    $namespace = substr($class, 0, $position);
    $short = substr($class, $position + 1);
    if (str_ends_with($class, 'Factory')) {
        $base = substr($class, 0, -7);
        $body = class_exists($base) ? sprintf('return new \\%s(...$data);', $base) : 'return null;';
        $code = sprintf('namespace %s; class %s { public function create(array $data = []) { %s } }', $namespace, $short, $body);
    } elseif (str_ends_with($class, 'Extension') && interface_exists($class . 'Interface')) {
        $code = sprintf(
            'namespace %s; class %s extends \\Magento\\Framework\\Api\\AbstractSimpleObject implements \\%sInterface {}',
            $namespace,
            $short,
            $class
        );
    } else {
        return;
    }
    $directory = sys_get_temp_dir() . '/openfilter-unit-generated';
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $file = $directory . '/' . hash('sha256', $class) . '.php';
    file_put_contents($file, "<?php\n" . $code . "\n");
    require $file;
});
