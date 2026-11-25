<?php

declare(strict_types=1);

namespace ContactModule\Validation;

use ContactModule\Contracts\ValidatorInterface;
use ContactModule\DTO\ValidationResult;
use DateTimeImmutable;

final class FormValidator implements ValidatorInterface
{
    public function validate(array $input, array $schema): ValidationResult
    {
        $errors = [];
        $values = [];
        if (array_diff_key($input, $schema) !== []) {
            $errors['form_id'] = 'unknown';
        }
        foreach ($schema as $name => $rules) {
            $raw = $input[$name] ?? $rules['default'] ?? null;
            $multiple = $rules['type'] === 'checkbox_group' || ($rules['multiple'] ?? false);
            $items = $multiple && !is_array($raw) && $raw !== null ? [$raw] : $raw;
            $missing = $raw === null || $raw === '' || $items === []
                || ($rules['type'] === 'checkbox' && $raw === false);
            if ($missing) {
                if ($rules['required'] ?? false) {
                    $errors[$name] = 'required';
                }
                continue;
            }
            if ($multiple && (!is_array($items) || !array_is_list($items) || count($items) > 32)) {
                $errors[$name] = 'field';
                continue;
            }
            $parsed = [];
            foreach ($multiple ? $items : [$raw] as $item) {
                $value = $this->value($item, $rules);
                if ($value === null) {
                    $errors[$name] = $rules['type'] === 'email' ? 'email' : 'field';
                    break;
                }
                $parsed[] = $value;
            }
            if (!isset($errors[$name])) {
                $values[$name] = $multiple ? array_values(array_unique($parsed, SORT_REGULAR)) : $parsed[0];
            }
        }
        return new ValidationResult($values, $errors);
    }

    /** @param array<string,mixed> $rules */
    private function value(mixed $raw, array $rules): string|int|float|bool|null
    {
        $type = $rules['type'];
        if ($type === 'checkbox') {
            return in_array($raw, [true, 'true', 'on', 1, '1'], true) ? true : null;
        }
        if (!is_string($raw) && ($type !== 'number' || (!is_int($raw) && !is_float($raw)))) {
            return null;
        }
        $value = (string) $raw;
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            return null;
        }
        $value = match ($rules['normalize'] ?? 'trim') {
            'none' => $value,
            'lowercase' => mb_strtolower(trim($value), 'UTF-8'),
            default => trim(str_replace(["\r\n", "\r"], "\n", $value)),
        };
        $length = mb_strlen($value, 'UTF-8');
        if (
            ($type !== 'textarea' && strpbrk($value, "\r\n") !== false)
            || (($rules['required'] ?? false) && $value === '')
            || $length < ($rules['min_length'] ?? 1)
            || $length > ($rules['max_length'] ?? ($type === 'email' ? 254 : 5000))
            || (isset($rules['pattern']) && preg_match($rules['pattern'], $value) !== 1)
        ) {
            return null;
        }
        return match ($type) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null,
            'tel' => preg_match('/\A[+0-9(). x-]+\z/iD', $value) && preg_match('/[0-9]/', $value) ? $value : null,
            'number' => $this->number($value, $rules),
            'select', 'radio', 'checkbox_group' => in_array($value, $rules['options'], true) ? $value : null,
            'date' => $this->date($value) ? $value : null,
            'url' => filter_var($value, FILTER_VALIDATE_URL) && preg_match('~\Ahttps?://~i', $value) ? $value : null,
            default => $value,
        };
    }

    /** @param array<string,mixed> $rules */
    private function number(string $value, array $rules): int|float|null
    {
        if (!is_numeric($value) || !preg_match('/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?\z/D', $value)) {
            return null;
        }
        $number = $value + 0;
        return is_finite((float) $number) && $number >= ($rules['min'] ?? -INF) && $number <= ($rules['max'] ?? INF)
            ? $number : null;
    }

    private function date(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
