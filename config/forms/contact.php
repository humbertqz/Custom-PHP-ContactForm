<?php

declare(strict_types=1);

return [
    'id' => 'contact',
    'name' => 'Contacto',
    'fields' => [
        'name' => ['label' => 'Nombre', 'type' => 'text', 'required' => true, 'max_length' => 150],
        'email' => ['label' => 'Correo electrónico', 'type' => 'email', 'required' => true],
        'phone' => ['label' => 'Teléfono', 'type' => 'tel', 'max_length' => 30],
        'subject' => ['label' => 'Asunto', 'type' => 'text', 'max_length' => 150],
        'message' => ['label' => 'Mensaje', 'type' => 'textarea', 'required' => true, 'max_length' => 5000],
    ],
    'mail' => ['subject' => 'Nuevo mensaje de contacto'],
];
