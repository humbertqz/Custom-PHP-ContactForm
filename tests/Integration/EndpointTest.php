<?php

declare(strict_types=1);

namespace ContactModuleTests;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

final class EndpointTest extends TestCase
{
    public function testPublicLoaderRejectsAnInstanceUnderTheDocumentRoot(): void
    {
        $root = dirname(__DIR__, 2);
        [$status, $response] = $this->request($root, ['document_root' => $root]);
        self::assertSame(500, $status);
        self::assertSame(['success' => false, 'message' => 'No se pudo procesar la solicitud.'], $response);
    }

    public function testEndpointsWithLocalSqliteAndPrivateConfiguration(): void
    {
        if (!class_exists(Dotenv::class)) {
            self::markTestSkipped('Composer runtime dependencies unavailable.');
        }
        $root = sys_get_temp_dir() . '/pcm-endpoint-test-' . bin2hex(random_bytes(12));
        mkdir($root, 0700);
        $previous = getenv('CONTACT_MODULE_ROOT');
        try {
            $key = str_repeat('p', 64);
            $env = "TURNSTILE_ENABLED=false\nQUEUE_ENABLED=true\nRATE_LIMIT_MAX_REQUESTS=20\nRATE_LIMIT_KEY=$key";
            file_put_contents($root . '/.env', $env);
            putenv('CONTACT_MODULE_ROOT=' . $root);
            [$code] = $this->runCommand([PHP_BINARY, dirname(__DIR__, 2) . '/bin/install.php', '--non-interactive']);
            self::assertSame(0, $code);
            self::assertSame($env, file_get_contents($root . '/.env'));
            [$code, $output] = $this->runCommand([PHP_BINARY, dirname(__DIR__, 2) . '/bin/healthcheck.php', '--json']);
            self::assertSame(0, $code);
            $health = json_decode($output, true, 8, JSON_THROW_ON_ERROR);
            self::assertTrue($health['ok']);
            self::assertSame('ready', $health['database']);
            self::assertSame('ready', $health['queue']);
            [$status, $response] = $this->request($root, [
                'entry' => 'config.php', 'server' => ['REQUEST_METHOD' => 'GET'], 'query' => ['form_id' => 'contact'],
            ]);
            self::assertSame(200, $status);
            self::assertSame(['success', 'form_id', 'capture_utm', 'turnstile', 'honeypot'], array_keys($response));
            self::assertTrue($response['capture_utm']);
            self::assertArrayNotHasKey('secret', $response['turnstile']);
            $post = [
                'body' => [
                    'form_id' => 'contact', 'name' => 'Testing', 'email' => 'visitor@example.com', 'message' => 'Hola',
                    'utm' => 'utm_source=google&utm_source=other&token=private&utm_campaign=Ofertas+de+verano'
                        . '&utm_medium=%0Ablocked&utm_term=' . str_repeat('x', 257),
                ],
                'server' => ['HTTP_IDEMPOTENCY_KEY' => 'unique-production-key'],
            ];
            [$status, $response] = $this->request($root, $post);
            self::assertSame(200, $status);
            $database = new \PDO('sqlite:' . $root . '/storage/database/contacts.sqlite');
            $read = $database->prepare('SELECT extra_fields FROM submissions WHERE uuid = ?');
            $read->execute([$response['submission_id']]);
            $fields = json_decode($read->fetchColumn(), true, 8, JSON_THROW_ON_ERROR);
            self::assertSame(['utm' => 'utm_campaign=Ofertas%20de%20verano&utm_source=google'], $fields);
            $read = $database = null;
            [$status, $duplicate] = $this->request($root, $post);
            self::assertSame(200, $status);
            self::assertSame($response['submission_id'], $duplicate['submission_id']);
            $post['body']['message'] = 'Un mensaje diferente';
            self::assertSame(409, $this->request($root, $post)[0]);
            $post['body']['to'] = 'thirdparty@example.com';
            self::assertSame(422, $this->request($root, $post)[0]);
            unset($post['body']['to']);
            foreach (
                [
                ['REQUEST_METHOD' => 'PATCH', 'expected' => 405],
                ['CONTENT_LENGTH' => '100000', 'expected' => 413],
                ['CONTENT_TYPE' => 'text/plain', 'expected' => 415],
                ['HTTP_ORIGIN' => 'https://external.example', 'expected' => 403],
                ] as $server
            ) {
                $expected = $server['expected'];
                unset($server['expected']);
                $post['server'] = $server;
                self::assertSame($expected, $this->request($root, $post)[0]);
            }
        } finally {
            putenv($previous === false ? 'CONTACT_MODULE_ROOT' : 'CONTACT_MODULE_ROOT=' . $previous);
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

    /** @param array<string,mixed> $scenario @return array{int,array<string,mixed>} */
    private function request(string $root, array $scenario): array
    {
        [$code, $output] = $this->runCommand([
            PHP_BINARY, dirname(__DIR__) . '/Fixtures/endpoint.php', $root, json_encode($scenario, JSON_THROW_ON_ERROR),
        ]);
        self::assertSame(0, $code);
        [$body, $status] = explode("\n", trim($output));
        return [(int) $status, json_decode($body, true, 8, JSON_THROW_ON_ERROR)];
    }

    /** @param list<string> $command @return array{int,string} */
    private function runCommand(array $command): array
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open unavailable.');
        }
        $child = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($child);
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
