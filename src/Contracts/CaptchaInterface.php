<?php

declare(strict_types=1);

namespace ContactModule\Contracts;

interface CaptchaInterface
{
    /** Returns false for rejected tokens; throws if the verifier is unavailable. */
    public function verify(string $token, string $action, ?string $ip): bool;
}
