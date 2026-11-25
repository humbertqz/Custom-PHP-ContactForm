<?php

declare(strict_types=1);

namespace ContactModule\Config;

final readonly class Config
{
    /** @param array<string, string|null> $values */
    public function __construct(public string $root, public string $package, private array $values)
    {
    }

    public function text(string $key): string
    {
        return $this->values[$key] ?? '';
    }

    public function flag(string $key): bool
    {
        return match ($this->text($key)) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw new ConfigurationException("Booleano inválido: $key"),
        };
    }

    public function integer(string $key, int $min = 1, int $max = 65535): int
    {
        $value = filter_var($this->text($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);
        if ($value === false) {
            throw new ConfigurationException("Entero fuera de rango: $key");
        }
        return $value;
    }

    /** @return list<string> */
    public function list(string $key): array
    {
        $value = trim($this->text($key));
        return $value === '' ? [] : array_map('trim', explode(',', $value));
    }

    public function path(string $relative): string
    {
        if (!preg_match('~\Astorage(?:/[A-Za-z0-9_-][A-Za-z0-9_.-]*)+\z~D', $relative)) {
            throw new ConfigurationException('Ruta privada inválida.');
        }
        $path = $this->root;
        foreach (explode('/', $relative) as $part) {
            $path .= '/' . $part;
            if (is_link($path)) {
                throw new ConfigurationException('Una ruta privada contiene un enlace simbólico.');
            }
        }
        return $path;
    }
}
