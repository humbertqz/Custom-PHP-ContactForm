<?php

declare(strict_types=1);

namespace ContactModuleTests;

use ContactModule\Database\Connection;
use ContactModule\Database\MigrationManager;
use ContactModule\DTO\Submission;
use ContactModule\Queue\DatabaseQueue;
use ContactModule\Services\CleanupService;
use ContactModule\Storage\SQLiteAdapter;
use PHPUnit\Framework\TestCase;

final class CleanupTest extends TestCase
{
    public function testRetentionPreservesPendingRowsAndSupportsDryRun(): void
    {
        $config = Support::config(['LOCAL_RETENTION_MODE' => 'days']);
        $pdo = Connection::sqlite(':memory:');
        (new MigrationManager())->run($pdo, $config->package, ['submissions', 'queue', 'rate_limits']);
        $storage = new SQLiteAdapter($pdo);
        $queue = new DatabaseQueue($pdo);
        $now = time();
        $created = gmdate('Y-m-d\TH:i:s\Z', $now - 180 * 86400);
        $first = new Submission(Support::submission()->id, 'contact', $created, ['message' => 'con trabajo']);
        $second = new Submission(Support::submission()->id, 'contact', $created, ['message' => 'terminal']);
        $storage->save($first);
        $storage->save($second);
        $stored = static fn (): bool => true;
        $queue->enqueue($first, 'database_first', ['database', 'google_sheets'], null, null, $stored);
        $queue->enqueue($second, 'database_first', ['database'], null, null, $stored);
        $pdo->prepare('INSERT INTO rate_limits (bucket, hits, expires_at) VALUES (?, 1, ?)')
            ->execute([str_repeat('a', 64), $now - 10]);
        $cleanup = new CleanupService();
        $dry = $cleanup->run($config, $pdo, $pdo, $pdo, true);
        self::assertSame(1, $dry['submissions']);
        self::assertSame(1, $dry['rate_limits']);
        self::assertNotNull($storage->find($first->id));
        self::assertNotNull($storage->find($second->id));
        self::assertSame($dry, $cleanup->run($config, $pdo, $pdo, $pdo, false));
        self::assertNotNull($storage->find($first->id));
        self::assertNull($storage->find($second->id));
    }
}
