<?php

declare(strict_types=1);

namespace ContactModule\Storage;

use ContactModule\Contracts\StorageInterface;
use ContactModule\DTO\DeliveryResult;
use ContactModule\DTO\DeliveryStatus;
use ContactModule\DTO\Submission;

final class UnavailableDatabase implements StorageInterface
{
    public function getName(): string
    {
        return 'database';
    }

    public function save(Submission $submission): DeliveryResult
    {
        return new DeliveryResult(DeliveryStatus::Temporary, 'DATABASE_CONNECTION');
    }
}
