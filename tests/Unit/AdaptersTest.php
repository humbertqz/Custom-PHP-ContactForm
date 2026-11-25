<?php

declare(strict_types=1);

namespace ContactModuleTests;

use ContactModule\DTO\DeliveryStatus;
use ContactModule\Security\TurnstileAdapter;
use ContactModule\Storage\GoogleSheetsAdapter;
use ContactModule\Support\HttpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdaptersTest extends TestCase
{
    public function testSheetsRedirectUsesGetWithoutCredentialsAndValidatesDuplicate(): void
    {
        $submission = Support::submission();
        $url = 'https://script.google.com/macros/s/test/exec';
        $redirect = 'https://script.googleusercontent.com/macros/echo?user_content_key=test';
        $transport = $this->createMock(HttpClient::class);
        $transport->expects(self::exactly(2))->method('request')->willReturnCallback(
            static function (
                string $method,
                string $target,
                ?string $body,
                int $timeout,
                array $headers = []
            ) use (
                $submission,
                $url,
                $redirect
            ): array {
                if ($method === 'POST') {
                    self::assertSame($url, $target);
                    self::assertSame('test-token', json_decode($body, true)['token']);
                    return ['status' => 302, 'location' => $redirect, 'body' => ''];
                }
                self::assertSame('GET', $method);
                self::assertSame($redirect, $target);
                self::assertNull($body);
                self::assertSame([], $headers);
                return ['status' => 200, 'location' => null, 'body' => json_encode([
                    'success' => true, 'submission_id' => $submission->id, 'status' => 'already_stored',
                ])];
            }
        );
        $adapter = new GoogleSheetsAdapter($transport, $url, 'test-token', 2);
        self::assertSame(DeliveryStatus::Completed, $adapter->save($submission)->status);
    }

    public function testSheetsRejectsUntrustedRedirectAndReportsTimeoutAsAmbiguous(): void
    {
        $transport = $this->createMock(HttpClient::class);
        $transport->expects(self::once())->method('request')->willReturn([
            'status' => 302, 'body' => '',
            'location' => 'https://script.googleusercontent.com.example.com/macros/echo?x=1',
        ]);
        $url = 'https://script.google.com/macros/s/test/exec';
        $adapter = new GoogleSheetsAdapter($transport, $url, 'never-replayed', 2);
        self::assertSame('SHEETS_REDIRECT', $adapter->save(Support::submission())->code);
        $transport = $this->createStub(HttpClient::class);
        $transport->method('request')->willThrowException(new RuntimeException('timeout after write'));
        $adapter = new GoogleSheetsAdapter($transport, $url, 'never-replayed', 2);
        self::assertSame(DeliveryStatus::Ambiguous, $adapter->save(Support::submission())->status);
    }

    public function testSheetsRequiresExactMatchingUuid(): void
    {
        $transport = $this->createStub(HttpClient::class);
        $transport->method('request')->willReturn([
            'status' => 200, 'location' => null,
            'body' => '{"success":true,"status":"stored","submission_id":"other"}',
        ]);
        $adapter = new GoogleSheetsAdapter($transport, 'https://script.google.com/macros/s/test/exec', 'token', 3);
        self::assertSame(DeliveryStatus::Ambiguous, $adapter->save(Support::submission())->status);
    }

    public function testTurnstileMatchesFixedHostnameAndActionAndRejectsReplay(): void
    {
        foreach (
            [
            [['success' => true, 'hostname' => 'a.example.com', 'action' => 'contact'], true],
            [['success' => true, 'hostname' => 'b.example.com', 'action' => 'contact'], false],
            [['success' => true, 'hostname' => 'a.example.com', 'action' => 'other'], false],
            [['success' => false, 'error-codes' => ['timeout-or-duplicate']], false],
            ] as [$body, $expected]
        ) {
            $http = $this->createStub(HttpClient::class);
            $http->method('request')->willReturn(['status' => 200, 'location' => null, 'body' => json_encode($body)]);
            $adapter = new TurnstileAdapter($http, 'secret', ['a.example.com'], 3);
            self::assertSame($expected, $adapter->verify('once', 'contact', null));
        }
    }

    public function testTurnstileOutageFailsClosed(): void
    {
        $http = $this->createStub(HttpClient::class);
        $http->method('request')->willReturn(['status' => 503, 'location' => null, 'body' => '']);
        $adapter = new TurnstileAdapter($http, 'secret', ['a.example.com'], 3);
        $this->expectException(RuntimeException::class);
        $adapter->verify('once', 'contact', null);
    }
}
