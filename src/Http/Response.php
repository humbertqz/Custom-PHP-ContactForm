<?php

declare(strict_types=1);

namespace ContactModule\Http;

final readonly class Response
{
    /** @param array<string,mixed> $body @param array<string,string> $headers */
    public function __construct(public int $status, public array $body = [], public array $headers = [])
    {
    }

    /** @param array<string,string> $extra */
    public function send(array $extra = []): void
    {
        http_response_code($this->status);
        foreach (
            $extra + $this->headers + [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            ] as $key => $value
        ) {
            header("$key: $value");
        }
        if ($this->status !== 204) {
            echo json_encode($this->body, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        }
    }
}
