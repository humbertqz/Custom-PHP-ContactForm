<?php

declare(strict_types=1);

namespace ContactModuleTests;

use ContactModule\Security\Honeypot;
use ContactModule\Security\TrustedProxyResolver;
use ContactModule\Validation\FormValidator;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testAllowedValuesAndNormalization(): void
    {
        $validation = (new FormValidator())->validate([
            'email' => ' sample@example.com ',
            'amount' => '12.5',
            'date' => '2024-02-29',
            'topics' => ['b', 'a', 'b'],
            'terms' => 'on',
            'message' => "<p>hola</p>\r\nAdiós",
        ], [
            'email' => ['type' => 'email'],
            'amount' => ['type' => 'number', 'min' => 10, 'max' => 13],
            'date' => ['type' => 'date'],
            'topics' => ['type' => 'checkbox_group', 'options' => ['a', 'b']],
            'terms' => ['type' => 'checkbox'],
            'message' => ['type' => 'textarea'],
            'source' => ['type' => 'hidden', 'default' => 'site'],
        ]);
        self::assertSame([], $validation->errors);
        self::assertSame(['b', 'a'], $validation->fields['topics']);
        self::assertSame(12.5, $validation->fields['amount']);
        self::assertSame('sample@example.com', $validation->fields['email']);
        self::assertSame("<p>hola</p>\nAdiós", $validation->fields['message']);
        self::assertSame(true, $validation->fields['terms']);
        self::assertSame('site', $validation->fields['source']);
    }

    public function testMalformedValuesAndUnknownRoutingAreRejected(): void
    {
        $validation = (new FormValidator())->validate([
            'email' => ["name@example.com", "other@example.com"],
            'date' => '2023-02-29',
            'to' => 'example@example.com',
            'url' => 'file:///private',
            'choice' => 'outside',
        ], [
            'email' => ['type' => 'email'],
            'date' => ['type' => 'date'],
            'url' => ['type' => 'url'],
            'choice' => ['type' => 'select', 'options' => ['inside']],
        ]);
        self::assertSame(
            ['form_id' => 'unknown', 'email' => 'email', 'date' => 'field', 'url' => 'field', 'choice' => 'field'],
            $validation->errors
        );
        self::assertSame([], $validation->fields);
    }

    public function testHoneypotAndTrustedPeerChain(): void
    {
        self::assertFalse(Honeypot::triggered(null));
        self::assertFalse(Honeypot::triggered(''));
        self::assertTrue(Honeypot::triggered(['']));
        $resolver = new TrustedProxyResolver(['10.0.0.0/24', '2001:db8::/64']);
        self::assertSame('203.0.113.1', $resolver->resolve('203.0.113.1', '10.10.10.10'));
        self::assertSame('203.0.113.1', $resolver->resolve('10.0.0.1', '198.51.100.1, 203.0.113.1, 10.0.0.2'));
        self::assertNull($resolver->resolve('2001:db8::1', 'malformed'));
    }
}
