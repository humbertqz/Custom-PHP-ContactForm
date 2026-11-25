<?php

declare(strict_types=1);

namespace ContactModuleTests;

use ContactModule\Config\Config;
use ContactModule\Contracts\EventLoggerInterface;
use ContactModule\DTO\Submission;
use ContactModule\Support\Uuid;

final class Support
{
    /** @param array<string,string> $overrides */
    public static function config(array $overrides = [], ?string $root = null): Config
    {
        $package = dirname(__DIR__, 2);
        $example = (string) file_get_contents($package . '/.env.example');
        $values = parse_ini_string(preg_replace('/^#.*$/m', '', $example), false, INI_SCANNER_RAW);
        return new Config($root ?? $package, $package, array_replace($values, [
            'APP_ENV' => 'testing',
            'TURNSTILE_ENABLED' => 'false',
            'RATE_LIMIT_KEY' => str_repeat('x', 64),
        ], $overrides));
    }

    public static function logger(): EventLoggerInterface
    {
        return new class implements EventLoggerInterface {
            public function warning(string $event, array $context = []): void
            {
            }

            public function error(string $event, array $context = []): void
            {
            }
        };
    }

    public static function submission(string $message = '<p>¡Hola!</p>'): Submission
    {
        return new Submission(
            Uuid::create(),
            'contact',
            gmdate('Y-m-d\TH:i:s\Z'),
            ['name' => "O'Brian", 'email' => 'test@example.com', 'message' => $message, 'topics' => ['a', 'b']],
            'https://example.com/contact',
            'contact'
        );
    }
}
