<?php

declare(strict_types=1);

namespace ContactModule\Http;

use ContactModule\Bootstrap;
use ContactModule\Contracts\ValidatorInterface;
use ContactModule\DTO\Submission;
use ContactModule\Security\CsrfProtection;
use ContactModule\Security\Honeypot;
use ContactModule\Security\TrustedProxyResolver;
use ContactModule\Support\UtmParameters;
use ContactModule\Support\Uuid;

final class ContactController
{
    public function __construct(
        private Bootstrap $app,
        private CsrfProtection $csrf,
        private ValidatorInterface $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        $config = $this->app->config;
        $input = $request->body($config->integer('MAX_REQUEST_SIZE_KB') * 1024);
        $id = $input['form_id'] ?? '';
        $form = is_string($id) ? $this->app->forms->get($id) : null;
        if ($form === null) {
            throw new HttpException(422, 'unknown');
        }
        $proxy = new TrustedProxyResolver($config->list('TRUSTED_PROXIES'));
        $ip = $proxy->resolve($request->value('REMOTE_ADDR'), $request->value('HTTP_X_FORWARDED_FOR'));
        $manager = $this->app->runtime();
        $this->rate($id, $ip ?? 'unknown', $form['rate_limit'] ?? null);
        $honeypot = $config->text('HONEYPOT_FIELD');
        if (
            ($config->flag('HONEYPOT_ENABLED') && Honeypot::triggered($input[$honeypot] ?? null))
            || ($config->flag('CSRF_ENABLED') && !$this->csrf->verify($input['csrf_token'] ?? null))
        ) {
            throw new HttpException(403, 'security');
        }

        $token = $input['cf-turnstile-response'] ?? null;
        $sourceUrl = $this->source($request->value('HTTP_REFERER'));
        $utm = '';
        if ($form['capture_utm']) {
            $utm = UtmParameters::normalize($input['utm'] ?? null);
            unset($input['utm']);
        }
        unset($input['form_id'], $input[$honeypot], $input['csrf_token'], $input['cf-turnstile-response']);
        $validation = $this->validator->validate($input, $form['fields']);
        if ($validation->errors !== []) {
            $messages = $this->app->messages;
            return new Response(422, [
                'success' => false,
                'message' => $form['messages']['validation'] ?? $messages['validation'],
                'errors' => array_map(static fn (string $error): string => $messages[$error], $validation->errors),
            ]);
        }
        $key = $request->value('HTTP_IDEMPOTENCY_KEY');
        if ($key !== '' && !preg_match('/\A[A-Za-z0-9_-]{20,128}\z/D', $key)) {
            throw new HttpException(400, 'bad_request');
        }
        if ($this->app->captcha !== null) {
            try {
                $valid = is_string($token) && $this->app->captcha->verify($token, $form['turnstile_action'], $ip);
            } catch (\RuntimeException) {
                $this->app->logger->warning('turnstile_unavailable');
                throw new HttpException(503, 'unavailable');
            }
            if (!$valid) {
                throw new HttpException(403, 'security');
            }
        }

        $email = $validation->fields['email'] ?? null;
        $confirmation = $config->flag('MAIL_CONFIRMATION_ENABLED')
            && ($form['channels']['email_confirmation'] ?? true)
            && is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && $this->app->limiter?->allow(
                'confirmation',
                strtolower($email),
                $config->integer('CONFIRMATION_MAX_PER_HOUR'),
                3600
            );
        $fields = $validation->fields;
        if ($utm !== '') {
            $fields['utm'] = $utm;
        }
        $submission = new Submission(
            Uuid::create(),
            $id,
            gmdate('Y-m-d\TH:i:s\Z'),
            $fields,
            $sourceUrl,
            $form['sheets_route'] ?? $id
        );
        [$status, $uuid] = $manager->process($submission, $form, $confirmation, $key === '' ? null : $key);
        if ($status === 409) {
            throw new HttpException(409, 'conflict');
        }
        if ($status === 503) {
            throw new HttpException(503, 'unavailable');
        }
        $payload = [
            'success' => true,
            'message' => $form['messages']['success'] ?? $this->app->messages['success'],
            'submission_id' => $uuid,
        ];
        if ($status === 202) {
            $payload['status'] = 'accepted';
        }
        return new Response($status, $payload);
    }

    private function rate(string $id, string $ip, ?int $max): void
    {
        $limiter = $this->app->limiter;
        if ($limiter === null) {
            return;
        }
        $config = $this->app->config;
        $window = $config->integer('RATE_LIMIT_WINDOW_SECONDS');
        if (
            !$limiter->allow('ip:' . $id, $ip, $max ?? $config->integer('RATE_LIMIT_MAX_REQUESTS'), $window)
            || !$limiter->allow('ip', $ip, $config->integer('RATE_LIMIT_IP_MAX'), $window)
            || !$limiter->allow('form', $id, $config->integer('RATE_LIMIT_FORM_MAX'), $window)
            || !$limiter->allow('installation', '', $config->integer('RATE_LIMIT_INSTALLATION_MAX'), $window)
        ) {
            throw new HttpException(429, 'rate', ['Retry-After' => (string) $window]);
        }
    }

    private function source(string $referer): string
    {
        if ($referer === '' || strlen($referer) > 2048 || !filter_var($referer, FILTER_VALIDATE_URL)) {
            return '';
        }
        $parts = parse_url($referer);
        if (!isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $allowed = [$this->app->config->text('APP_URL'), ...$this->app->config->list('ALLOWED_ORIGINS')];
        return in_array($origin, $allowed, true) ? $origin . ($parts['path'] ?? '/') : '';
    }
}
