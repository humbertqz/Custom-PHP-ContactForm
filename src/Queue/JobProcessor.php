<?php

declare(strict_types=1);

namespace ContactModule\Queue;

use ContactModule\Contracts\EventLoggerInterface;
use ContactModule\Config\Config;
use ContactModule\Config\FormRegistry;
use ContactModule\DTO\DeliveryResult;
use ContactModule\DTO\DeliveryStatus;
use ContactModule\DTO\Submission;
use ContactModule\Storage\PdoStorage;
use ContactModule\Storage\StorageManager;
use Throwable;

final class JobProcessor
{
    public function __construct(
        private Config $config,
        private DatabaseQueue $queue,
        private StorageManager $storage,
        private ?PdoStorage $database,
        private FormRegistry $forms,
        private EventLoggerInterface $logger,
    ) {
    }

    public function run(int $limit, ?string $uuid = null, ?string $onlyChannel = null): int
    {
        $start = hrtime(true);
        $max = $this->config->flag('RETRY_ENABLED') ? $this->config->integer('RETRY_MAX_ATTEMPTS') : 1;
        if ($uuid === null) {
            $this->queue->recover(time(), $this->config->integer('QUEUE_LEASE_SECONDS'), $max);
            $this->settleAbandonedJobs();
        }
        $processed = 0;
        while (
            $processed < $limit && (hrtime(true) - $start) / 1e9 < $this->config->integer('QUEUE_MAX_RUN_SECONDS')
            && ($job = $this->queue->claim(time(), $uuid, $onlyChannel)) !== null
        ) {
            $this->process($job, $max);
            $processed++;
        }
        return $processed;
    }

    private function settleAbandonedJobs(): void
    {
        $items = $this->queue->pdo->query("SELECT DISTINCT s.uuid FROM queue_submissions s
            JOIN deliveries d ON s.uuid = d.submission_id WHERE d.last_error_code = 'LEASE_EXPIRED'
                AND d.status = 'ambiguous' AND (s.payload IS NOT NULL OR EXISTS (
                    SELECT 1 FROM deliveries open_job WHERE open_job.submission_id = s.uuid
                        AND open_job.channel IN ('email_confirmation', 'google_sheets', 'email_notification')
                        AND open_job.status IN ('pending', 'retry_scheduled')
                )) LIMIT 100");
        $uuids = $items->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($uuids as $uuid) {
            $this->queue->settle($uuid, true);
        }
    }

    /** @param array<string,mixed> $job */
    private function process(array $job, int $max): void
    {
        $uuid = $job['submission_id'];
        $channel = $job['channel'];
        try {
            $result = $this->deliver($job);
        } catch (Throwable $error) {
            $result = new DeliveryResult(DeliveryStatus::Ambiguous, 'WORKER_EXCEPTION');
        }
        $status = $result->status->value;
        $when = null;
        if (
            ($result->status === DeliveryStatus::Temporary
                || ($result->status === DeliveryStatus::Ambiguous && !str_starts_with($channel, 'email_')))
            && $job['attempts'] < $max
        ) {
            $status = 'retry_scheduled';
            $when = time() + 60 * (int) $this->config->list('RETRY_INTERVALS_MINUTES')[$job['attempts'] - 1];
        } elseif ($result->status === DeliveryStatus::Temporary) {
            $status = 'failed';
        }
        if ($this->queue->finish($job, $status, $result->code, $when, time())) {
            $this->queue->settle($uuid, true);
            if ($result->status !== DeliveryStatus::Completed) {
                $this->logger->warning('delivery_result', [
                    'submission_id' => $uuid, 'channel' => $channel, 'status' => $status, 'code' => $result->code,
                ]);
            }
        }
    }

    /** @param array<string,mixed> $job */
    private function deliver(array $job): DeliveryResult
    {
        $form = $this->forms->get($job['form_id']);
        if ($form === null) {
            return new DeliveryResult(DeliveryStatus::Failed, 'FORM_DISABLED');
        }
        $channel = $job['channel'];
        if ($job['mode'] === 'fallback' && $channel === 'database' && $job['sheets_status'] === 'completed') {
            return new DeliveryResult(DeliveryStatus::Completed, 'FALLBACK_SKIPPED');
        }
        if (
            ($form['channels'][$channel] ?? true) === false || (
            $channel === 'email_confirmation' && !$this->config->flag('MAIL_CONFIRMATION_ENABLED')
            )
        ) {
            return new DeliveryResult(DeliveryStatus::Failed, 'CHANNEL_DISABLED');
        }
        $submission = is_string($job['payload'])
            ? Submission::decode($job['payload']) : $this->database?->find($job['submission_id']);
        if ($submission === null) {
            return new DeliveryResult(DeliveryStatus::Failed, 'PAYLOAD_MISSING');
        }
        if ($submission->sheetsRoute === '' && isset($form['sheets_route'])) {
            $submission = new Submission(
                $submission->id,
                $submission->formId,
                $submission->createdAt,
                $submission->fields,
                $submission->sourceUrl,
                $form['sheets_route']
            );
        }
        return $this->storage->deliver($channel, $submission, $form);
    }
}
