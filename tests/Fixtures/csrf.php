<?php

declare(strict_types=1);

use ContactModule\Security\CsrfProtection;

require dirname(__DIR__) . '/bootstrap.php';

ini_set('session.save_path', $argv[1]);
$csrf = new CsrfProtection(false, '/contact');
$token = $csrf->issue();
$_COOKIE['PCM_SESSION'] = session_id();
echo json_encode([$csrf->verify($token), $csrf->verify(str_repeat('0', 64))], JSON_THROW_ON_ERROR);
