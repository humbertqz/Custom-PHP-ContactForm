<?php

declare(strict_types=1);

namespace ContactModule\Security;

final class CsrfProtection
{
    public function __construct(private bool $secure, private string $path)
    {
    }

    public function issue(): string
    {
        $this->start();
        $token = $_SESSION['contact_csrf'] ??= bin2hex(random_bytes(32));
        session_write_close();
        return $token;
    }

    public function verify(mixed $token): bool
    {
        if (!is_string($token) || strlen($token) !== 64 || !isset($_COOKIE['PCM_SESSION'])) {
            return false;
        }
        $this->start();
        $valid = isset($_SESSION['contact_csrf']) && hash_equals($_SESSION['contact_csrf'], $token);
        session_write_close();
        return $valid;
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('Contact session requires isolated endpoints.');
        }
        session_name('PCM_SESSION');
        if (
            !session_start([
            'use_strict_mode' => 1, 'use_only_cookies' => 1, 'cookie_httponly' => 1,
            'cookie_secure' => $this->secure, 'cookie_samesite' => 'Lax', 'cookie_path' => $this->path ?: '/',
            'cache_limiter' => '',
            ])
        ) {
            throw new \RuntimeException('Session unavailable.');
        }
    }
}
