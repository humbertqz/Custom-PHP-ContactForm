<?php

declare(strict_types=1);

namespace ContactModule\Http;

use ContactModule\Bootstrap;
use ContactModule\Security\CsrfProtection;
use ContactModule\Validation\FormValidator;
use PDOException;
use Throwable;

final class Endpoint
{
    public static function serve(string $package, string $root, bool $configuration): void
    {
        $app = null;
        $headers = ['Vary' => 'Origin'];
        try {
            $app = new Bootstrap($package, $root);
            $request = new Request($_SERVER, $_GET);
            $config = $app->config;
            $origin = $request->value('HTTP_ORIGIN');
            $crossOrigin = $origin !== '' && $origin !== $config->text('APP_URL');
            if ($crossOrigin) {
                if ($config->flag('CSRF_ENABLED') || !in_array($origin, $config->list('ALLOWED_ORIGINS'), true)) {
                    throw new HttpException(403, 'security');
                }
                $headers['Access-Control-Allow-Origin'] = $origin;
            }
            $method = $configuration ? 'GET' : 'POST';
            if ($request->value('REQUEST_METHOD') === 'OPTIONS' && $origin !== '') {
                if ($request->value('HTTP_ACCESS_CONTROL_REQUEST_METHOD') !== $method) {
                    throw new HttpException(405, 'method', ['Allow' => "$method, OPTIONS"]);
                }
                (new Response(204, [], [
                    'Access-Control-Allow-Methods' => $method,
                    'Access-Control-Allow-Headers' => 'Content-Type, Idempotency-Key',
                    'Access-Control-Max-Age' => '600',
                ]))->send($headers);
                return;
            }
            if ($request->value('REQUEST_METHOD') !== $method) {
                throw new HttpException(405, 'method', ['Allow' => "$method, OPTIONS"]);
            }
            $csrf = new CsrfProtection($config->text('APP_ENV') === 'production', $config->text('APP_BASE_PATH'));
            $response = $configuration
                ? (new ConfigController($app, $csrf))->handle($request)
                : (new ContactController($app, $csrf, new FormValidator()))->handle($request);
            $response->send($headers);
        } catch (HttpException $error) {
            (new Response($error->status, [
                'success' => false, 'message' => $app?->messages[$error->messageKey] ?? 'La solicitud no es válida.',
            ], $error->headers))->send($headers);
        } catch (Throwable $error) {
            try {
                $app?->logger->error('request_failure', ['type' => $error::class]);
            } catch (Throwable) {
                error_log('contact-module: logger unavailable');
            }
            if ($app === null) {
                error_log('contact-module: bootstrap failed; run healthcheck');
            }
            $status = $error instanceof PDOException ? 503 : 500;
            (new Response($status, [
                'success' => false,
                'message' => $app?->messages[$status === 503 ? 'unavailable' : 'internal']
                    ?? 'No se pudo procesar la solicitud.',
            ]))->send($headers);
        }
    }
}
