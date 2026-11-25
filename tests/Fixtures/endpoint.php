<?php

declare(strict_types=1);

$package = dirname(__DIR__, 2);
$root = $argv[1];
$scenario = json_decode($argv[2], true, 8, JSON_THROW_ON_ERROR);
putenv('CONTACT_MODULE_ROOT=' . $root);
putenv('CONTACT_MODULE_PACKAGE=' . $package);
$_SERVER = array_replace([
    'DOCUMENT_ROOT' => $scenario['document_root'] ?? $package . '/public',
    'REQUEST_METHOD' => 'POST',
    'CONTENT_TYPE' => 'multipart/form-data',
    'CONTENT_LENGTH' => '200',
    'REMOTE_ADDR' => '192.0.2.1',
], $scenario['server'] ?? []);
$_POST = $scenario['body'] ?? [];
$_GET = $scenario['query'] ?? [];
$_FILES = $scenario['files'] ?? [];
register_shutdown_function(static function (): void {
    echo "\n", http_response_code() ?: 200;
});
require $package . '/public/' . ($scenario['entry'] ?? 'contact.php');
