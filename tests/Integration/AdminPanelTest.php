<?php

declare(strict_types=1);

namespace ContactModuleTests;

use ContactModule\Database\Connection;
use ContactModule\DTO\DeliveryStatus;
use ContactModule\DTO\Submission;
use ContactModule\Storage\SQLiteAdapter;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

final class AdminPanelTest extends TestCase
{
    public function testLoginShowsEscapedMessagesAndLogoutRevokesAccess(): void
    {
        if (!class_exists(Dotenv::class) || !function_exists('proc_open')) {
            self::markTestSkipped('Composer dependencies and proc_open are required.');
        }
        $root = sys_get_temp_dir() . '/contact-admin-test-' . bin2hex(random_bytes(12));
        mkdir($root, 0700);
        mkdir("$root/sessions", 0700);
        $password = 'synthetic-login-password';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        $env = "APP_ENV=local\nAPP_URL=http://localhost\nTURNSTILE_ENABLED=false\nRATE_LIMIT_KEY="
            . str_repeat('a', 64);
        $admin = "ADMIN_ENABLED=true\nADMIN_USERNAME=operator\nADMIN_PASSWORD_HASH='$hash'\n";
        file_put_contents("$root/.env", $env . PHP_EOL);
        try {
            $disabled = $this->request($root, []);
            self::assertSame(404, $disabled['status']);
            $php = dirname(__DIR__, 2) . '/bin/install.php';
            [$code, $output] = $this->command([PHP_BINARY, $php, '--non-interactive'], '', [
                'CONTACT_MODULE_ROOT' => $root,
            ]);
            self::assertSame(0, $code, $output);
            $pdo = Connection::sqlite("$root/storage/database/contacts.sqlite");
            $storage = new SQLiteAdapter($pdo);
            $submission = new Submission(
                '6f692e5b-7a58-459b-b8e4-27d28bf431f3',
                'contact',
                '2026-11-25T00:00:00Z',
                ['name' => 'QA', 'message' => '<img src=x onerror=alert(1)>', 'utm' => 'utm_campaign=noviembre']
            );
            self::assertSame(DeliveryStatus::Completed, $storage->save($submission)->status);
            unset($storage, $pdo);
            file_put_contents("$root/.env", $env . PHP_EOL . $admin);

            $login = $this->request($root, []);
            self::assertSame(200, $login['status']);
            self::assertStringContainsString('Iniciar sesión', $login['body']);
            self::assertStringNotContainsString('utm_campaign=noviembre', $login['body']);
            $post = ['method' => 'POST', 'session' => $login['session'], 'body' => [
                'action' => 'login', 'username' => 'operator', 'password' => $password, 'csrf' => 'invalid',
            ]];
            self::assertSame(403, $this->request($root, $post)['status']);
            $post['body']['csrf'] = $this->token($login['body']);
            $post['body']['password'] = 'wrong-password';
            self::assertSame(401, $this->request($root, $post)['status']);

            $post['body']['password'] = $password;
            $auth = $this->request($root, $post);
            self::assertSame(303, $auth['status']);
            self::assertNotSame($login['session'], $auth['session']);
            $messages = $this->request($root, ['session' => $auth['session']]);
            self::assertSame(200, $messages['status']);
            self::assertStringContainsString('utm_campaign=noviembre', $messages['body']);
            self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $messages['body']);
            self::assertStringNotContainsString('<img src=x', $messages['body']);

            $logout = $this->request($root, ['method' => 'POST', 'session' => $auth['session'], 'body' => [
                'action' => 'logout', 'csrf' => $this->token($messages['body']),
            ]]);
            self::assertSame(303, $logout['status']);
            $afterLogout = $this->request($root, ['session' => $auth['session']]);
            self::assertStringContainsString('Iniciar sesión', $afterLogout['body']);
            self::assertStringNotContainsString('utm_campaign=noviembre', $afterLogout['body']);

            $post['session'] = $afterLogout['session'];
            $post['body']['csrf'] = $this->token($afterLogout['body']);
            $post['body']['password'] = 'wrong-password';
            for ($attempt = 0; $attempt < 8; $attempt++) {
                self::assertSame(401, $this->request($root, $post)['status']);
            }
            self::assertSame(429, $this->request($root, $post)['status']);
        } finally {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }

    private function token(string $page): string
    {
        self::assertSame(1, preg_match('/name="csrf" value="([a-f0-9]{64})"/', $page, $match));
        return $match[1];
    }

    /** @param array<string,mixed> $scenario @return array{status:int,body:string,session:string} */
    private function request(string $root, array $scenario): array
    {
        $fixture = dirname(__DIR__) . '/Fixtures/admin-endpoint.php';
        [$code, $output] = $this->command([PHP_BINARY, $fixture, $root], json_encode($scenario, JSON_THROW_ON_ERROR));
        self::assertSame(0, $code, $output);
        return json_decode($output, true, 8, JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $command @param array<string,string> $env @return array{int,string} */
    private function command(array $command, string $input, ?array $env = null): array
    {
        $child = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, $env);
        self::assertIsResource($child);
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($child);
        self::assertSame('', $errors);
        return [$code, $output];
    }
}
