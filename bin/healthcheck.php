<?php

declare(strict_types=1);

use ContactModule\Config\ConfigurationException;
use ContactModule\Services\HealthCheckService;

require __DIR__ . '/common.php';

contactCommand(static function (): int {
    $options = getopt('', ['json', 'check-connections']);
    try {
        $app = contactApplication();
        $result = (new HealthCheckService())->check($app, isset($options['check-connections']));
    } catch (ConfigurationException $error) {
        $result = ['ok' => false, 'config' => 'invalid', 'detail' => $error->getMessage()];
    } catch (Throwable $error) {
        $result = ['ok' => false, 'error' => $error::class, 'code' => (string) $error->getCode()];
    }
    if (isset($options['json'])) {
        contactJson($result);
    } else {
        foreach ($result as $key => $value) {
            echo $key, ' ', is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR), PHP_EOL;
        }
    }
    return $result['ok'] ? 0 : 1;
});
