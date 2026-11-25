<?php

declare(strict_types=1);

namespace ContactModule\DTO;

final readonly class Submission
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public string $id,
        public string $formId,
        public string $createdAt,
        public array $fields,
        public string $sourceUrl = '',
        public string $sheetsRoute = '',
    ) {
    }

    public function encode(): string
    {
        return json_encode(get_object_vars($this), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public static function decode(string $payload): self
    {
        $data = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
        return new self(
            $data['id'],
            $data['formId'],
            $data['createdAt'],
            $data['fields'],
            $data['sourceUrl'],
            $data['sheetsRoute'] ?? '',
        );
    }

    public function field(string $key): string
    {
        $value = $this->fields[$key] ?? '';
        return is_scalar($value) ? (string) $value : '';
    }
}
