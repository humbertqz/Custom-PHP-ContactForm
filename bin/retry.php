<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

contactCommand(static function (): int {
    $options = getopt('', ['limit:', 'dry-run']);
    $limit = filter_var($options['limit'] ?? 50, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 1000],
    ]);
    if ($limit === false) {
        fwrite(STDERR, "Usa --limit entre 1 y 1000.\n");
        return 1;
    }
    $app = contactApplication(false);
    $app->runtime();
    if ($app->worker === null) {
        contactJson(['queue' => 'disabled']);
        return 0;
    }
    if (isset($options['dry-run'])) {
        contactJson(['queue_counts' => $app->queue?->counts(), 'limit' => $limit]);
    } else {
        contactJson(['processed' => $app->worker->run($limit)]);
    }
    return 0;
});
