<?php

declare(strict_types=1);

require __DIR__ . '/common.php';

contactCommand(static function (): int {
    contactApplication()->migrate();
    echo "Migraciones al día.\n";
    return 0;
});
