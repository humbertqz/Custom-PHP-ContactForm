<?php

declare(strict_types=1);

namespace ContactModule\Http;

final class HttpException extends \RuntimeException
{
    /** @param array<string,string> $headers */
    public function __construct(public int $status, public string $messageKey, public array $headers = [])
    {
        parent::__construct($messageKey);
    }
}
