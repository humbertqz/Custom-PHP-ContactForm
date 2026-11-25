<?php

declare(strict_types=1);

namespace ContactModule;

use ContactModule\Contracts\EventLoggerInterface;
use ContactModule\Config\Config;
use ContactModule\DTO\DeliveryStatus;
use ContactModule\DTO\Submission;
use ContactModule\Queue\DatabaseQueue;
use ContactModule\Queue\JobProcessor;
use ContactModule\Storage\PdoStorage;
use ContactModule\Storage\StorageManager;

final class ContactManager
{
    public function __construct(
        private Config $config,
        private StorageManager $storage,
        private ?PdoStorage $database,
        private ?DatabaseQueue $queue,
        private ?JobProcessor $worker,
        private EventLoggerInterface $logger,
    ) {
    }

    /** @param array<string,mixed> $form @return array{int,string} */
    public function process(Submission $submission, array $form, bool $confirmation, ?string $key): array
    {
        $mode = $this->storage->mode;
        $channels = $this->storage->channels($form, $confirmation);
        if ($this->queue === null) {
            return [$this->sendNow($submission, $form, $channels, $mode), $submission->id];
        }
        $hash = $key === null ? null : hash_hmac(
            'sha256',
            json_encode([$submission->formId, $submission->fields, $submission->sourceUrl], JSON_THROW_ON_ERROR),
            $key
        );
        $key = $key === null ? null : hash('sha256', $submission->formId . "\0" . $key);
        $sharedPrimary = $mode === 'database_first' && $this->queue->pdo === $this->database?->pdo;
        $database = $this->database;
        $stored = $this->queue->enqueue(
            $submission,
            $mode,
            $channels,
            $key,
            $hash,
            $sharedPrimary && $database !== null
                ? static fn (): bool => $database->save($submission)->status === DeliveryStatus::Completed : null
        );
        $uuid = $stored['uuid'];
        if ($stored['request_hash'] !== $hash) {
            return [409, $uuid];
        }
        if ($uuid === $submission->id) {
            if ($this->config->flag('QUEUE_PROCESS_INLINE')) {
                $this->worker?->run(count($channels), $uuid);
            } elseif ($mode === 'database_first') {
                $this->worker?->run(1, $uuid, 'database');
            }
        }
        return [StorageManager::status($stored['mode'], $this->queue->states($uuid)), $uuid];
    }

    /** @param array<string,mixed> $form @param list<string> $channels */
    private function sendNow(Submission $submission, array $form, array $channels, string $mode): int
    {
        $states = [];
        foreach ($channels as $channel) {
            if ($mode === 'fallback' && $channel === 'database' && ($states['google_sheets'] ?? '') === 'completed') {
                continue;
            }
            if ($channel === 'email_confirmation' && StorageManager::status($mode, $states) !== 200) {
                continue;
            }
            $result = $this->storage->deliver($channel, $submission, $form);
            $states[$channel] = $result->status->value;
            if ($result->status !== DeliveryStatus::Completed) {
                $this->logger->warning('delivery_result', [
                    'submission_id' => $submission->id, 'channel' => $channel,
                    'status' => $result->status->value, 'code' => $result->code,
                ]);
                if ($mode === 'database_first' && $channel === 'database') {
                    break;
                }
            }
        }
        return StorageManager::status($mode, $states);
    }
}
