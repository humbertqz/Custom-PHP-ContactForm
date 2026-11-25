<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

contactCommand(static function (): int {
    contactAutoload();
    $root = contactRoot();
    foreach (['storage', 'storage/database', 'storage/cache', 'storage/logs'] as $directory) {
        $path = "$root/$directory";
        if (is_link($path) || (!is_dir($path) && !mkdir($path, 0700)) || !is_writable($path)) {
            throw new \RuntimeException('Storage path unavailable.');
        }
    }
    $file = "$root/.env";
    if (is_link($file)) {
        throw new \RuntimeException('Invalid environment path.');
    }
    if (!is_file($file)) {
        $handle = fopen($file, 'xb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot create env file.');
        }
        try {
            $example = (string) file_get_contents(contactPackage() . '/.env.example');
            $key = bin2hex(random_bytes(32));
            $example = str_replace("\nRATE_LIMIT_KEY=\n", "\nRATE_LIMIT_KEY=$key\n", $example);
            if (fwrite($handle, $example) !== strlen($example)) {
                throw new \RuntimeException('Incomplete configuration file.');
            }
        } finally {
            fclose($handle);
        }
        echo "Se creó .env. Ajusta APP_URL, ALLOWED_ORIGINS, los canales y las claves Turnstile.\n";
        if (!in_array('--non-interactive', $_SERVER['argv'], true) && stream_isatty(STDIN)) {
            echo "Pulsa Intro cuando hayas guardado tus cambios, o Ctrl+C para salir.\n";
            fgets(STDIN);
        }
    }
    $app = contactApplication(false);
    foreach (['DB' => 'DATABASE_ENABLED', 'QUEUE_DB' => 'QUEUE_ENABLED'] as $prefix => $enabled) {
        $driver = $prefix === 'DB' ? 'DB_DRIVER' : 'QUEUE_DRIVER';
        if (!$app->config->flag($enabled) || $app->config->text($driver) !== 'sqlite') {
            continue;
        }
        $directory = dirname($app->config->path($app->config->text($prefix . '_PATH')));
        if ((!is_dir($directory) && !mkdir($directory, 0700, true)) || !is_writable($directory)) {
            throw new RuntimeException('SQLite path unavailable.');
        }
    }
    $app->runtime(true);
    $app->migrate();
    echo "Instalación inicializada. Copia public/ al sitio y ejecuta bin/healthcheck.php.\n";
    return 0;
});
