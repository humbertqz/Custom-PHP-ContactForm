<?php

declare(strict_types=1);

namespace ContactModule\Support;

use ContactModule\Config\Config;
use ContactModule\Contracts\EventLoggerInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as Monolog;
use Psr\Log\LoggerInterface;

final class Logger implements EventLoggerInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public static function create(Config $config): self
    {
        return new self(new Monolog('contact-module', [
            new RotatingFileHandler(
                $config->path('storage/logs/contact.log'),
                $config->integer('LOG_RETENTION_DAYS'),
                $config->text('LOG_LEVEL'),
                true,
                0600,
                true
            ),
        ]));
    }

    public function warning(string $event, array $context = []): void
    {
        $this->write('warning', $event, $context);
    }

    public function error(string $event, array $context = []): void
    {
        $this->write('error', $event, $context);
    }

    /** @param array<string,mixed> $context */
    private function write(string $level, string $event, array $context): void
    {
        try {
            $this->logger->log($level, $event, $context);
        } catch (\Throwable) {
            error_log('contact-module: private logger unavailable');
        }
    }
}
