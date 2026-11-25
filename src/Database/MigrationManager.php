<?php

declare(strict_types=1);

namespace ContactModule\Database;

use PDO;
use Throwable;

final class MigrationManager
{
    /** @param list<string> $components */
    public function run(PDO $pdo, string $root, array $components): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sqlite = $driver === 'sqlite';
        $lock = null;
        if ($sqlite) {
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('BEGIN IMMEDIATE');
        } else {
            $lock = 'pcm:' . substr(hash('sha256', (string) $pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
            if ((int) $pdo->query("SELECT GET_LOCK('$lock', 10)")->fetchColumn() !== 1) {
                throw new \RuntimeException('Migration lock unavailable.');
            }
        }
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
                name VARCHAR(100) PRIMARY KEY, applied_at BIGINT NOT NULL
            )');
            foreach (glob("$root/database/migrations/$driver/*.sql") ?: [] as $file) {
                $name = basename($file, '.sql');
                if (!in_array(substr($name, 4), $components, true)) {
                    continue;
                }
                $check = $pdo->prepare('SELECT 1 FROM migrations WHERE name = ?');
                $check->execute([$name]);
                if ($check->fetchColumn()) {
                    continue;
                }
                foreach (explode(';', (string) file_get_contents($file)) as $sql) {
                    if (trim($sql) !== '') {
                        $pdo->exec($sql);
                    }
                }
                $pdo->prepare('INSERT INTO migrations (name, applied_at) VALUES (?, ?)')->execute([$name, time()]);
            }
            if ($sqlite) {
                $pdo->exec('COMMIT');
            }
        } catch (Throwable $failure) {
            if ($sqlite) {
                $pdo->exec('ROLLBACK');
            }
            throw $failure;
        } finally {
            if ($lock !== null) {
                $pdo->query("SELECT RELEASE_LOCK('$lock')")->closeCursor();
            }
        }
    }
}
