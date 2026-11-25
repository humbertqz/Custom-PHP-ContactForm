<?php

declare(strict_types=1);

use ContactModule\Support\HttpClient;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
$command = $_SERVER['argv'][1] ?? 'help';
if (in_array($command, ['help', '--help'], true)) {
    echo "Uso: turnstile.php list|get|domains|create|update|add-domain|remove-domain|rotate-secret|delete|export\n";
    echo "Opciones: --site-key=... --name=... --domains=... --domain=... --mode=managed --output=/ruta/privada\n";
    exit;
}
try {
    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Instala primero las dependencias de Composer.');
    }
    require $autoload;
    $options = [];
    foreach (array_slice($_SERVER['argv'], 2) as $arg) {
        if (!preg_match('/\A--(site-key|name|domains|domain|mode|output)=(.+)\z/uD', $arg, $matches)) {
            throw new RuntimeException('Opción inválida. Consulta help.');
        }
        $options[$matches[1]] = $matches[2];
    }
    try {
        $env = is_file(__DIR__ . '/.env') ? Dotenv::parse((string) file_get_contents(__DIR__ . '/.env')) : [];
    } catch (Throwable) {
        throw new RuntimeException('El archivo administrativo .env no es válido.');
    }
    $account = getenv('CLOUDFLARE_ACCOUNT_ID') ?: ($env['CLOUDFLARE_ACCOUNT_ID'] ?? '');
    $token = getenv('CLOUDFLARE_API_TOKEN') ?: ($env['CLOUDFLARE_API_TOKEN'] ?? '');
    if (!preg_match('/\A[a-f0-9]{32}\z/iD', $account) || !preg_match('/\A[A-Za-z0-9_-]{15,200}\z/D', $token)) {
        throw new RuntimeException('Configura CLOUDFLARE_ACCOUNT_ID y CLOUDFLARE_API_TOKEN.');
    }
    if (
        !in_array($command, [
        'list', 'get', 'domains', 'create', 'update', 'add-domain', 'remove-domain',
        'rotate-secret', 'delete', 'export',
        ], true)
    ) {
        throw new RuntimeException('Comando inválido.');
    }
    $key = $options['site-key'] ?? '';
    if (!in_array($command, ['list', 'create'], true) && !preg_match('/\A[A-Za-z0-9_-]{16,32}\z/D', $key)) {
        throw new RuntimeException('Se requiere --site-key válida.');
    }
    $http = new HttpClient();
    $url = "https://api.cloudflare.com/client/v4/accounts/$account/challenges/widgets";
    $request = static function (
        string $method,
        string $path = '',
        ?array $body = null
    ) use (
        $http,
        $url,
        $token
): array {
        try {
            $json = $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR);
            $response = $http->request($method, $url . $path, $json, 15, [
                'Authorization: Bearer ' . $token, 'Content-Type: application/json',
            ]);
        } catch (Throwable) {
            throw new RuntimeException(
                'Conexión con Cloudflare no disponible. Verifica el estado antes de repetir cambios.'
            );
        }
        if ($response['status'] === 429) {
            throw new RuntimeException('Cloudflare aplicó un límite de API. Reintenta más tarde.');
        }
        $data = json_decode($response['body'], true, 16);
        if ($response['status'] < 200 || $response['status'] >= 300 || ($data['success'] ?? false) !== true) {
            $code = (int) ($data['errors'][0]['code'] ?? 0);
            $status = $response['status'];
            throw new RuntimeException("Cloudflare rechazó la operación (HTTP $status, código $code).");
        }
        return $data;
    };
    $output = $options['output'] ?? '';
    if (
        $output !== '' && (!in_array($command, ['create', 'rotate-secret', 'export'], true)
        || file_exists($output) || is_link($output) || !is_writable(dirname($output)))
    ) {
        throw new RuntimeException('Usa --output con una ruta privada nueva y escribible.');
    }
    $path = '/' . $key;
    if ($command === 'list') {
        $result = [];
        for ($page = 1;; $page++) {
            $data = $request('GET', '?page=' . $page . '&per_page=100');
            $batch = $data['result'] ?? [];
            foreach ($batch as $item) {
                $result[] = turnstilePublic($item);
            }
            if ($batch === [] || $page * 100 >= ($data['result_info']['total_count'] ?? count($result))) {
                break;
            }
        }
    } elseif ($command === 'delete' || $command === 'rotate-secret' || $command === 'remove-domain') {
        if (!stream_isatty(STDIN)) {
            throw new RuntimeException('La operación requiere confirmación interactiva.');
        }
        fwrite(STDERR, "Escribe $key para confirmar $command: ");
        if (trim((string) fgets(STDIN)) !== $key) {
            throw new RuntimeException('Operación cancelada.');
        }
        $result = match ($command) {
            'delete' => $request('DELETE', $path)['result'],
            'rotate-secret' => $request('POST', "$path/rotate_secret", ['invalidate_immediately' => false])['result'],
            default => turnstileUpdate($request, $path, $command, $options),
        };
    } elseif ($command === 'create') {
        $settings = turnstileSettings($options['name'] ?? '', $options['mode'] ?? 'managed', $options['domains'] ?? '');
        $result = $request('POST', '', $settings + ['clearance_level' => 'no_clearance'])['result'];
    } elseif (in_array($command, ['update', 'add-domain'], true)) {
        $result = turnstileUpdate($request, $path, $command, $options);
    } else {
        if ($command === 'export' && $output === '') {
            throw new RuntimeException('El comando export requiere --output.');
        }
        $result = $request('GET', $path)['result'];
    }
    if ($output !== '') {
        turnstileExport($output, $result);
    }
    $safe = match ($command) {
        'list' => $result,
        'domains' => $result['domains'] ?? [],
        default => turnstilePublic($result ?? []),
    };
    echo json_encode($safe, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "No se pudo completar la operación de Turnstile.\n");
    exit(1);
}

