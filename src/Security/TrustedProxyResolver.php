<?php

declare(strict_types=1);

namespace ContactModule\Security;

use ContactModule\Config\ConfigurationException;

final class TrustedProxyResolver
{
    /** @var list<array{string,int}> */
    private array $networks = [];

    /** @param list<string> $proxies */
    public function __construct(array $proxies)
    {
        foreach ($proxies as $item) {
            [$ip, $mask] = array_pad(explode('/', $item, 2), 2, null);
            $bytes = filter_var($ip, FILTER_VALIDATE_IP) ? inet_pton($ip) : false;
            if (
                $bytes === false || ($mask !== null && (!ctype_digit($mask) || (int) $mask < 1
                        || (int) $mask > strlen($bytes) * 8))
            ) {
                throw new ConfigurationException('TRUSTED_PROXIES contiene una dirección inválida.');
            }
            $this->networks[] = [$bytes, $mask === null ? strlen($bytes) * 8 : (int) $mask];
        }
    }

    public function resolve(string $peer, string $forwarded): ?string
    {
        if (!filter_var($peer, FILTER_VALIDATE_IP)) {
            return null;
        }
        $peer = (string) inet_ntop((string) inet_pton($peer));
        if (!$this->trusted($peer)) {
            return $peer;
        }
        if ($forwarded === '' || strlen($forwarded) > 2048) {
            return null;
        }
        foreach (array_reverse(explode(',', $forwarded)) as $value) {
            $ip = trim($value);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return null;
            }
            if (!$this->trusted($ip)) {
                return inet_ntop((string) inet_pton($ip)) ?: null;
            }
        }
        return null;
    }

    private function trusted(string $ip): bool
    {
        $bytes = (string) inet_pton($ip);
        foreach ($this->networks as [$network, $mask]) {
            $whole = intdiv($mask, 8);
            $remainder = $mask % 8;
            if (
                strlen($network) === strlen($bytes) && substr($bytes, 0, $whole) === substr($network, 0, $whole)
                && ($remainder === 0 || ((ord($network[$whole]) ^ ord($bytes[$whole])) >> (8 - $remainder)) === 0)
            ) {
                return true;
            }
        }
        return false;
    }
}
