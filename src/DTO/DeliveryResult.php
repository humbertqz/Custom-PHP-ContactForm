<?php

declare(strict_types=1);

namespace ContactModule\DTO;

final readonly class DeliveryResult
{
    public function __construct(public DeliveryStatus $status, public ?string $code = null)
    {
    }

    public static function completed(): self
    {
        return new self(DeliveryStatus::Completed);
    }
}
