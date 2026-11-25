<?php

declare(strict_types=1);

$form = require __DIR__ . '/contact.php';
$form['id'] = 'quotation';
$form['name'] = 'Solicitud de cotización';
$form['fields']['company'] = ['label' => 'Empresa', 'type' => 'text', 'max_length' => 150];
$form['fields']['service'] = [
    'label' => 'Servicio de interés', 'type' => 'select', 'required' => true,
    'options' => ['web-design', 'ecommerce', 'seo'],
];
$form['mail']['subject'] = 'Nueva solicitud de cotización';

return $form;
