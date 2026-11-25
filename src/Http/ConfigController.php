<?php

declare(strict_types=1);

namespace ContactModule\Http;

use ContactModule\Bootstrap;
use ContactModule\Security\CsrfProtection;

final class ConfigController
{
    public function __construct(private Bootstrap $app, private CsrfProtection $csrf)
    {
    }

    public function handle(Request $request): Response
    {
        $id = $request->query['form_id'] ?? '';
        $form = is_string($id) ? $this->app->forms->get($id) : null;
        if ($form === null) {
            throw new HttpException(400, 'unknown');
        }
        $config = $this->app->config;
        $turnstile = $config->flag('TURNSTILE_ENABLED');
        $payload = [
            'success' => true,
            'form_id' => $id,
            'capture_utm' => $form['capture_utm'],
            'turnstile' => [
                'enabled' => $turnstile,
                'site_key' => $turnstile ? $config->text('TURNSTILE_SITE_KEY') : '',
                'action' => $form['turnstile_action'],
                'theme' => $config->text('TURNSTILE_THEME'),
                'appearance' => $config->text('TURNSTILE_APPEARANCE'),
                'size' => $config->text('TURNSTILE_SIZE'),
            ],
            'honeypot' => ['enabled' => $config->flag('HONEYPOT_ENABLED'), 'field' => $config->text('HONEYPOT_FIELD')],
        ];
        if ($config->flag('CSRF_ENABLED')) {
            $payload['csrf_token'] = $this->csrf->issue();
        }
        return new Response(200, $payload, [
            'Cache-Control' => isset($payload['csrf_token']) ? 'no-store' : 'private, max-age=60',
        ]);
    }
}
