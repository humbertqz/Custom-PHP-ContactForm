<?php

declare(strict_types=1);

namespace ContactModule\Http;

use ContactModule\Config\Config;
use ContactModule\Config\ConfigLoader;
use ContactModule\Database\Connection;
use ContactModule\Security\RateLimiter;
use ContactModule\Security\TrustedProxyResolver;
use PDO;
use RuntimeException;
use Throwable;

final class AdminPanel
{
    private const SESSION_COOKIE = 'PCM_ADMIN';
    private const PAGE_SIZE = 20;

    private function __construct(private Config $config, private Request $request)
    {
    }

    public static function serve(string $package, string $root): void
    {
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; '
            . 'form-action \'self\'; frame-ancestors \'none\'; base-uri \'none\'');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');

        try {
            $config = ConfigLoader::load($package, $root);
            (new self($config, new Request($_SERVER, $_GET)))->handle();
        } catch (Throwable $error) {
            error_log('contact-module: admin request failed (' . $error::class . ')');
            http_response_code(503);
            echo 'El panel no está disponible.';
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    private function handle(): void
    {
        $name = $this->config->text('ADMIN_USERNAME');
        $hash = $this->config->text('ADMIN_PASSWORD_HASH');
        if (
            !$this->config->flag('ADMIN_ENABLED') || !$this->config->flag('DATABASE_ENABLED')
            || !$this->config->flag('RATE_LIMIT_ENABLED') || $this->config->text('DB_DRIVER') !== 'sqlite'
            || $this->config->text('RATE_LIMIT_DRIVER') !== 'sqlite'
            || preg_match('/\A[A-Za-z0-9_.@-]{3,64}\z/D', $name) !== 1
            || password_get_info($hash)['algoName'] !== 'bcrypt'
        ) {
            http_response_code(404);
            echo 'Página no disponible.';
            return;
        }

        $method = $this->request->value('REQUEST_METHOD');
        if (!in_array($method, ['GET', 'POST'], true)) {
            header('Allow: GET, POST');
            http_response_code(405);
            return;
        }
        $origin = $this->config->text('APP_URL');
        $secure = str_starts_with($origin, 'https://');
        $local = in_array(parse_url($origin, PHP_URL_HOST), ['localhost', '127.0.0.1', '[::1]'], true)
            && in_array($this->request->value('REMOTE_ADDR'), ['127.0.0.1', '::1'], true);
        $https = in_array(strtolower($this->request->value('HTTPS')), ['on', '1'], true);
        if ($secure ? !$https : !$local) {
            http_response_code(403);
            echo 'Abre el panel mediante HTTPS.';
            return;
        }

        $path = $this->config->text('APP_BASE_PATH') . '/admin.php';
        $stamp = hash('sha256', $name . "\0" . $hash);
        $this->startSession($secure, $path);
        $now = time();
        $loggedIn = is_string($_SESSION['auth'] ?? null)
            && hash_equals($stamp, $_SESSION['auth'])
            && (int) ($_SESSION['last_seen'] ?? 0) > $now - 1800
            && (int) ($_SESSION['signed_in'] ?? 0) > $now - 28800;
        if ($loggedIn) {
            $_SESSION['last_seen'] = $now;
        } else {
            unset($_SESSION['auth'], $_SESSION['last_seen'], $_SESSION['signed_in']);
        }
        $token = $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        $error = '';

        if ($method === 'POST') {
            $error = $this->post($path, $secure, $loggedIn, $token, $name, $hash, $stamp, $now);
            if ($error === '') {
                return;
            }
        }

        session_write_close();
        $page = filter_var($this->request->query['page'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100000],
        ]) ?: 1;
        $messages = $loggedIn ? $this->messages($page) : [];
        $hasMore = count($messages) > self::PAGE_SIZE && $page < 100000;
        $messages = array_slice($messages, 0, self::PAGE_SIZE);
        require $this->config->package . '/templates/admin.php';
    }

    private function startSession(bool $secure, string $path): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            throw new RuntimeException('Admin session requires an isolated endpoint.');
        }
        session_name(self::SESSION_COOKIE);
        if (
            !session_start([
                'use_strict_mode' => 1, 'use_only_cookies' => 1, 'cookie_httponly' => 1,
                'cookie_secure' => $secure, 'cookie_samesite' => 'Strict', 'cookie_path' => $path,
                'cache_limiter' => '',
            ])
        ) {
            throw new RuntimeException('Admin session unavailable.');
        }
    }

    private function post(
        string $path,
        bool $secure,
        bool $loggedIn,
        string $token,
        string $name,
        string $hash,
        string $stamp,
        int $now,
    ): string {
        $length = $this->request->value('CONTENT_LENGTH');
        $submitted = $_POST['csrf'] ?? null;
        if (
            !ctype_digit($length) || (int) $length > 4096
            || !isset($_COOKIE[self::SESSION_COOKIE]) || !is_string($submitted) || !hash_equals($token, $submitted)
        ) {
            http_response_code(403);
            return 'La sesión del formulario venció. Vuelve a abrir el panel.';
        }

        $action = $_POST['action'] ?? null;
        if ($loggedIn && $action === 'logout') {
            $_SESSION = [];
            session_destroy();
            setcookie(self::SESSION_COOKIE, '', [
                'expires' => 1, 'path' => $path, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict',
            ]);
        } elseif (!$loggedIn && $action === 'login') {
            if (!$this->allowLogin()) {
                header('Retry-After: 900');
                http_response_code(429);
                return 'Demasiados intentos. Inténtalo de nuevo en unos minutos.';
            }
            $username = $_POST['username'] ?? null;
            $password = $_POST['password'] ?? null;
            $valid = is_string($password) && strlen($password) <= 72
                && !str_contains($password, "\0") && password_verify($password, $hash);
            if (!$valid || !is_string($username) || !hash_equals($name, $username)) {
                http_response_code(401);
                return 'El usuario o la contraseña no son válidos.';
            }
            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Cannot renew admin session.');
            }
            $_SESSION = [
                'auth' => $stamp, 'signed_in' => $now, 'last_seen' => $now, 'csrf' => bin2hex(random_bytes(32)),
            ];
            session_write_close();
        } else {
            http_response_code(403);
            return 'La operación no está disponible.';
        }

        header('Location: ' . $path, true, 303);
        return '';
    }

    private function allowLogin(): bool
    {
        $proxy = new TrustedProxyResolver($this->config->list('TRUSTED_PROXIES'));
        $address = $proxy->resolve(
            $this->request->value('REMOTE_ADDR'),
            $this->request->value('HTTP_X_FORWARDED_FOR')
        ) ?? 'unknown';
        $path = $this->config->path('storage/cache/rate.sqlite');
        if (!is_file($path)) {
            throw new RuntimeException('Rate limit database unavailable.');
        }
        $limiter = new RateLimiter(Connection::sqlite($path), $this->config->text('RATE_LIMIT_KEY'));
        return $limiter->allow('admin_ip', $address, 10, 900)
            && $limiter->allow('admin_global', 'all', 80, 900);
    }

    /** @return list<array<string,string>> */
    private function messages(int $page): array
    {
        $path = $this->config->path($this->config->text('DB_PATH'));
        $pdo = Connection::sqlite($path, true);
        $query = $pdo->prepare(
            'SELECT name, email, phone, subject, message, extra_fields, form_id, source_url, created_at
            FROM submissions ORDER BY id DESC LIMIT :limit OFFSET :offset'
        );
        $query->bindValue(':limit', self::PAGE_SIZE + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll();
    }
}
