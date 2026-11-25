<?php

declare(strict_types=1);

namespace ContactModule\DTO;

final readonly class ValidationResult
{
    /** @param array<string, mixed> $fields @param array<string, string> $errors */
    public function __construct(public array $fields, public array $errors)
    {
    }
}
