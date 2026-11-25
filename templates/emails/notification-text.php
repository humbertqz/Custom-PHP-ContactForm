<?php

declare(strict_types=1);

$format = static fn (mixed $value): string => is_array($value) ? implode(', ', $value) : (string) $value;
$message = $format($submission->fields['message'] ?? '');
$contactLabels = ['name' => 'Nombre', 'email' => 'Correo', 'phone' => 'Teléfono', 'subject' => 'Asunto'];
$groups = [
    'Datos del envío' => array_intersect_key($submission->fields, $contactLabels),
    'Campos adicionales' => array_diff_key($submission->fields, $contactLabels + ['message' => true]),
];

echo "Nuevo mensaje de contacto\n", ($form['name'] ?? $submission->formId), "\n\n";

if ($message !== '') {
    echo ($form['fields']['message']['label'] ?? 'Mensaje'), "\n", $message, "\n\n";
}

foreach (array_filter($groups) as $title => $fields) {
    echo $title, "\n";
    foreach ($fields as $name => $value) {
        $label = $form['fields'][$name]['label'] ?? $contactLabels[$name] ?? ($name === 'utm' ? 'UTM' : $name);
        echo $label, ': ', $format($value), "\n";
    }
    echo "\n";
}

echo 'Recibido: ', str_replace(['T', 'Z'], [' ', ' UTC'], $submission->createdAt), "\n";
echo 'Referencia: ', $submission->id, "\n";
