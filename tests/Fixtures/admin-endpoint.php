<?php

declare(strict_types=1);

$root = $argv[1];
$package = dirname(__DIR__, 2);
$scenario = json_decode(stream_get_contents(STDIN), true, 8, JSON_THROW_ON_ERROR);
putenv('CONTACT_MODULE_ROOT=' . $root);
putenv('CONTACT_MODULE_PACKAGE=' . $package);
ini_set('session.save_path', $root . '/sessions');
$_SERVER = [
    'DOCUMENT_ROOT' => $package . '/public',
    'REQUEST_METHOD' => $scenario['method'] ?? 'GET',
    'CONTENT_LENGTH' => '256',
    'REMOTE_ADDR' => '127.0.0.1',
];
$_GET = [];
$_POST = $scenario['body'] ?? [];
$_COOKIE = isset($scenario['session']) ? ['PCM_ADMIN' => $scenario['session']] : [];
ob_start();
require $package . '/public/admin.php';
$body = ob_get_clean();
echo json_encode([
    'status' => http_response_code() ?: 200, 'body' => $body, 'session' => session_id(),
], JSON_THROW_ON_ERROR);
