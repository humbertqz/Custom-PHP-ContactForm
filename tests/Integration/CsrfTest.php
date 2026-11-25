<?php

declare(strict_types=1);

namespace ContactModuleTests;

use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function testSessionTokenAndIncorrectToken(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open unavailable.');
        }
        $root = sys_get_temp_dir() . '/pcm-session-' . bin2hex(random_bytes(12));
        mkdir($root, 0700);
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Fixtures/csrf.php', $root],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        try {
            self::assertIsResource($process);
            fclose($pipes[0]);
            $result = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors);
            self::assertSame([true, false], json_decode($result, true, 8, JSON_THROW_ON_ERROR));
        } finally {
            foreach (glob($root . '/sess_*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($root);
        }
    }
}
