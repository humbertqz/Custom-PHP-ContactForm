<?php

declare(strict_types=1);

namespace ContactModule\Services;

use ContactModule\Bootstrap;
use ContactModule\Support\HttpClient;
use Throwable;

final class HealthCheckService
{
    /** @return array<string,mixed> */
    public function check(Bootstrap $app, bool $network): array
    {
        $config = $app->config;
        $result = [
            'ok' => true, 'php' => PHP_VERSION, 'config' => 'valid',
            'private_storage' => is_writable($config->path('storage/logs')) ? 'writable' : 'unavailable',
            'smtp' => $config->flag('MAIL_ENABLED') ? 'configured' : 'disabled',
            'sheets' => $config->flag('GOOGLE_SHEETS_ENABLED') ? 'configured' : 'disabled',
            'turnstile' => $config->flag('TURNSTILE_ENABLED') ? 'configured' : 'disabled',
        ];
        $result['ok'] = $result['private_storage'] === 'writable';
        $connections = [
            'database' => [$app->database?->pdo, 'submissions'],
            'queue' => [$app->queue?->pdo, 'deliveries'],
            'rate_limits' => [$app->rateDb, 'rate_limits'],
        ];
        foreach ($connections as $name => [$pdo, $table]) {
            try {
                $pdo?->query("SELECT 1 FROM $table LIMIT 1")->closeCursor();
                $result[$name] = $pdo === null ? 'disabled' : 'ready';
            } catch (Throwable) {
                $result[$name] = 'unavailable_or_unmigrated';
                $result['ok'] = false;
            }
        }
        if ($app->queue !== null && $result['queue'] === 'ready') {
            $result['queue_counts'] = array_replace(array_fill_keys([
                'pending', 'processing', 'retry_scheduled', 'completed', 'failed', 'ambiguous',
            ], 0), $app->queue->counts());
        }
        if (!$network) {
            return $result;
        }
        if ($app->mail !== null) {
            $mail = $app->mail->client();
            try {
                $result['smtp'] = $mail->smtpConnect($mail->SMTPOptions) ? 'authenticated' : 'unavailable';
            } catch (Throwable) {
                $result['smtp'] = 'unavailable';
            } finally {
                $mail->smtpClose();
            }
            $result['ok'] = $result['ok'] && $result['smtp'] === 'authenticated';
        }
        $http = new HttpClient();
        $probes = [
            'sheets' => [$config->text('GOOGLE_SCRIPT_URL'), 'GOOGLE_SCRIPT_TIMEOUT'],
            'turnstile' => [
                'https://challenges.cloudflare.com/turnstile/v0/siteverify', 'TURNSTILE_TIMEOUT',
            ],
        ];
        foreach ($probes as $name => [$url, $timeout]) {
            if ($result[$name] !== 'configured') {
                continue;
            }
            try {
                $response = $http->request('GET', $url, null, $config->integer($timeout), []);
                $result[$name] = $response['status'] < 500 ? 'https_reachable' : 'unavailable';
                $result[$name . '_http_status'] = $response['status'];
            } catch (Throwable) {
                $result[$name] = 'unavailable';
            }
            $result['ok'] = $result['ok'] && $result[$name] === 'https_reachable';
        }
        return $result;
    }
}
