<?php

declare(strict_types=1);

namespace ContactModule\Services;

use ContactModule\Config\Config;
use PDO;

final class CleanupService
{
    /** @return array<string,int> */
    public function run(Config $config, ?PDO $pdo, ?PDO $queue, ?PDO $rateDb, bool $dryRun): array
    {
        $now = time();
        $result = ['logs' => 0, 'rate_limits' => 0, 'submissions' => 0, 'jobs' => 0, 'payloads' => 0];
        $outdated = $now - $config->integer('LOG_RETENTION_DAYS') * 86400;
        foreach (glob($config->path('storage/logs') . '/contact-????-??-??.log') ?: [] as $file) {
            if (!is_link($file) && is_file($file) && filemtime($file) < $outdated && ($dryRun || unlink($file))) {
                $result['logs']++;
            }
        }
        if ($rateDb !== null) {
            $result['rate_limits'] = $this->mutate($rateDb, 'rate_limits', 'expires_at <= ?', [$now], $dryRun);
        }
        $terminal = "NOT EXISTS (SELECT 1 FROM deliveries d WHERE d.submission_id = queue_submissions.uuid
            AND d.status IN ('pending', 'processing', 'retry_scheduled'))";
        if ($pdo !== null) {
            $daysCutoff = gmdate('Y-m-d\TH:i:s\Z', $now - $config->integer('LOCAL_RETENTION_DAYS') * 86400);
            $mode = $config->text('LOCAL_RETENTION_MODE');
            $afterSync = $config->flag('FALLBACK_DELETE_AFTER_SYNC');
            $where = match ($mode) {
                'days' => 'created_at <= ?',
                'until_synced' => $queue === null ? '1 = 1' : '1 = 0',
                default => '1 = 0',
            };
            $args = $mode === 'days' ? [$daysCutoff] : [];
            if ($queue === null) {
                $result['submissions'] = $this->mutate($pdo, 'submissions', $where, $args, $dryRun);
            } else {
                $pending = $queue->prepare("SELECT 1 FROM queue_submissions WHERE uuid = ? AND NOT ($terminal)");
                $successful = $queue->prepare("SELECT 1 FROM deliveries
                    WHERE submission_id = ? AND channel = 'google_sheets' AND status = 'completed'");
                $rows = $pdo->prepare('SELECT id, uuid, created_at FROM submissions
                    WHERE id > ? ORDER BY id LIMIT 250');
                $last = 0;
                while (true) {
                    $rows->execute([$last]);
                    $batch = $rows->fetchAll();
                    if ($batch === []) {
                        break;
                    }
                    foreach ($batch as $row) {
                        $last = (int) $row['id'];
                        $uuid = $row['uuid'];
                        $pending->execute([$uuid]);
                        $open = $pending->fetchColumn();
                        $pending->closeCursor();
                        if ($open) {
                            continue;
                        }
                        $successful->execute([$uuid]);
                        $sheets = $successful->fetchColumn();
                        $successful->closeCursor();
                        if (
                            $mode === 'until_synced' || ($mode === 'days' && $row['created_at'] <= $daysCutoff)
                            || ($afterSync && $config->text('STORAGE_MODE') === 'fallback' && $sheets)
                        ) {
                            $result['submissions'] += $this->mutate($pdo, 'submissions', 'uuid = ?', [$uuid], $dryRun);
                        }
                    }
                }
            }
        }
        if ($queue !== null) {
            $result['payloads'] = $this->mutate(
                $queue,
                'queue_submissions',
                "payload IS NOT NULL AND $terminal",
                [],
                $dryRun,
                'SET payload = NULL'
            );
            $older = $now - $config->integer('QUEUE_RETENTION_DAYS') * 86400;
            $result['jobs'] = $this->mutate(
                $queue,
                'queue_submissions',
                "created_at <= ? AND $terminal AND NOT EXISTS
                    (SELECT 1 FROM deliveries d WHERE d.submission_id = queue_submissions.uuid AND d.updated_at > ?)",
                [$older, $older],
                $dryRun
            );
        }
        return $result;
    }

    /** @param list<int|string> $parameters */
    private function mutate(
        PDO $pdo,
        string $table,
        string $where,
        array $parameters,
        bool $dryRun,
        ?string $update = null,
    ): int {
        $prefix = $dryRun ? "SELECT COUNT(*) FROM $table" : (
            $update === null ? "DELETE FROM $table" : "UPDATE $table $update"
        );
        $statement = $pdo->prepare("$prefix WHERE $where");
        $statement->execute($parameters);
        return $dryRun ? (int) $statement->fetchColumn() : $statement->rowCount();
    }
}
