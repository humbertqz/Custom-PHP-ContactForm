<?php

declare(strict_types=1);

namespace ContactModule;

use ContactModule\Contracts\EventLoggerInterface;
use ContactModule\Config\Config;
use ContactModule\Config\ConfigLoader;
use ContactModule\Config\FormRegistry;
use ContactModule\Database\Connection;
use ContactModule\Database\MigrationManager;
use ContactModule\Queue\DatabaseQueue;
use ContactModule\Queue\JobProcessor;
use ContactModule\Security\RateLimiter;
use ContactModule\Security\TurnstileAdapter;
use ContactModule\Services\MailService;
use ContactModule\Storage\GoogleSheetsAdapter;
use ContactModule\Storage\MySQLAdapter;
use ContactModule\Storage\PdoStorage;
use ContactModule\Storage\SQLiteAdapter;
use ContactModule\Storage\StorageManager;
use ContactModule\Storage\UnavailableDatabase;
use ContactModule\Support\HttpClient;
use ContactModule\Support\Logger;
use PDO;
use PDOException;

final class Bootstrap
{
    public readonly Config $config;
    public readonly FormRegistry $forms;
    public readonly EventLoggerInterface $logger;
    /** @var array<string,string> */
    public readonly array $messages;
    public ?PdoStorage $database = null;
    public ?DatabaseQueue $queue = null;
    public ?JobProcessor $worker = null;
    public ?RateLimiter $limiter = null;
    public ?PDO $rateDb = null;
    public ?TurnstileAdapter $captcha = null;
    public ?MailService $mail = null;
    /** @var array<string,PDO> */
    private array $connections = [];

    public function __construct(string $package, string $root)
    {
        $this->config = ConfigLoader::load($package, $root);
        $this->forms = new FormRegistry($this->config);
        $this->logger = Logger::create($this->config);
        $defaults = require $package . '/config/messages.php';
        $custom = is_file($root . '/config/messages.php') ? require $root . '/config/messages.php' : [];
        $this->messages = array_replace($defaults, $custom);
    }

    public function runtime(bool $strict = false): ContactManager
    {
        $config = $this->config;
        $local = null;
        if ($config->flag('DATABASE_ENABLED')) {
            try {
                $pdo = $this->connection('DB_DRIVER', 'DB_PATH');
                $local = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                    ? new SQLiteAdapter($pdo) : new MySQLAdapter($pdo);
                $this->database = $local;
            } catch (PDOException $error) {
                if ($strict) {
                    throw $error;
                }
                $local = new UnavailableDatabase();
            }
        }
        if ($config->flag('QUEUE_ENABLED')) {
            $this->queue = new DatabaseQueue($this->connection('QUEUE_DRIVER', 'QUEUE_DB_PATH'));
        }
        if ($config->flag('RATE_LIMIT_ENABLED')) {
            $this->rateDb = $config->text('RATE_LIMIT_DRIVER') === 'sqlite'
                ? Connection::sqlite($config->path('storage/cache/rate.sqlite'))
                : $this->connections['mysql'] ??= Connection::mysql($config);
            $this->limiter = new RateLimiter($this->rateDb, $config->text('RATE_LIMIT_KEY'));
        }
        $http = new HttpClient();
        $sheets = $config->flag('GOOGLE_SHEETS_ENABLED') ? new GoogleSheetsAdapter(
            $http,
            $config->text('GOOGLE_SCRIPT_URL'),
            $config->text('GOOGLE_SCRIPT_TOKEN'),
            $config->integer('GOOGLE_SCRIPT_TIMEOUT')
        ) : null;
        $this->mail = $config->flag('MAIL_ENABLED') ? new MailService($config) : null;
        $this->captcha = $config->flag('TURNSTILE_ENABLED') ? new TurnstileAdapter(
            $http,
            $config->text('TURNSTILE_SECRET_KEY'),
            $config->list('TURNSTILE_ALLOWED_HOSTNAMES'),
            $config->integer('TURNSTILE_TIMEOUT')
        ) : null;
        $storage = new StorageManager($config->text('STORAGE_MODE'), $local, $sheets, $this->mail);
        if ($this->queue !== null) {
            $this->worker = new JobProcessor(
                $config,
                $this->queue,
                $storage,
                $this->database,
                $this->forms,
                $this->logger
            );
        }
        return new ContactManager($config, $storage, $this->database, $this->queue, $this->worker, $this->logger);
    }

    public function migrate(): void
    {
        $migration = new MigrationManager();
        $components = [];
        if ($this->database !== null) {
            $components[] = 'submissions';
        }
        if ($this->queue !== null && $this->queue->pdo === $this->database?->pdo) {
            $components[] = 'queue';
        } elseif ($this->queue !== null) {
            $migration->run($this->queue->pdo, $this->config->package, ['queue']);
        }
        if ($this->database !== null) {
            $migration->run($this->database->pdo, $this->config->package, $components);
        }
        if ($this->rateDb !== null) {
            $migration->run($this->rateDb, $this->config->package, ['rate_limits']);
        }
    }

    private function connection(string $driver, string $path): PDO
    {
        if ($this->config->text($driver) !== 'sqlite') {
            return $this->connections['mysql'] ??= Connection::mysql($this->config);
        }
        $name = $this->config->path($this->config->text($path));
        return $this->connections[$name] ??= Connection::sqlite($name);
    }
}
