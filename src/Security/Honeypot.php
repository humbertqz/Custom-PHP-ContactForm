<?php

declare(strict_types=1);

namespace ContactModule\Security;

final class Honeypot
{
    public static function triggered(mixed $value): bool
    {
        return $value !== null && (!is_string($value) || trim($value) !== '');
    }
}
