<?php

declare(strict_types=1);

use ContactModule\Database\Connection;
use ContactModule\Queue\DatabaseQueue;

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Connection::sqlite($argv[1]);
$queue = new DatabaseQueue($pdo);
echo "ready\n";
fgets(STDIN);
$job = $queue->claim(time(), $argv[2]);
echo $job === null ? "busy\n" : "claimed\n";
