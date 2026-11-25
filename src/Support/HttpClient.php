<?php

declare(strict_types=1);

namespace ContactModule\Support;

class HttpClient
{
    /**
     * @param list<string> $headers
     * @return array{status:int,body:string,location:?string}
     */
    public function request(
        string $method,
        string $url,
        ?string $body,
        int $timeout,
        array $headers = ['Content-Type: application/json'],
    ): array {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('HTTP_INIT');
        }
        $location = null;
        $response = '';
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$location): int {
                if (str_starts_with(strtolower($header), 'location:')) {
                    $location = trim(substr($header, 9));
                }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > 1048576) {
                    return 0;
                }
                $response .= $chunk;
                return strlen($chunk);
            },
        ];
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'HTTPS';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if (!curl_setopt_array($curl, $options)) {
            throw new \RuntimeException('HTTP_OPTIONS');
        }
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($ok === false) {
            throw new \RuntimeException('HTTP_TRANSPORT');
        }
        return ['status' => $status, 'body' => $response, 'location' => $location];
    }
}
