<?php

declare(strict_types=1);

namespace ContactModule\Config;

final class FormRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $forms = [];

    public function __construct(private Config $config)
    {
        $directory = $config->root . '/config/forms';
        if (!is_dir($directory)) {
            $directory = $config->package . '/config/forms';
        }
        foreach (glob($directory . '/*.php') ?: [] as $path) {
            $form = require $path;
            $this->register($form);
        }
        if ($this->forms === []) {
            throw new ConfigurationException('No hay formularios registrados.');
        }
    }

    /** @param array<string, mixed> $form */
    private function register(array $form): void
    {
        $id = $form['id'] ?? '';
        if (!is_string($id) || !preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/D', $id) || isset($this->forms[$id])) {
            throw new ConfigurationException('Identificador de formulario inválido o duplicado.');
        }
        if (!is_string($form['name'] ?? null) || !mb_check_encoding($form['name'], 'UTF-8')) {
            throw new ConfigurationException('El formulario requiere un nombre válido.');
        }
        foreach (['mail', 'messages', 'channels'] as $section) {
            if (isset($form[$section]) && !is_array($form[$section])) {
                throw new ConfigurationException("Sección inválida en el formulario: $section.");
            }
        }
        $fields = $form['fields'] ?? [];
        $reserved = ['form_id', 'csrf_token', 'source_url', 'constructor', 'prototype'];
        $reserved[] = $this->config->text('HONEYPOT_FIELD');
        if (!is_array($fields) || count($fields) < 1 || count($fields) > 64) {
            throw new ConfigurationException("Campos inválidos para $id.");
        }
        foreach ($fields as $name => $field) {
            if (
                !preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', (string) $name)
                || in_array($name, $reserved, true)
                || !is_array($field) || !is_string($field['label'] ?? null)
            ) {
                throw new ConfigurationException("Campo inválido en $id.");
            }
            $this->validateField($field);
        }
        $form['capture_utm'] = !isset($fields['utm']) && $this->config->text('HONEYPOT_FIELD') !== 'utm';
        $action = $form['turnstile_action'] ?? $this->config->text('TURNSTILE_ACTION');
        if (!is_string($action) || !self::validAction($action)) {
            throw new ConfigurationException("Acción Turnstile inválida para $id.");
        }
        $form['turnstile_action'] = $action;
        $channels = $form['channels'] ?? [];
        foreach ($channels as $channel => $enabled) {
            $key = match ($channel) {
                'database' => 'DATABASE_ENABLED',
                'google_sheets' => 'GOOGLE_SHEETS_ENABLED',
                'email_notification' => 'MAIL_ENABLED',
                'email_confirmation' => 'MAIL_CONFIRMATION_ENABLED',
                default => throw new ConfigurationException('Canal de formulario desconocido.'),
            };
            if (!is_bool($enabled) || ($enabled && !$this->config->flag($key))) {
                throw new ConfigurationException("Canal de formulario inválido: $id/$channel.");
            }
        }
        $required = match ($this->config->text('STORAGE_MODE')) {
            'email_only' => ['email_notification'],
            'google_sheets_only' => ['google_sheets'],
            'parallel', 'fallback' => ['database', 'google_sheets'],
            default => ['database'],
        };
        foreach ($required as $channel) {
            if (($channels[$channel] ?? true) === false) {
                throw new ConfigurationException("El destino $channel es obligatorio para $id.");
            }
        }
        foreach (['to', 'cc', 'bcc'] as $key) {
            if (isset($form['mail'][$key])) {
                $addresses = $form['mail'][$key];
                if (
                    !is_array($addresses) || !array_is_list($addresses)
                    || array_filter($addresses, 'is_string') !== $addresses
                ) {
                    throw new ConfigurationException('La lista SMTP debe contener direcciones de correo.');
                }
                ConfigValidator::addresses($addresses);
                if ($key === 'to' && $form['mail'][$key] === []) {
                    throw new ConfigurationException('MAIL to no puede quedar vacío.');
                }
            }
        }
        foreach (['subject', 'confirmation_subject'] as $key) {
            if (
                isset($form['mail'][$key]) && (!is_string($form['mail'][$key])
                || !preg_match('/\A[^\p{C}]{1,200}\z/uD', $form['mail'][$key]))
            ) {
                throw new ConfigurationException('Asunto configurado inválido.');
            }
        }
        foreach (['template', 'confirmation_template', 'sheets_route'] as $key) {
            $value = $key === 'sheets_route' ? ($form[$key] ?? $id) : ($form['mail'][$key] ?? 'default');
            if (!is_string($value) || !preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/D', $value)) {
                throw new ConfigurationException('Identificador de plantilla o ruta de hoja inválido.');
            }
        }
        foreach (['success', 'validation'] as $key) {
            if (
                isset($form['messages'][$key]) && (!is_string($form['messages'][$key])
                    || !mb_check_encoding($form['messages'][$key], 'UTF-8'))
            ) {
                throw new ConfigurationException('Mensaje de formulario inválido.');
            }
        }
        if (isset($form['rate_limit']) && (!is_int($form['rate_limit']) || $form['rate_limit'] < 1)) {
            throw new ConfigurationException('Límite por formulario inválido.');
        }
        $this->forms[$id] = $form;
    }

    /** @param array<string, mixed> $field */
    private function validateField(array $field): void
    {
        $type = $field['type'] ?? '';
        if (
            !in_array($type, [
            'text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox', 'checkbox_group',
            'date', 'url', 'hidden',
            ], true)
        ) {
            throw new ConfigurationException('Tipo de campo desconocido.');
        }
        foreach (['required', 'multiple'] as $key) {
            if (isset($field[$key]) && !is_bool($field[$key])) {
                throw new ConfigurationException('Regla booleana de campo inválida.');
            }
        }
        foreach (['min', 'max'] as $key) {
            $bound = $field[$key] ?? null;
            if ($bound !== null && (!(is_int($bound) || is_float($bound)) || !is_finite($bound))) {
                throw new ConfigurationException('Límite numérico de campo inválido.');
            }
        }
        foreach (['min_length', 'max_length'] as $key) {
            if (isset($field[$key]) && (!is_int($field[$key]) || $field[$key] < 0 || $field[$key] > 10000)) {
                throw new ConfigurationException('Longitud de campo inválida.');
            }
        }
        if (
            ($field['min_length'] ?? 0) > ($field['max_length'] ?? 10000)
            || (isset($field['min'], $field['max']) && $field['min'] > $field['max'])
            || (isset($field['pattern']) && (
                !is_string($field['pattern']) || @preg_match($field['pattern'], '') === false
            ))
            || !in_array($field['normalize'] ?? 'trim', ['trim', 'none', 'lowercase'], true)
        ) {
            throw new ConfigurationException('Reglas de campo inválidas.');
        }
        if (in_array($type, ['select', 'radio', 'checkbox_group'], true)) {
            $options = $field['options'] ?? [];
            if ($options === [] || !is_array($options) || array_filter($options, 'is_string') !== $options) {
                throw new ConfigurationException('Opciones de campo inválidas.');
            }
        }
    }

    public static function validAction(string $action): bool
    {
        return (bool) preg_match('/\A[A-Za-z0-9_-]{1,32}\z/D', $action);
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        return $this->forms[$id] ?? null;
    }
}
