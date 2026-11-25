<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

contactCommand(static function (): int {
    $tty = stream_isatty(STDIN);
    if ($tty) {
        fwrite(STDERR, "Escribe una contraseña de entre 12 y 72 bytes: ");
        passthru('stty -echo', $status);
        if ($status !== 0) {
            fwrite(STDERR, "No fue posible ocultar la entrada. Usa una terminal compatible.\n");
            return 1;
        }
    }
    try {
        $password = rtrim((string) fgets(STDIN), "\r\n");
    } finally {
        if ($tty) {
            passthru('stty echo');
            fwrite(STDERR, PHP_EOL);
        }
    }
    $length = strlen($password);
    if ($length < 12 || $length > 72 || str_contains($password, "\0")) {
        fwrite(STDERR, "Usa una contraseña de entre 12 y 72 bytes.\n");
        return 1;
    }
    echo "ADMIN_PASSWORD_HASH='", password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), "'", PHP_EOL;
    return 0;
});
