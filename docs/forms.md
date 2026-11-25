# Formularios y frontend

Cada archivo PHP de config/forms/ privado retorna un arreglo. El servidor carga el directorio, registra el id literal y lo busca en un mapa. Copia config/forms/contact.php como punto de partida. Para el ejemplo personalizado, instala examples/support-form.php como config/forms/support.php del cliente y sirve examples/custom-form.html.

| Clave | Valores |
| --- | --- |
| id, name | Identificador único con minúsculas y nombre descriptivo |
| fields | Mapa de nombres a reglas, con label y type |
| type | text, email, tel, number, textarea, select, radio, checkbox, checkbox_group, date, url, hidden |
| required, min_length, max_length | Reglas por campo |
| min, max, pattern | Rango numérico y expresión regular PHP de servidor |
| options | Lista explícita para select, radio y checkbox_group |
| multiple | Lista de valores; los nombres HTML pueden usar [] |
| default, normalize | Valor predeterminado y trim, none o lowercase |
| mail | to, cc, bcc, subject, template, confirmation_subject, confirmation_template |
| messages | success y validation |
| channels | database, google_sheets, email_notification, email_confirmation |
| turnstile_action | Acción esperada, hasta 32 caracteres compatibles |
| sheets_route | Identificador que coincide con route en el mapa del Apps Script |
| rate_limit | Límite por IP y formulario durante la ventana configurada |

Los canales por formulario solo pueden restringir los destinos globales. Los canales obligatorios del modo deben seguir habilitados. El tipo hidden recibe la misma validación que los otros campos y sus valores solo se guardan como datos. El backend rechaza campos no declarados. Usa nombres de campo formados por minúsculas, dígitos y guion bajo. Las casillas simples envían on, 1 o true. Las fechas usan YYYY-MM-DD y las URL admiten HTTP y HTTPS.

Usa method="post" y action en el HTML para que un envío sin JavaScript no coloque datos del visitante en la URL. La experiencia de CAPTCHA, feedback y JSON del frontend requiere JavaScript.

## Parámetros UTM

Al enviar el formulario, el fragmento HTML y `contact.js` capturan los parámetros de campaña de la página actual. Se admiten `utm_source`, `utm_medium`, `utm_campaign`, `utm_id`, `utm_term`, `utm_content`, `utm_source_platform`, `utm_creative_format` y `utm_marketing_tactic`. Se toma el primer valor válido de cada parámetro, con un máximo de 256 bytes por valor. Los demás parámetros de la URL se descartan.

El envío guarda un solo campo opcional `utm` como texto, por ejemplo `utm_campaign=verano&utm_medium=cpc&utm_source=google`. En SQLite o MySQL queda dentro de `submissions.extra_fields`. En Google Sheets queda en la celda **Campos adicionales** al usar el Apps Script actualizado. Si declaraste un campo personalizado `utm`, el módulo conserva su comportamiento.

## Eventos y accesibilidad

Usa data-contact-feedback para el mensaje general y data-contact-error-for="email" en un elemento referenciado con aria-describedby. El estado del formulario es data-contact-state, con idle, loading, success o error. El script utiliza aria-busy y aria-invalid y enfoca el primer campo con error. El sitio controla colores y tipografía.

| Evento | detail |
| --- | --- |
| contact:ready | formId |
| contact:before-submit | formId; permite event.preventDefault() |
| contact:success | formId, status y response |
| contact:error | formId, status y response si el servidor respondió |
| contact:complete | formId |

Usa window.ContactModule.initialize(nodo) para formularios añadidos dinámicamente. El SDK se comparte y cada formulario recibe su propio widget. Configura data-contact-timeout en milisegundos si el timeout por defecto de 60000 resulta insuficiente. Para WordPress o Elementor, usa HTML personalizado y carga el script una sola vez desde el tema, un snippet controlado o el gestor de scripts. Sirve el backend como instalación independiente.
