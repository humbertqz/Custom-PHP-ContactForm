<?php

declare(strict_types=1);

namespace ContactModuleTests;

use ContactModule\Config\ConfigLoader;
use ContactModule\Config\ConfigValidator;
use ContactModule\Config\ConfigurationException;
use ContactModule\Config\FormRegistry;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testConfigurationAndAuthorizedForms(): void
    {
        $config = Support::config();
        ConfigValidator::validate($config);
        $registry = new FormRegistry($config);
        self::assertSame('contact', $registry->get('contact')['id']);
        self::assertSame('select', $registry->get('quotation')['fields']['service']['type']);
        self::assertNull($registry->get('../app'));
    }

    public function testInvalidStorageStrategyAndPublicPath(): void
    {
        foreach (
            [
            ['STORAGE_MODE' => 'parallel'],
            ['DB_PATH' => 'public/contacts.sqlite'],
            ['DB_PATH' => 'storage/../public/data.sqlite'],
            ['APP_URL' => 'https://example.com/path'],
            ['TURNSTILE_ENABLED' => 'true'],
            ['TURNSTILE_ENABLED' => 'true', 'TURNSTILE_SITE_KEY' => 'missing'],
            ['RETRY_INTERVALS_MINUTES' => '5,15000,0'],
            ['TRUSTED_PROXIES' => '0.0.0.0/0'],
            ] as $values
        ) {
            try {
                ConfigValidator::validate(Support::config($values));
                self::fail('Expected invalid private configuration.');
            } catch (ConfigurationException) {
                self::assertTrue(true);
            }
        }
    }

    public function testLoadsEnvironmentOverrides(): void
    {
        if (!class_exists(Dotenv::class)) {
            self::markTestSkipped('vlucas/phpdotenv unavailable.');
        }
        $root = sys_get_temp_dir() . '/contact-config-test-' . bin2hex(random_bytes(12));
        mkdir($root, 0700);
        file_put_contents("$root/.env", "TURNSTILE_ENABLED=false\nRATE_LIMIT_ENABLED=false\nAPP_BASE_PATH=/help\n");
        try {
            $config = ConfigLoader::load(dirname(__DIR__, 2), $root);
            self::assertSame('/help', $config->text('APP_BASE_PATH'));
            self::assertFalse($config->flag('TURNSTILE_ENABLED'));
        } finally {
            unlink("$root/.env");
            rmdir($root);
        }
    }

    public function testQuickSetupDerivesHostnameAndSeparateRateLimitKey(): void
    {
        if (!class_exists(Dotenv::class)) {
            self::markTestSkipped('vlucas/phpdotenv unavailable.');
        }
        $root = sys_get_temp_dir() . '/contact-config-test-' . bin2hex(random_bytes(12));
        mkdir($root, 0700);
        $secret = str_repeat('s', 32);
        file_put_contents("$root/.env", "APP_URL=https://www.example.test\nTURNSTILE_SITE_KEY=" . str_repeat('k', 32)
            . "\nTURNSTILE_SECRET_KEY=$secret\nTURNSTILE_ALLOWED_HOSTNAMES=@APP_HOST\nRATE_LIMIT_KEY=@TURNSTILE\n");
        try {
            $config = ConfigLoader::load(dirname(__DIR__, 2), $root);
            self::assertSame(['www.example.test'], $config->list('TURNSTILE_ALLOWED_HOSTNAMES'));
            self::assertSame(
                bin2hex(hash_hkdf('sha256', $secret, 32, 'php-contact-module/rate-limit')),
                $config->text('RATE_LIMIT_KEY')
            );
        } finally {
            unlink("$root/.env");
            rmdir($root);
        }
    }
}
