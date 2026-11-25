<?php

declare(strict_types=1);

namespace ContactModule\Security;

use ContactModule\Contracts\CaptchaInterface;
use ContactModule\Support\HttpClient;
use RuntimeException;

final class TurnstileAdapter implements CaptchaInterface
{
    /** @param list<string> $hostnames */
    public function __construct(
        private HttpClient $http,
        private string $secret,
        private array $hostnames,
        private int $timeout,
    ) {
    }

    public function verify(string $token, string $action, ?string $ip): bool
    {
        if ($token === '' || strlen($token) > 2048) {
            return false;
        }
        $body = ['secret' => $this->secret, 'response' => $token];
        if ($ip !== null) {
            $body['remoteip'] = $ip;
        }
        $response = $this->http->request(
            'POST',
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            json_encode($body, JSON_THROW_ON_ERROR),
            $this->timeout
        );
        $data = json_decode($response['body'], true, 8);
        if ($response['status'] !== 200 || !is_array($data) || !is_bool($data['success'] ?? null)) {
            throw new RuntimeException('TURNSTILE_UNAVAILABLE');
        }
        if (!$data['success']) {
            $errors = $data['error-codes'] ?? [];
            $unavailable = ['internal-error', 'invalid-input-secret', 'missing-input-secret'];
            if (is_array($errors) && array_intersect($errors, $unavailable)) {
                throw new RuntimeException('TURNSTILE_UNAVAILABLE');
            }
            return false;
        }
        return is_string($data['hostname'] ?? null)
            && in_array(strtolower($data['hostname']), array_map('strtolower', $this->hostnames), true)
            && ($data['action'] ?? null) === $action;
    }
}