/** @return array{name:string,mode:string,domains:list<string>} */
function turnstileSettings(string $name, string $mode, string $input): array
{
    $domains = array_values(array_unique(array_map(
        static fn (string $domain): string => strtolower(trim($domain)),
        explode(',', $input)
    )));
    foreach ($domains as $domain) {
        if (
            !filter_var($domain, FILTER_VALIDATE_IP) && (
            !str_contains($domain, '.') || !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            )
        ) {
            throw new RuntimeException('Usa hostnames exactos o IP, sin protocolo ni comodines.');
        }
    }
    $modes = ['managed', 'invisible', 'non-interactive'];
    if (!preg_match('/\A[^\p{C}]{1,254}\z/uD', $name) || !in_array($mode, $modes, true)) {
        throw new RuntimeException('Nombre o modo de widget inválido.');
    }
    return ['name' => $name, 'mode' => $mode, 'domains' => $domains];
}

/** @param callable(string,string,?array=):array $request @param array<string,string> $options */
function turnstileUpdate(callable $request, string $path, string $command, array $options): array
{
    $current = $request('GET', $path)['result'];
    $domains = $current['domains'] ?? [];
    if (in_array($command, ['add-domain', 'remove-domain'], true)) {
        $domain = strtolower(trim($options['domain'] ?? ''));
        if ($domain === '' || str_contains($domain, ',')) {
            throw new RuntimeException('Indica un dominio con --domain.');
        }
        $domains = $command === 'add-domain' ? [...$domains, $domain] : array_diff($domains, [$domain]);
    }
    $settings = turnstileSettings(
        $options['name'] ?? $current['name'],
        $options['mode'] ?? $current['mode'],
        $options['domains'] ?? implode(',', $domains)
    );
    $keys = ['clearance_level', 'bot_fight_mode', 'ephemeral_id', 'offlabel'];
    $kept = array_intersect_key($current, array_flip($keys));
    $kept = array_filter($kept, static fn (mixed $value): bool => $value !== false);
    return $request('PUT', $path, $settings + $kept)['result'];
}

/** @param array<string,mixed> $widget @return array<string,mixed> */
function turnstilePublic(array $widget): array
{
    $keys = ['sitekey', 'name', 'domains', 'mode', 'region', 'clearance_level'];
    return array_intersect_key($widget, array_flip($keys));
}

/** @param array<string,mixed> $widget */
function turnstileExport(string $output, array $widget): void
{
    $key = $widget['sitekey'] ?? '';
    $secret = $widget['secret'] ?? '';
    if (!preg_match('/\A[A-Za-z0-9_-]{16,32}\z/D', $key) || !preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D', $secret)) {
        throw new RuntimeException('Cloudflare no devolvió claves válidas.');
    }
    $settings = turnstileSettings($widget['name'], $widget['mode'], implode(',', $widget['domains']));
    $domains = implode(',', $settings['domains']);
    $content = "TURNSTILE_ENABLED=true\nTURNSTILE_SITE_KEY=$key\nTURNSTILE_SECRET_KEY=$secret\n"
        . "TURNSTILE_ALLOWED_HOSTNAMES=$domains\nTURNSTILE_APPEARANCE=interaction-only\n";
    $handle = @fopen($output, 'xb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo crear el archivo privado.');
    }
    try {
        if (fwrite($handle, $content) !== strlen($content)) {
            throw new RuntimeException('No se pudo completar el archivo privado.');
        }
    } finally {
        fclose($handle);
    }
}
