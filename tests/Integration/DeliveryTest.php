<?php

declare(strict_types=1);

namespace ContactModuleTests;

use ContactModule\Config\FormRegistry;
use ContactModule\ContactManager;
use ContactModule\Contracts\MailerInterface;
use ContactModule\Contracts\StorageInterface;
use ContactModule\Database\Connection;
use ContactModule\Database\MigrationManager;
use ContactModule\DTO\DeliveryResult;
use ContactModule\DTO\DeliveryStatus;
use ContactModule\Queue\DatabaseQueue;
use ContactModule\Queue\JobProcessor;
use ContactModule\Storage\SQLiteAdapter;
use ContactModule\Storage\StorageManager;
use PHPUnit\Framework\TestCase;

final class DeliveryTest extends TestCase
{
    public function testSuccessMatrix(): void
    {
        foreach (
            [
            ['google_sheets_only', ['google_sheets' => 'completed', 'email_notification' => 'failed'], 200],
            ['google_sheets_only', ['google_sheets' => 'failed', 'email_notification' => 'completed'], 503],
            ['fallback', ['google_sheets' => 'failed', 'database' => 'completed'], 200],
            ['fallback', ['google_sheets' => 'completed', 'database' => 'failed'], 200],
            ['parallel', ['google_sheets' => 'completed', 'database' => 'failed'], 503],
            ['parallel', ['google_sheets' => 'completed', 'database' => 'retry_scheduled'], 202],
            ['parallel', ['google_sheets' => 'failed', 'database' => 'failed'], 503],
            ['database_first', ['database' => 'completed', 'email_notification' => 'pending'], 202],
            ['email_only', ['email_notification' => 'ambiguous'], 503],
            ] as [$mode, $states, $expected]
        ) {
            self::assertSame($expected, StorageManager::status($mode, $states));
        }
    }

    public function testFallbackRetriesOnlySheetsAndDeduplicatesBrowserRequests(): void
    {
        $config = Support::config(['STORAGE_MODE' => 'fallback']);
        $form = (new FormRegistry($config))->get('contact');
        $pdo = Connection::sqlite(':memory:');
        (new MigrationManager())->run($pdo, $config->package, ['submissions', 'queue']);
        $database = new SQLiteAdapter($pdo);
        $queue = new DatabaseQueue($pdo);
        $google = $this->createMock(StorageInterface::class);
        $google->expects(self::exactly(2))->method('save')->willReturnOnConsecutiveCalls(
            new DeliveryResult(DeliveryStatus::Ambiguous, 'SHEETS_TRANSPORT'),
            DeliveryResult::completed()
        );
        $smtp = $this->createMock(MailerInterface::class);
        $smtp->expects(self::once())->method('send')->willReturn(DeliveryResult::completed());
        $logger = Support::logger();
        $storage = new StorageManager('fallback', $database, $google, $smtp);
        $worker = new JobProcessor($config, $queue, $storage, $database, new FormRegistry($config), $logger);
        $manager = new ContactManager($config, $storage, $database, $queue, $worker, $logger);
        $submission = Support::submission();
        $key = 'repeated-request-0AA0000';
        self::assertSame([202, $submission->id], $manager->process($submission, $form, false, $key));
        self::assertNotNull($database->find($submission->id));
        self::assertSame([202, $submission->id], $manager->process(Support::submission(), $form, false, $key));
        self::assertSame([409, $submission->id], $manager->process(Support::submission('x'), $form, false, $key));
        self::assertSame(0, $worker->run(10));
        $pdo->exec("UPDATE deliveries SET next_retry_at = 0 WHERE status = 'retry_scheduled'");
        self::assertSame(1, $worker->run(10));
        self::assertSame([
            'database' => 'completed', 'email_notification' => 'completed', 'google_sheets' => 'completed',
        ], $queue->states($submission->id));
        self::assertNull($pdo->query('SELECT payload FROM queue_submissions')->fetchColumn());
    }

    public function testNoQueueMakesAnEmailOnlyFailureVisible(): void
    {
        $config = Support::config(['STORAGE_MODE' => 'email_only']);
        $smtp = $this->createStub(MailerInterface::class);
        $smtp->method('send')->willReturn(new DeliveryResult(DeliveryStatus::Temporary, 'TEMP'));
        $storage = new StorageManager('email_only', null, null, $smtp);
        $manager = new ContactManager($config, $storage, null, null, null, Support::logger());
        $submission = Support::submission();
        self::assertSame([503, $submission->id], $manager->process($submission, [], false, null));
    }
}
