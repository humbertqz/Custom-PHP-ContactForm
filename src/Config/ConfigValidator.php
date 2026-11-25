<?php

declare(strict_types=1);

namespace ContactModule\Config;

use ContactModule\Security\TrustedProxyResolver;
use PDO;

final class ConfigValidator
{
    public static function validate(Config $config): void
    {
        self::oneOf($config, 'APP_ENV', ['production', 'local', 'testing']);
        self::oneOf($config, 'LOCAL_RETENTION_MODE', ['permanent', 'until_synced', 'days', 'none']);
        self::oneOf($config, 'TURNSTILE_THEME', ['auto', 'light', 'dark']);
        self::oneOf($config, 'TURNSTILE_APPEARANCE', ['always', 'execute', 'interaction-only']);
        self::oneOf($config, 'TURNSTILE_SIZE', ['normal', 'compact', 'flexible']);
        $scheme = $config->text('APP_ENV') === 'production' ? 'https' : 'https?';
        foreach ([$config->text('APP_URL'), ...$config->list('ALLOWED_ORIGINS')] as $origin) {
            if (
                !filter_var($origin, FILTER_VALIDATE_URL)
                || !preg_match("~\A$scheme://(?:[A-Za-z0-9.-]+|\[[a-fA-F0-9:]+\])(?::[0-9]{1,5})?\z~D", $origin)
            ) {
                throw new ConfigurationException('Origen inválido en APP_URL o ALLOWED_ORIGINS.');
            }
        }
        if (
            !in_array($config->text('APP_TIMEZONE'), timezone_identifiers_list(), true)
            || !preg_match('~\A(?:/[a-zA-Z0-9_-]+)*\z~D', $config->text('APP_BASE_PATH'))
        ) {
            throw new ConfigurationException('Zona horaria o ruta pública inválida.');
        }

        $database = $config->flag('DATABASE_ENABLED');
        $sheets = $config->flag('GOOGLE_SHEETS_ENABLED');
        $mail = $config->flag('MAIL_ENABLED');
        $queue = $config->flag('QUEUE_ENABLED');
        $mode = $config->text('STORAGE_MODE');
        $valid = match ($mode) {
            'email_only' => $mail && !$database && !$sheets,
            'google_sheets_only' => !$database && $sheets,
            'database_only' => $database && !$sheets,
            'parallel', 'fallback' => $database && $sheets,
            'database_first' => $database,
            default => false,
        };
        if (!$valid || ($database && $config->text('LOCAL_RETENTION_MODE') === 'none')) {
            throw new ConfigurationException('STORAGE_MODE, canales o retención incompatibles.');
        }
        foreach (['DB_DRIVER', 'QUEUE_DRIVER', 'RATE_LIMIT_DRIVER'] as $key) {
            self::oneOf($config, $key, ['sqlite', 'mysql', 'mariadb']);
        }
        foreach (['DB_PATH', 'QUEUE_DB_PATH'] as $key) {
            $config->path($config->text($key));
        }
        $drivers = [];
        if ($database) {
            $drivers[] = $config->text('DB_DRIVER');
        }
        if ($queue) {
            $drivers[] = $config->text('QUEUE_DRIVER');
        }
        if ($config->flag('RATE_LIMIT_ENABLED')) {
            if (strlen($config->text('RATE_LIMIT_KEY')) < 32) {
                throw new ConfigurationException('RATE_LIMIT_KEY requiere al menos 32 caracteres.');
            }
            $drivers[] = $config->text('RATE_LIMIT_DRIVER');
        }
        foreach ($drivers as $driver) {
            if (!in_array($driver === 'sqlite' ? 'sqlite' : 'mysql', PDO::getAvailableDrivers(), true)) {
                throw new ConfigurationException("Motor PDO no disponible: $driver.");
            }
            if ($driver !== 'sqlite') {
                self::required($config, ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME']);
                if (
                    strpbrk($config->text('DB_HOST') . $config->text('DB_DATABASE'), ";\0\r\n") !== false
                    || $config->text('DB_CHARSET') !== 'utf8mb4'
                ) {
                    throw new ConfigurationException('DSN MySQL inválido.');
                }
            }
        }
        if ($mail) {
            self::required($config, ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS']);
            self::oneOf($config, 'MAIL_ENCRYPTION', ['tls', 'ssl']);
            if (filter_var($config->text('MAIL_HOST'), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                throw new ConfigurationException('MAIL_HOST inválido.');
            }
            self::addresses([$config->text('MAIL_FROM_ADDRESS'), ...$config->list('MAIL_TO_ADDRESS')]);
            if ($config->list('MAIL_TO_ADDRESS') === [] || preg_match('/[\r\n]/', $config->text('MAIL_FROM_NAME'))) {
                throw new ConfigurationException('Configuración de direcciones SMTP incompleta.');
            }
            self::addresses([...$config->list('MAIL_CC'), ...$config->list('MAIL_BCC')]);
        }
        if (
            $config->flag('MAIL_CONFIRMATION_ENABLED')
            && (!$mail || !$config->flag('TURNSTILE_ENABLED') || !$config->flag('RATE_LIMIT_ENABLED'))
        ) {
            throw new ConfigurationException('Las confirmaciones requieren SMTP, Turnstile y rate limiting.');
        }
        $url = $config->text('GOOGLE_SCRIPT_URL');
        if (
            $sheets && (
                !preg_match('~\Ahttps://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec\z~D', $url)
                || !preg_match('/\A[A-Za-z0-9_-]{32,256}\z/D', $config->text('GOOGLE_SCRIPT_TOKEN'))
            )
        ) {
            throw new ConfigurationException('URL de Apps Script o token inválido.');
        }
        if ($config->flag('TURNSTILE_ENABLED')) {
            self::required($config, ['TURNSTILE_SITE_KEY', 'TURNSTILE_SECRET_KEY', 'TURNSTILE_ALLOWED_HOSTNAMES']);
            foreach (['TURNSTILE_SITE_KEY', 'TURNSTILE_SECRET_KEY'] as $key) {
                if (!preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D', $config->text($key))) {
                    throw new ConfigurationException("Clave Turnstile inválida: $key.");
                }
            }
            foreach ($config->list('TURNSTILE_ALLOWED_HOSTNAMES') as $hostname) {
                if (filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                    throw new ConfigurationException('Hostname inválido para Turnstile.');
                }
            }
        }
        foreach (
            ['APP_DEBUG', 'FALLBACK_DELETE_AFTER_SYNC', 'QUEUE_PROCESS_INLINE', 'RETRY_ENABLED',
            'HONEYPOT_ENABLED', 'CSRF_ENABLED'] as $key
        ) {
            $config->flag($key);
        }
        foreach (
            ['DB_PORT', 'MAIL_PORT', 'RATE_LIMIT_MAX_REQUESTS', 'RATE_LIMIT_WINDOW_SECONDS',
            'RATE_LIMIT_INSTALLATION_MAX', 'RATE_LIMIT_FORM_MAX', 'RATE_LIMIT_IP_MAX', 'CONFIRMATION_MAX_PER_HOUR',
            'LOCAL_RETENTION_DAYS', 'LOG_RETENTION_DAYS', 'QUEUE_RETENTION_DAYS'] as $key
        ) {
            $config->integer($key);
        }
        $config->integer('MAX_REQUEST_SIZE_KB', 1, 1024);
        $config->integer('QUEUE_MAX_RUN_SECONDS', 1, 3600);
        $attempts = $config->integer('RETRY_MAX_ATTEMPTS', 1, 20);
        $intervals = $config->list('RETRY_INTERVALS_MINUTES');
        if (
            ($queue && $config->flag('RETRY_ENABLED') && count($intervals) < $attempts - 1)
            || array_filter($intervals, static fn (string $delay): bool => !ctype_digit($delay)
                || (int) $delay < 1 || (int) $delay > 525600)
        ) {
            throw new ConfigurationException('Intervalos de reintento inválidos.');
        }
        $timeout = max(
            $config->integer('MAIL_TIMEOUT', 1, 60),
            $config->integer('GOOGLE_SCRIPT_TIMEOUT', 1, 60),
            $config->integer('TURNSTILE_TIMEOUT', 1, 60)
        );
        $config->integer('QUEUE_LEASE_SECONDS', 2 * $timeout + 15, 86400);
        self::oneOf($config, 'LOG_LEVEL', [
            'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency',
        ]);
        if (!FormRegistry::validAction($config->text('TURNSTILE_ACTION'))) {
            throw new ConfigurationException('TURNSTILE_ACTION inválida.');
        }
        if (!preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $config->text('HONEYPOT_FIELD'))) {
            throw new ConfigurationException('HONEYPOT_FIELD inválido.');
        }
        new TrustedProxyResolver($config->list('TRUSTED_PROXIES'));
    }

    /** @param list<string> $addresses */
    public static function addresses(array $addresses): void
    {
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new ConfigurationException('Dirección de correo configurada inválida.');
            }
        }
    }

    /** @param list<string> $allowed */
    private static function oneOf(Config $config, string $key, array $allowed): void
    {
        if (!in_array($config->text($key), $allowed, true)) {
            throw new ConfigurationException("Valor inválido: $key");
        }
    }

    /** @param list<string> $keys */
    private static function required(Config $config, array $keys): void
    {
        foreach ($keys as $key) {
            if (trim($config->text($key)) === '') {
                throw new ConfigurationException("Falta configurar $key.");
            }
        }
    }
}
