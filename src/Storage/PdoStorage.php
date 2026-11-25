<?php

declare(strict_types=1);

namespace ContactModule\Storage;

use ContactModule\Contracts\StorageInterface;
use ContactModule\DTO\DeliveryResult;
use ContactModule\DTO\DeliveryStatus;
use ContactModule\DTO\Submission;
use PDO;
use PDOException;

abstract class PdoStorage implements StorageInterface
{
    public function __construct(public readonly PDO $pdo)
    {
    }

    public function getName(): string
    {
        return 'database';
    }

    abstract protected function duplicateClause(): string;

    public function save(Submission $submission): DeliveryResult
    {
        $primary = ['name', 'email', 'phone', 'subject', 'message'];
        // Preserve typed custom fields when an installation reuses a standard name.
        $additional = array_filter(
            $submission->fields,
            static fn (mixed $value, string $key): bool => !in_array($key, $primary, true)
                || !is_string($value) || $value === '',
            ARRAY_FILTER_USE_BOTH
        );
        $sql = 'INSERT INTO submissions
            (uuid, form_id, name, email, phone, subject, message, extra_fields,
                sheets_route, source_url, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ' . $this->duplicateClause();
        try {
            $this->pdo->prepare($sql)->execute([
                $submission->id, $submission->formId,
                ...array_map($submission->field(...), $primary),
                json_encode($additional, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                $submission->sheetsRoute, $submission->sourceUrl, 'stored', $submission->createdAt,
                gmdate('Y-m-d\TH:i:s\Z'),
            ]);
            return DeliveryResult::completed();
        } catch (PDOException $exception) {
            $state = (string) $exception->getCode();
            $status = in_array(substr($state, 0, 2), ['22', '23', '42'], true)
                ? DeliveryStatus::Failed : DeliveryStatus::Temporary;
            return new DeliveryResult($status, 'DATABASE_WRITE');
        }
    }

    public function find(string $uuid): ?Submission
    {
        $query = $this->pdo->prepare('SELECT * FROM submissions WHERE uuid = ?');
        $query->execute([$uuid]);
        $row = $query->fetch();
        if ($row === false) {
            return null;
        }
        $fields = json_decode($row['extra_fields'], true, 16, JSON_THROW_ON_ERROR);
        foreach (['name', 'email', 'phone', 'subject', 'message'] as $column) {
            if ($row[$column] !== '' && !array_key_exists($column, $fields)) {
                $fields[$column] = $row[$column];
            }
        }
        return new Submission(
            $row['uuid'],
            $row['form_id'],
            $row['created_at'],
            $fields,
            $row['source_url'],
            $row['sheets_route']
        );
    }

    public function delete(string $uuid): void
    {
        $this->pdo->prepare('DELETE FROM submissions WHERE uuid = ?')->execute([$uuid]);
    }
}
