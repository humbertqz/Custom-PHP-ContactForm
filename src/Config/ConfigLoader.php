<?php

declare(strict_types=1);

namespace ContactModule\Config;

use Dotenv\Dotenv;

final class ConfigLoader
{
    public static function load(string $package, string $root): Config
    {
        $values = Dotenv::parse((string) file_get_contents($package . '/.env.example'));
        if (is_link($root . '/.env')) {
            throw new ConfigurationException('La ruta de .env no puede ser un enlace simbólico.');
        }
        if (is_file($root . '/.env')) {
            try {
                $values = array_replace($values, Dotenv::parse((string) file_get_contents($root . '/.env')));
            } catch (\Throwable) {
                throw new ConfigurationException('No se pudo interpretar .env.');
            }
        }

        foreach ($values as $key => $value) {
            $process = getenv($key);
            $values[$key] = $process !== false ? $process : $value;
        }

        if (($values['TURNSTILE_ALLOWED_HOSTNAMES'] ?? '') === '@APP_HOST') {
            $host = parse_url($values['APP_URL'] ?? '', PHP_URL_HOST);
            $values['TURNSTILE_ALLOWED_HOSTNAMES'] = is_string($host) ? $host : '';
        }
        if (($values['RATE_LIMIT_KEY'] ?? '') === '@TURNSTILE') {
            $secret = $values['TURNSTILE_SECRET_KEY'] ?? '';
            $enabled = in_array($values['TURNSTILE_ENABLED'] ?? '', ['true', '1'], true);
            $values['RATE_LIMIT_KEY'] = $enabled && $secret !== ''
                ? bin2hex(hash_hkdf('sha256', $secret, 32, 'php-contact-module/rate-limit'))
                : '';
        }

        $config = new Config($root, $package, $values);
        ConfigValidator::validate($config);
        return $config;
    }
}
