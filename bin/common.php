<?php

declare(strict_types=1);

use ContactModule\Bootstrap;
use ContactModule\Config\ConfigurationException;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
umask(0077);

function contactPackage(): string
{
    return dirname(__DIR__);
}

function contactRoot(): string
{
    $configured = getenv('CONTACT_MODULE_ROOT');
    $package = contactPackage();
    $composerRoot = str_ends_with($package, '/vendor/contact-module/php-contact-module')
        ? dirname($package, 3) : $package;
    $root = realpath($configured === false ? $composerRoot : $configured);
    if ($root === false || !is_dir($root)) {
        throw new ConfigurationException('CONTACT_MODULE_ROOT debe referir a un directorio existente.');
    }
    return $root;
}

function contactAutoload(): void
{
    if (version_compare(PHP_VERSION, '8.3.0', '<')) {
        fwrite(STDERR, "Se requiere PHP 8.3 o superior.\n");
        exit(1);
    }
    foreach (['curl', 'mbstring', 'openssl', 'pdo', 'session', 'ctype', 'filter', 'hash'] as $extension) {
        if (!extension_loaded($extension)) {
            fwrite(STDERR, "Falta la extensión $extension.\n");
            exit(1);
        }
    }
    $autoload = contactPackage() . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        $autoload = contactRoot() . '/vendor/autoload.php';
    }
    if (!is_file($autoload)) {
        fwrite(STDERR, "Ejecuta composer install o utiliza el ZIP con dependencias incluidas.\n");
        exit(1);
    }
    require_once $autoload;
}

/** @param callable():int $command */
function contactCommand(callable $command): never
{
    try {
        exit($command());
    } catch (ConfigurationException $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Operación fallida. Revisa permisos, migraciones y servicios. Tipo '
            . $exception::class . ', código ' . (int) $exception->getCode() . PHP_EOL);
    }
    exit(1);
}

function contactApplication(bool $runtime = true): Bootstrap
{
    contactAutoload();
    $app = new Bootstrap(contactPackage(), contactRoot());
    if ($runtime) {
        $app->runtime(true);
    }
    return $app;
}

/** @param array<string,mixed> $result */
function contactJson(array $result): void
{
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
}
