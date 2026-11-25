<?php

declare(strict_types=1);

namespace ContactModuleTests;

use ContactModule\Database\Connection;
use ContactModule\Database\MigrationManager;
use ContactModule\DTO\DeliveryStatus;
use ContactModule\Queue\DatabaseQueue;
use ContactModule\Security\RateLimiter;
use ContactModule\Storage\MySQLAdapter;
use ContactModule\Storage\SQLiteAdapter;
use PDOException;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testSqliteMigrationsAndSubmissionAreIdempotent(): void
    {
        $pdo = Connection::sqlite(':memory:');
        $migration = new MigrationManager();
        $root = dirname(__DIR__, 2);
        $migration->run($pdo, $root, ['submissions']);
        $migration->run($pdo, $root, ['submissions', 'queue']);
        $migration->run($pdo, $root, ['submissions', 'queue']);
        $storage = new SQLiteAdapter($pdo);
        $submission = Support::submission("Información sobre O'Brian; prueba UTF-8");
        self::assertSame(DeliveryStatus::Completed, $storage->save($submission)->status);
        self::assertSame(DeliveryStatus::Completed, $storage->save($submission)->status);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn());
        self::assertEquals($submission->fields, $storage->find($submission->id)->fields);
        self::assertSame('contact', $storage->find($submission->id)->sheetsRoute);
    }

    public function testQueueRollbackAndIdempotencyConflictData(): void
    {
        $pdo = Connection::sqlite(':memory:');
        (new MigrationManager())->run($pdo, dirname(__DIR__, 2), ['queue', 'submissions']);
        $queue = new DatabaseQueue($pdo);
        $storage = new SQLiteAdapter($pdo);
        $submission = Support::submission();
        try {
            $store = static fn (): bool => $storage->save($submission)->status === DeliveryStatus::Completed;
            $queue->enqueue($submission, 'database_first', ['database', 'database'], 'key', 'a', $store);
            self::fail('Expected duplicate work channel to roll back entire transaction.');
        } catch (PDOException) {
            self::assertNull($storage->find($submission->id));
        }
        $queue->enqueue($submission, 'database_first', ['database'], 'key', 'a', static function () use (
            $storage,
            $submission
        ): bool {
            return $storage->save($submission)->status === DeliveryStatus::Completed;
        });
        $existing = $queue->enqueue(Support::submission(), 'database_first', ['database'], 'key', 'different', null);
        self::assertSame($submission->id, $existing['uuid']);
        self::assertSame('a', $existing['request_hash']);
        self::assertSame(['database' => 'completed'], $queue->states($submission->id));
        self::assertNull($pdo->query('SELECT payload FROM queue_submissions')->fetchColumn());
    }

    public function testQueueClaimAcrossConnectionsAndAbandonedWork(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pcm-test-');
        try {
            $one = Connection::sqlite($file);
            (new MigrationManager())->run($one, dirname(__DIR__, 2), ['queue']);
            $first = new DatabaseQueue($one);
            $second = new DatabaseQueue(Connection::sqlite($file));
            $submission = Support::submission();
            $channels = ['google_sheets', 'email_notification'];
            $first->enqueue($submission, 'google_sheets_only', $channels, null, null, null);
            $now = time();
            $claim1 = $first->claim($now, $submission->id);
            $claim2 = $second->claim($now, $submission->id);
            self::assertSame('google_sheets', $claim1['channel']);
            self::assertSame('email_notification', $claim2['channel']);
            self::assertNull($second->claim($now, $submission->id));
            self::assertSame(2, $second->recover($now + 130, 120, 6));
            $attempt = $second->claim($now + 130, $submission->id);
            self::assertSame(2, $attempt['attempts']);
            self::assertFalse($first->finish($claim1, 'completed', null, null, $now + 130));
            self::assertTrue($second->finish($attempt, 'completed', null, null, $now + 130));
            $second->settle($submission->id, true);
            self::assertSame('ambiguous', $second->states($submission->id)['email_notification']);
            self::assertNull($one->query('SELECT payload FROM queue_submissions')->fetchColumn());
        } finally {
            unset($first, $second, $one);
            foreach ([$file, $file . '-wal', $file . '-shm'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function testRateLimitExpiresAndDoesNotStoreIdentities(): void
    {
        $pdo = Connection::sqlite(':memory:');
        (new MigrationManager())->run($pdo, dirname(__DIR__, 2), ['rate_limits']);
        $limiter = new RateLimiter($pdo, str_repeat('k', 64));
        self::assertTrue($limiter->allow('ip', '192.0.2.5', 2, 60, 100));
        self::assertTrue($limiter->allow('ip', '192.0.2.5', 2, 60, 110));
        self::assertFalse($limiter->allow('ip', '192.0.2.5', 2, 60, 120));
        self::assertTrue($limiter->allow('ip', '192.0.2.5', 2, 60, 160));
        self::assertStringNotContainsString('192.0.2.5', $pdo->query('SELECT bucket FROM rate_limits')->fetchColumn());
    }

    public function testMySqlMigrationsAndAdapter(): void
    {
        $name = getenv('CONTACT_TEST_MYSQL_DATABASE');
        if (!$name || !str_ends_with($name, '_test') || !in_array('mysql', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('Provide an isolated MySQL/MariaDB database ending in _test.');
        }
        $pdo = Connection::mysql(Support::config([
            'DB_HOST' => getenv('CONTACT_TEST_MYSQL_HOST') ?: '127.0.0.1',
            'DB_PORT' => getenv('CONTACT_TEST_MYSQL_PORT') ?: '3306',
            'DB_DATABASE' => $name,
            'DB_USERNAME' => getenv('CONTACT_TEST_MYSQL_USER') ?: '',
            'DB_PASSWORD' => getenv('CONTACT_TEST_MYSQL_PASSWORD') ?: '',
        ]));
        (new MigrationManager())->run($pdo, dirname(__DIR__, 2), ['submissions', 'queue']);
        $storage = new MySQLAdapter($pdo);
        $submission = Support::submission();
        try {
            self::assertSame(DeliveryStatus::Completed, $storage->save($submission)->status);
            self::assertSame(DeliveryStatus::Completed, $storage->save($submission)->status);
            self::assertEquals($submission->fields, $storage->find($submission->id)->fields);
        } finally {
            $storage->delete($submission->id);
        }
    }
}
