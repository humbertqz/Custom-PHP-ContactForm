<?php

declare(strict_types=1);

namespace ContactModule\Contracts;

interface EventLoggerInterface
{
    /** @param array<string,mixed> $context */
    public function warning(string $event, array $context = []): void;

    /** @param array<string,mixed> $context */
    public function error(string $event, array $context = []): void;
}
