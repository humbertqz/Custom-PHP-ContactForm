<?php

declare(strict_types=1);

namespace ContactModule\Storage;

use ContactModule\Contracts\StorageInterface;
use ContactModule\DTO\DeliveryResult;
use ContactModule\DTO\DeliveryStatus;
use ContactModule\DTO\Submission;
use ContactModule\Support\HttpClient;

final class GoogleSheetsAdapter implements StorageInterface
{
    public function __construct(
        private HttpClient $http,
        private string $url,
        private string $token,
        private int $timeout,
    ) {
        if (!preg_match('~\Ahttps://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec\z~D', $url)) {
            throw new \InvalidArgumentException('Untrusted Apps Script URL.');
        }
    }

    public function getName(): string
    {
        return 'google_sheets';
    }

    public function save(Submission $submission): DeliveryResult
    {
        try {
            $payload = json_encode([
                'token' => $this->token,
                'submission' => [
                    'id' => $submission->id,
                    'form_id' => $submission->formId,
                    'created_at' => $submission->createdAt,
                    'fields' => (object) $submission->fields,
                    'source_url' => $submission->sourceUrl,
                    'route' => $submission->sheetsRoute ?: $submission->formId,
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (strlen($payload) > 131072) {
                return new DeliveryResult(DeliveryStatus::Failed, 'SHEETS_SIZE');
            }
            $started = hrtime(true);
            $response = $this->http->request('POST', $this->url, $payload, $this->timeout);
            if (in_array($response['status'], [302, 303], true)) {
                $location = $response['location'] ?? '';
                $redirect = '~\Ahttps://script\.googleusercontent\.com/macros/echo\?[^\x00-\x20\x7F#]+\z~D';
                if (!preg_match($redirect, $location)) {
                    return new DeliveryResult(DeliveryStatus::Ambiguous, 'SHEETS_REDIRECT');
                }
                $remaining = $this->timeout - (int) ((hrtime(true) - $started) / 1e9);
                if ($remaining < 1) {
                    return new DeliveryResult(DeliveryStatus::Ambiguous, 'SHEETS_TIMEOUT');
                }
                $response = $this->http->request('GET', $location, null, $remaining, []);
            }
        } catch (\RuntimeException | \JsonException) {
            return new DeliveryResult(DeliveryStatus::Ambiguous, 'SHEETS_TRANSPORT');
        }

        $status = $response['status'];
        if ($status === 429 || $status >= 500) {
            return new DeliveryResult(DeliveryStatus::Ambiguous, 'SHEETS_HTTP_RETRY');
        }
        if (in_array($status, [401, 403, 404, 410], true)) {
            return new DeliveryResult(DeliveryStatus::Failed, 'SHEETS_ACCESS');
        }
        $data = json_decode($response['body'], true, 8);
        if (
            $status === 200 && is_array($data) && ($data['success'] ?? null) === true
            && ($data['submission_id'] ?? '') === $submission->id
            && in_array($data['status'] ?? '', ['stored', 'already_stored'], true)
        ) {
            return DeliveryResult::completed();
        }
        if (is_array($data) && ($data['success'] ?? null) === false && is_bool($data['retryable'] ?? null)) {
            $code = $data['code'] ?? '';
            if (in_array($code, ['UNAUTHORIZED', 'INVALID_REQUEST', 'CONFIGURATION', 'BUSY', 'TEMPORARY'], true)) {
                return new DeliveryResult(
                    $data['retryable'] ? DeliveryStatus::Temporary : DeliveryStatus::Failed,
                    'SHEETS_' . $code
                );
            }
        }
        return new DeliveryResult(DeliveryStatus::Ambiguous, 'SHEETS_RESPONSE');
    }
}
