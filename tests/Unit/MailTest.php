<?php

declare(strict_types=1);

namespace ContactModuleTests;

use ContactModule\DTO\DeliveryStatus;
use ContactModule\Services\MailService;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

final class MailTest extends TestCase
{
    public function testUsesAuthorizedSenderAndEscapesNotification(): void
    {
        if (!class_exists(PHPMailer::class)) {
            self::markTestSkipped('PHPMailer unavailable.');
        }
        $mock = $this->getMockBuilder(PHPMailer::class)->onlyMethods(['postSend'])->getMock();
        $mock->expects(self::once())->method('postSend')->willReturn(true);
        $config = Support::config([
            'MAIL_HOST' => 'smtp.example.com', 'MAIL_FROM_ADDRESS' => 'from@example.com',
            'MAIL_TO_ADDRESS' => 'inbox@example.com', 'MAIL_USERNAME' => 'user', 'MAIL_PASSWORD' => 'dummy',
        ]);
        $service = new MailService($config, static fn (): PHPMailer => $mock);
        $message = "<script>alert(1)</script>\nSegunda línea.";
        $submission = Support::submission($message);
        $form = ['name' => 'Cotización', 'fields' => ['topics' => ['label' => 'Intereses <seleccionados>']]];
        $result = $service->send($submission, $form, false);
        self::assertSame(DeliveryStatus::Completed, $result->status);
        self::assertSame('from@example.com', $mock->From);
        self::assertSame('inbox@example.com', $mock->getToAddresses()[0][0]);
        self::assertStringContainsString('Reply-To: test@example.com', $mock->getSentMIMEMessage());
        self::assertStringContainsString('&lt;script&gt;', $mock->Body);
        self::assertStringNotContainsString('<script>', $mock->Body);
        self::assertStringContainsString('&lt;/script&gt;<br>', $mock->Body);
        self::assertStringContainsString('Cotización', $mock->Body);
        self::assertStringContainsString('Intereses &lt;seleccionados&gt;', $mock->Body);
        self::assertStringContainsString('Campos adicionales', $mock->Body);
        self::assertStringContainsString($submission->id, $mock->Body);
        $text = str_replace("\r\n", "\n", $mock->AltBody);
        self::assertStringContainsString("Mensaje\n$message", $text);
        self::assertStringContainsString('Intereses <seleccionados>: a, b', $text);
        self::assertStringContainsString('Content-Type: multipart/alternative', $mock->getSentMIMEMessage());
    }
}
