<?php

declare(strict_types=1);

namespace ContactModule\Support;

final class UtmParameters
{
    private const KEYS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content',
        'utm_source_platform', 'utm_creative_format', 'utm_marketing_tactic',
    ];

    public static function normalize(mixed $query): string
    {
        if (!is_string($query) || strlen($query) > 8192) {
            return '';
        }

        $parameters = [];
        foreach (explode('&', $query) as $pair) {
            $separator = strpos($pair, '=');
            if ($separator === false) {
                continue;
            }
            $key = urldecode(substr($pair, 0, $separator));
            $value = urldecode(substr($pair, $separator + 1));
            if (
                in_array($key, self::KEYS, true) && !isset($parameters[$key])
                && strlen($value) <= 256 && preg_match('/\p{Cc}/u', $value) === 0 && trim($value) !== ''
            ) {
                $parameters[$key] = trim($value);
            }
        }

        ksort($parameters);
        return http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
