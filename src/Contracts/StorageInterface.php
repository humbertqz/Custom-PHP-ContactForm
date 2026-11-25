<?php

declare(strict_types=1);

namespace ContactModule\Contracts;

use ContactModule\DTO\DeliveryResult;
use ContactModule\DTO\Submission;

interface StorageInterface
{
    public function save(Submission $submission): DeliveryResult;

    public function getName(): string;
}
