<?php

declare(strict_types=1);

namespace ContactModule\Queue;

use ContactModule\DTO\Submission;
use ContactModule\Storage\StorageManager;
use ContactModule\Support\Uuid;
use PDO;
use Throwable;

final class DatabaseQueue
{
    public function __construct(public readonly PDO $pdo)
    {
    }

    /**
     * @param list<string> $channels
     * @param null|callable():bool $storePrimary
     * @return array{uuid:string,mode:string,request_hash:?string}
     */
    public function enqueue(
        Submission $submission,
        string $mode,
        array $channels,
        ?string $requestKey,
        ?string $requestHash,
        ?callable $storePrimary,
    ): array {
        $this->pdo->beginTransaction();
        try {
            $now = time();
            $insert = $this->pdo->prepare('INSERT INTO queue_submissions
                (uuid, form_id, mode, payload, request_key, request_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $submission->id, $submission->formId, $mode, $submission->encode(), $requestKey, $requestHash, $now,
            ]);
            $stored = $storePrimary !== null && $storePrimary();
            if ($stored) {
                $this->pdo->prepare('UPDATE queue_submissions SET payload = NULL WHERE uuid = ?')
                    ->execute([$submission->id]);
            }
            $insert = $this->pdo->prepare('INSERT INTO deliveries
                (submission_id, channel, status, next_retry_at, created_at, updated_at, delivered_at, attempts)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($channels as $channel) {
                $done = $channel === 'database' && $stored;
                $insert->execute([
                    $submission->id, $channel, $done ? 'completed' : 'pending',
                    $done ? null : $now, $now, $now, $done ? $now : null, $done ? 1 : 0,
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $existing = $requestKey === null ? null : $this->findRequest($requestKey);
            if ($existing !== null) {
                return $existing;
            }
            throw $exception;
        }
        return ['uuid' => $submission->id, 'mode' => $mode, 'request_hash' => $requestHash];
    }

    /** @return array{uuid:string,mode:string,request_hash:?string}|null */
    public function findRequest(string $key): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT uuid, mode, request_hash FROM queue_submissions WHERE request_key = ?'
        );
        $statement->execute([$key]);
        return $statement->fetch() ?: null;
    }

    /** @return array<string,string> */
    public function states(string $uuid): array
    {
        $query = $this->pdo->prepare('SELECT channel, status FROM deliveries WHERE submission_id = ?');
        $query->execute([$uuid]);
        return $query->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @return array<string,mixed>|null */
    public function claim(int $now, ?string $uuid, ?string $onlyChannel = null): ?array
    {
        $query = $this->pdo->prepare("SELECT d.*, s.payload, s.mode, s.form_id, sheets.status AS sheets_status
            FROM deliveries d JOIN queue_submissions s ON s.uuid = d.submission_id
            LEFT JOIN deliveries local ON local.submission_id = d.submission_id AND local.channel = 'database'
            LEFT JOIN deliveries sheets ON sheets.submission_id = d.submission_id AND sheets.channel = 'google_sheets'
            LEFT JOIN deliveries mail ON mail.submission_id = d.submission_id AND mail.channel = 'email_notification'
            WHERE d.status IN ('pending', 'retry_scheduled') AND d.next_retry_at <= ?
                AND (? IS NULL OR d.submission_id = ?) AND (? IS NULL OR d.channel = ?)
                AND (s.mode <> 'database_first' OR d.channel = 'database' OR local.status = 'completed')
                AND (s.mode <> 'fallback' OR d.channel <> 'database'
                    OR (sheets.attempts > 0 AND sheets.status <> 'processing'))
                AND (d.channel <> 'email_confirmation'
                    OR (s.mode = 'email_only' AND mail.status = 'completed')
                    OR (s.mode = 'google_sheets_only' AND sheets.status = 'completed')
                    OR (s.mode IN ('database_only', 'database_first') AND local.status = 'completed')
                    OR (s.mode = 'parallel' AND local.status = 'completed' AND sheets.status = 'completed')
                    OR (s.mode = 'fallback' AND (local.status = 'completed' OR sheets.status = 'completed')))
            ORDER BY d.next_retry_at, d.id LIMIT 16");
        $query->execute([$now, $uuid, $uuid, $onlyChannel, $onlyChannel]);
        $candidates = $query->fetchAll();
        $update = $this->pdo->prepare("UPDATE deliveries SET status = 'processing', attempts = attempts + 1,
            processing_started_at = ?, processing_token = ?, updated_at = ?
            WHERE id = ? AND status IN ('pending', 'retry_scheduled') AND attempts = ? AND next_retry_at <= ?");
        foreach ($candidates as $row) {
            $token = Uuid::create();
            $update->execute([$now, $token, $now, $row['id'], $row['attempts'], $now]);
            if ($update->rowCount() === 1) {
                $row['processing_token'] = $token;
                $row['attempts'] = (int) $row['attempts'] + 1;
                return $row;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $job */
    public function finish(array $job, string $status, ?string $code, ?int $retryAt, int $now): bool
    {
        $statement = $this->pdo->prepare("UPDATE deliveries SET status = ?, last_error_code = ?, next_retry_at = ?,
            processing_started_at = NULL, processing_token = NULL, delivered_at = ?, updated_at = ?
            WHERE id = ? AND status = 'processing' AND processing_token = ?");
        $statement->execute([
            $status, $code, $retryAt, $status === 'completed' && $code !== 'FALLBACK_SKIPPED' ? $now : null,
            $now, $job['id'], $job['processing_token'],
        ]);
        return $statement->rowCount() === 1;
    }

    public function recover(int $now, int $lease, int $maxAttempts): int
    {
        $statement = $this->pdo->prepare("UPDATE deliveries SET
            status = CASE WHEN channel IN ('email_notification', 'email_confirmation') OR attempts >= ?
                THEN 'ambiguous' ELSE 'retry_scheduled' END,
            next_retry_at = CASE WHEN channel IN ('email_notification', 'email_confirmation') OR attempts >= ?
                THEN NULL ELSE ? END,
            last_error_code = 'LEASE_EXPIRED', processing_started_at = NULL, processing_token = NULL, updated_at = ?
            WHERE status = 'processing' AND processing_started_at <= ?");
        $statement->execute([$maxAttempts, $maxAttempts, $now, $now, $now - $lease]);
        return $statement->rowCount();
    }

    public function settle(string $uuid, bool $dropPayload): void
    {
        $primary = $this->pdo->prepare("SELECT 1 FROM queue_submissions s JOIN deliveries d ON d.submission_id = s.uuid
            WHERE s.uuid = ? AND s.mode = 'database_first' AND d.channel = 'database'
                AND d.status IN ('failed', 'ambiguous')");
        $primary->execute([$uuid]);
        $blocked = $primary->fetchColumn();
        $primary->closeCursor();
        if ($blocked) {
            $this->pdo->prepare("UPDATE deliveries SET status = 'failed', last_error_code = 'PRIMARY_FAILED',
                next_retry_at = NULL, updated_at = ?
                WHERE submission_id = ? AND channel <> 'database' AND status IN ('pending', 'retry_scheduled')")
                ->execute([time(), $uuid]);
        }
        $mode = $this->pdo->prepare('SELECT mode FROM queue_submissions WHERE uuid = ?');
        $mode->execute([$uuid]);
        $strategy = $mode->fetchColumn();
        $mode->closeCursor();
        $states = $this->states($uuid);
        if (
            is_string($strategy)
            && in_array($states['email_confirmation'] ?? '', ['pending', 'retry_scheduled'], true)
            && StorageManager::status($strategy, $states) === 503
        ) {
            $this->pdo->prepare("UPDATE deliveries SET status = 'failed', last_error_code = 'PRIMARY_FAILED',
                next_retry_at = NULL, updated_at = ? WHERE submission_id = ? AND channel = 'email_confirmation'
                AND status IN ('pending', 'retry_scheduled')")->execute([time(), $uuid]);
        }
        if ($dropPayload) {
            $this->pdo->prepare("UPDATE queue_submissions SET payload = NULL WHERE uuid = ?
                AND NOT EXISTS (SELECT 1 FROM deliveries WHERE submission_id = ?
                    AND status IN ('pending', 'processing', 'retry_scheduled'))")->execute([$uuid, $uuid]);
        }
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        return array_map(
            'intval',
            $this->pdo->query('SELECT status, COUNT(*) FROM deliveries GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR)
        );
    }
}
