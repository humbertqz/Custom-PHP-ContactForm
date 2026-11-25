<?php

declare(strict_types=1);

namespace ContactModule\Storage;

use ContactModule\Contracts\MailerInterface;
use ContactModule\Contracts\StorageInterface;
use ContactModule\DTO\DeliveryResult;
use ContactModule\DTO\DeliveryStatus;
use ContactModule\DTO\Submission;

final class StorageManager
{
    public function __construct(
        public readonly string $mode,
        private ?StorageInterface $database,
        private ?StorageInterface $sheets,
        private ?MailerInterface $mail,
    ) {
    }

    /** @param array<string,mixed> $form @return list<string> */
    public function channels(array $form, bool $confirmation): array
    {
        $enabled = $form['channels'] ?? [];
        $channels = [];
        if ($this->sheets !== null && $this->mode === 'fallback') {
            $channels[] = 'google_sheets';
        }
        if ($this->database !== null && ($enabled['database'] ?? true)) {
            $channels[] = 'database';
        }
        if ($this->sheets !== null && $this->mode !== 'fallback' && ($enabled['google_sheets'] ?? true)) {
            $channels[] = 'google_sheets';
        }
        if ($this->mail !== null && ($enabled['email_notification'] ?? true)) {
            $channels[] = 'email_notification';
        }
        if ($this->mail !== null && $confirmation && ($enabled['email_confirmation'] ?? true)) {
            $channels[] = 'email_confirmation';
        }
        return $channels;
    }

    /** @param array<string,mixed> $form */
    public function deliver(string $channel, Submission $submission, array $form): DeliveryResult
    {
        return match ($channel) {
            'database' => $this->database?->save($submission),
            'google_sheets' => $this->sheets?->save($submission),
            'email_notification', 'email_confirmation' => $this->mail?->send(
                $submission,
                $form,
                $channel === 'email_confirmation'
            ),
            default => null,
        } ?? new DeliveryResult(DeliveryStatus::Failed, 'CHANNEL_DISABLED');
    }

    /** @param array<string,string> $states */
    public static function status(string $mode, array $states): int
    {
        $required = match ($mode) {
            'email_only' => ['email_notification'],
            'google_sheets_only' => ['google_sheets'],
            'parallel', 'fallback' => ['database', 'google_sheets'],
            default => ['database'],
        };
        $completed = 0;
        $pending = 0;
        foreach ($required as $channel) {
            $state = $states[$channel] ?? '';
            $completed += $state === 'completed' ? 1 : 0;
            $pending += in_array($state, ['pending', 'processing', 'retry_scheduled'], true) ? 1 : 0;
        }
        $minimum = $mode === 'fallback' ? 1 : count($required);
        if ($completed >= $minimum) {
            foreach ($states as $state) {
                if (in_array($state, ['pending', 'processing', 'retry_scheduled'], true)) {
                    return 202;
                }
            }
            return 200;
        }
        return $completed + $pending >= $minimum ? 202 : 503;
    }
}
