<?php

declare(strict_types=1);

return [
    'id' => 'support',
    'name' => 'Soporte',
    'fields' => [
        'name' => ['label' => 'Nombre', 'type' => 'text', 'required' => true, 'max_length' => 150],
        'email' => ['label' => 'Correo electrónico', 'type' => 'email', 'required' => true],
        'message' => ['label' => 'Mensaje', 'type' => 'textarea', 'required' => true, 'max_length' => 5000],
        'topics' => ['label' => 'Áreas de interés', 'type' => 'checkbox_group', 'options' => ['billing', 'support']],
    ],
    'mail' => ['subject' => 'Solicitud de soporte'],
];
