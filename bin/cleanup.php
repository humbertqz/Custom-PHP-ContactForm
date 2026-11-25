<?php

declare(strict_types=1);

use ContactModule\Services\CleanupService;

require __DIR__ . '/common.php';

contactCommand(static function (): int {
    $dryRun = in_array('--dry-run', $_SERVER['argv'], true);
    $app = contactApplication();
    $counts = (new CleanupService())->run(
        $app->config,
        $app->database?->pdo,
        $app->queue?->pdo,
        $app->rateDb,
        $dryRun
    );
    contactJson(['dry_run' => $dryRun, 'counts' => $counts]);
    return 0;
});
