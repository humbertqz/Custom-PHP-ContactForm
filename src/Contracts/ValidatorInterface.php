<?php

declare(strict_types=1);

namespace ContactModule\Contracts;

use ContactModule\DTO\ValidationResult;

interface ValidatorInterface
{
    /** @param array<string,mixed> $input @param array<string,mixed> $schema */
    public function validate(array $input, array $schema): ValidationResult;
}
