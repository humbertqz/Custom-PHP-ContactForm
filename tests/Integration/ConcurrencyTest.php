<?php

declare(strict_types=1);

namespace ContactModuleTests;

use ContactModule\Database\Connection;
use ContactModule\Database\MigrationManager;
use ContactModule\Queue\DatabaseQueue;
use PHPUnit\Framework\TestCase;

final class ConcurrencyTest extends TestCase
{
    public function testOnlyOneProcessClaimsAReadyJob(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open unavailable.');
        }
        $file = tempnam(sys_get_temp_dir(), 'pcm-race-');
        $workers = [];
        try {
            $pdo = Connection::sqlite($file);
            (new MigrationManager())->run($pdo, dirname(__DIR__, 2), ['queue']);
            $submission = Support::submission();
            (new DatabaseQueue($pdo))->enqueue($submission, 'email_only', ['email_notification'], null, null, null);
            for ($run = 0; $run < 2; $run++) {
                $process = proc_open(
                    [PHP_BINARY, dirname(__DIR__) . '/Fixtures/claim.php', $file, $submission->id],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes
                );
                self::assertIsResource($process);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [, $pipes]) {
                stream_set_timeout($pipes[1], 5);
                self::assertSame("ready\n", fgets($pipes[1]));
            }
            foreach ($workers as [, $pipes]) {
                fwrite($pipes[0], "\n");
                fflush($pipes[0]);
            }
            $results = [];
            foreach ($workers as [, $pipes]) {
                $results[] = trim((string) fgets($pipes[1]));
            }
            sort($results);
            self::assertSame(['busy', 'claimed'], $results);
            self::assertSame(1, (int) $pdo->query('SELECT attempts FROM deliveries')->fetchColumn());
        } finally {
            foreach ($workers as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                proc_close($process);
            }
            unset($pdo);
            foreach ([$file, $file . '-wal', $file . '-shm'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
