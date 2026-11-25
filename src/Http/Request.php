<?php

declare(strict_types=1);

namespace ContactModule\Http;

final readonly class Request
{
    /** @param array<string,mixed> $server @param array<string,mixed> $query */
    public function __construct(public array $server, public array $query)
    {
    }

    public function value(string $key): string
    {
        return is_string($this->server[$key] ?? null) ? $this->server[$key] : '';
    }

    /** @return array<string,mixed> */
    public function body(int $maximum): array
    {
        $length = $this->value('CONTENT_LENGTH');
        if ($length !== '' && (!ctype_digit($length) || (int) $length > $maximum)) {
            throw new HttpException(413, 'size');
        }
        $type = strtolower(trim(explode(';', $this->value('CONTENT_TYPE'), 2)[0]));
        if ($type === 'multipart/form-data') {
            if ($length === '') {
                throw new HttpException(400, 'bad_request');
            }
            if ($_FILES !== [] || $_POST === []) {
                throw new HttpException(400, 'bad_request');
            }
            return $_POST;
        }
        if (!in_array($type, ['application/json', 'application/x-www-form-urlencoded'], true)) {
            throw new HttpException(415, 'format');
        }
        $body = file_get_contents('php://input', false, null, 0, $maximum + 1);
        if ($body === false || $body === '') {
            throw new HttpException(400, 'bad_request');
        }
        if (strlen($body) > $maximum) {
            throw new HttpException(413, 'size');
        }
        if ($type === 'application/json') {
            try {
                $result = json_decode($body, false, 12, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new HttpException(400, 'bad_request');
            }
            if (!$result instanceof \stdClass) {
                throw new HttpException(400, 'bad_request');
            }
            return (array) $result;
        }
        return $this->decodeForm($body);
    }

    /** @return array<string,mixed> */
    private function decodeForm(string $body): array
    {
        $data = [];
        $pairs = explode('&', $body, 2049);
        if (count($pairs) > 2048) {
            throw new HttpException(400, 'bad_request');
        }
        foreach ($pairs as $pair) {
            if (!str_contains($pair, '=') || preg_match('/%(?![0-9a-f]{2})/i', $pair)) {
                throw new HttpException(400, 'bad_request');
            }
            [$raw, $value] = array_map('urldecode', explode('=', $pair, 2));
            $key = str_ends_with($raw, '[]') ? substr($raw, 0, -2) : $raw;
            if (!preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/D', $key)) {
                throw new HttpException(400, 'bad_request');
            }
            if (array_key_exists($key, $data)) {
                $data[$key] = [...(array) $data[$key], $value];
            } else {
                $data[$key] = $raw === $key ? $value : [$value];
            }
        }
        return $data;
    }
}
