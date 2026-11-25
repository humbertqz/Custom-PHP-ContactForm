<?php

declare(strict_types=1);

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$format = static fn (mixed $value): string => is_array($value) ? implode(', ', $value) : (string) $value;
$formName = $form['name'] ?? $submission->formId;
$received = str_replace(['T', 'Z'], [' ', ' UTC'], $submission->createdAt);
$message = $format($submission->fields['message'] ?? '');
$contactLabels = ['name' => 'Nombre', 'email' => 'Correo', 'phone' => 'Teléfono', 'subject' => 'Asunto'];
$groups = [
    'Datos del envío' => array_intersect_key($submission->fields, $contactLabels),
    'Campos adicionales' => array_diff_key($submission->fields, $contactLabels + ['message' => true]),
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo mensaje de contacto</title>
</head>
<body style="margin:0;padding:0;background:#f2f5f9;color:#172136;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f2f5f9">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <!--[if mso]>
            <table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td>
            <![endif]-->
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" bgcolor="#ffffff"
                   style="width:100%;max-width:600px;border:1px solid #dce3ed;border-top:4px solid #284d82;
                          text-align:left;font-family:Arial,Helvetica,sans-serif;">
                <tr>
                    <td style="padding:24px 24px 20px;border-bottom:1px solid #dce3ed;">
                        <h1 style="margin:0 0 7px;font-size:24px;line-height:1.25;color:#172136;">
                            Nuevo mensaje de contacto
                        </h1>
                        <p style="margin:0;font-size:14px;line-height:1.5;color:#536278;">
                            <?= $escape($formName) ?>
                        </p>
                    </td>
                </tr>
                <?php if ($message !== '') : ?>
                <tr>
                    <td style="padding:22px 24px 0;">
                        <h2 style="margin:0 0 9px;font-size:16px;line-height:1.4;color:#172136;">
                            <?= $escape($form['fields']['message']['label'] ?? 'Mensaje') ?>
                        </h2>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f5f8fc">
                            <tr>
                                <td style="padding:15px;border-left:3px solid #284d82;font-size:16px;
                                           line-height:1.55;color:#172136;
                                           overflow-wrap:anywhere;word-break:break-word;">
                                    <?= nl2br($escape($message), false) ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <?php endif; ?>
                <?php foreach (array_filter($groups) as $title => $fields) : ?>
                <tr>
                    <td style="padding:22px 24px 0;">
                        <h2 style="margin:0 0 9px;font-size:16px;line-height:1.4;color:#172136;">
                            <?= $escape($title) ?>
                        </h2>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="border-top:1px solid #dce3ed;">
                            <?php foreach ($fields as $name => $value) : ?>
                                <?php
                                $label = $form['fields'][$name]['label']
                                    ?? $contactLabels[$name] ?? ($name === 'utm' ? 'UTM' : $name);
                                ?>
                            <tr>
                                <td style="padding:9px 0;border-bottom:1px solid #dce3ed;
                                           overflow-wrap:anywhere;word-break:break-word;">
                                    <p style="margin:0 0 2px;font-size:12px;line-height:1.4;font-weight:bold;
                                              color:#536278;">
                                        <?= $escape($label) ?>
                                    </p>
                                    <p style="margin:0;font-size:14px;line-height:1.45;color:#172136;">
                                        <?= nl2br($escape($format($value)), false) ?>
                                    </p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td style="padding:22px 24px;font-size:12px;line-height:1.55;color:#536278;
                               overflow-wrap:anywhere;word-break:break-word;">
                        <p style="margin:0 0 4px;">Recibido: <?= $escape($received) ?></p>
                        <p style="margin:0;">Referencia: <?= $escape($submission->id) ?></p>
                    </td>
                </tr>
            </table>
            <!--[if mso]>
            </td></tr></table>
            <![endif]-->
        </td>
    </tr>
</table>
</body>
</html>
