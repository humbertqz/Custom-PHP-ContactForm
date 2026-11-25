<?php

declare(strict_types=1);

namespace ContactModule\Contracts;

use ContactModule\DTO\DeliveryResult;
use ContactModule\DTO\Submission;

interface MailerInterface
{
    /** @param array<string, mixed> $form */
    public function send(Submission $submission, array $form, bool $confirmation): DeliveryResult;
}
