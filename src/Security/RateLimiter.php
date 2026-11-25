<?php

declare(strict_types=1);

namespace ContactModule\Security;

use PDO;

final class RateLimiter
{
    public function __construct(private PDO $pdo, private string $key)
    {
    }

    public function allow(string $scope, string $identity, int $max, int $window, ?int $now = null): bool
    {
        $now ??= time();
        $bucket = hash_hmac('sha256', $scope . "\0" . $identity, $this->key);
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            return $this->hitMysql($bucket, $now, $window) <= $max;
        }
        $sql = 'INSERT INTO rate_limits (bucket, hits, expires_at) VALUES (?, 1, ?)
            ON CONFLICT (bucket) DO UPDATE SET
            hits = CASE WHEN expires_at <= ? THEN 1 ELSE MIN(hits + 1, 2147483647) END,
            expires_at = CASE WHEN expires_at <= ? THEN excluded.expires_at ELSE expires_at END
            RETURNING hits';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([$bucket, $now + $window, $now, $now]);
        $hits = (int) $statement->fetchColumn();
        $statement->closeCursor();
        return $hits <= $max;
    }

    private function hitMysql(string $bucket, int $now, int $window): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO rate_limits (bucket, hits, expires_at) VALUES (?, 1, ?)
                ON DUPLICATE KEY UPDATE
                    hits = IF(expires_at <= ?, 1, LEAST(hits + 1, 2147483647)),
                    expires_at = IF(expires_at <= ?, ?, expires_at)')
                ->execute([$bucket, $now + $window, $now, $now, $now + $window]);
            $read = $this->pdo->prepare('SELECT hits FROM rate_limits WHERE bucket = ?');
            $read->execute([$bucket]);
            $hits = (int) $read->fetchColumn();
            $read->closeCursor();
            $this->pdo->commit();
            return $hits;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
