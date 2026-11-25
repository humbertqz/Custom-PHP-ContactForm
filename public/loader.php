<?php

declare(strict_types=1);

use ContactModule\Http\Endpoint;
use ContactModule\Http\AdminPanel;

if (!isset($contactConfigurationEndpoint) && !isset($contactAdminEndpoint)) {
    http_response_code(404);
    return;
}
ini_set('display_errors', '0');
umask(0077);

$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$configured = getenv('CONTACT_MODULE_ROOT');
$candidates = $configured !== false ? [$configured] : [
    dirname(__DIR__), dirname(__DIR__, 2) . '/contact-module',
];
foreach ($candidates as $candidate) {
    $root = realpath($candidate);
    if (
        $root === false || !$documentRoot || $root === $documentRoot
        || str_starts_with($root . '/', rtrim($documentRoot, '/') . '/')
    ) {
        continue;
    }
    $packageOverride = getenv('CONTACT_MODULE_PACKAGE');
    $package = $packageOverride !== false ? $packageOverride : (
        is_file($root . '/src/Bootstrap.php') ? $root : $root . '/vendor/contact-module/php-contact-module'
    );
    $package = realpath($package);
    if (
        $package === false || $package === $documentRoot
        || str_starts_with($package . '/', rtrim($documentRoot, '/') . '/')
        || !is_file($package . '/src/Bootstrap.php')
    ) {
        continue;
    }
    $autoload = $package . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        $autoload = $root . '/vendor/autoload.php';
    }
    if (!is_file($autoload)) {
        continue;
    }
    require $autoload;
    if (isset($contactAdminEndpoint)) {
        AdminPanel::serve($package, $root);
    } elseif (isset($contactConfigurationEndpoint)) {
        Endpoint::serve($package, $root, $contactConfigurationEndpoint);
    }
    return;
}

http_response_code(500);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo '{"success":false,"message":"No se pudo procesar la solicitud."}';
