<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    // Allows a locally available PHPUnit to run dependency-free tests when Composer is offline.
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'ContactModule\\';
        if (str_starts_with($class, $prefix)) {
            $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}
require __DIR__ . '/Fixtures/Support.php';
