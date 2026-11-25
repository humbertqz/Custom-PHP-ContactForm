<?php

declare(strict_types=1);

namespace ContactModule\Services;

use PHPMailer\PHPMailer\SMTP;

final class TrackingSmtp extends SMTP
{
    public bool $dataStarted = false;
    public bool $dataAccepted = false;
    public int $failureCode = 0;

    // Only loss of the final reply makes it uncertain whether SMTP accepted DATA.
    protected function sendCommand($command, $commandstring, $expect)
    {
        if ($command === 'DATA END') {
            $this->dataStarted = true;
        }
        $success = parent::sendCommand($command, $commandstring, $expect);
        if ($command === 'DATA END') {
            $this->dataAccepted = $success;
        }
        if (!$success && !in_array($command, ['QUIT', 'RSET'], true)) {
            $this->failureCode = (int) $this->getError()['smtp_code'];
        }
        return $success;
    }
}
