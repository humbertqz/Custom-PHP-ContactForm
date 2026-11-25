# API HTTP

GET /contact/config.php?form_id=contact devuelve form_id, opciones públicas de Turnstile y Honeypot. Con CSRF devuelve csrf_token y Cache-Control no-store. En otro caso permite caché privada corta. Los cambios de hostname, credenciales y rutas se aplican en el backend.

`capture_utm` indica que el formulario admite el campo opcional `utm` en el cuerpo del POST. Envía los parámetros como una cadena de consulta sin el signo `?`. El servidor filtra y normaliza los valores antes de guardarlos. [Parámetros admitidos](forms.md#parámetros-utm)

POST /contact/contact.php admite application/json, application/x-www-form-urlencoded y multipart/form-data sin archivos. Envía form_id, los campos permitidos, cf-turnstile-response cuando corresponda y csrf_token si está habilitado. El origen se toma del Referer autorizado y se guarda sin consulta ni fragmento. Idempotency-Key acepta 20 a 128 caracteres alfanuméricos, guion y guion bajo con cola habilitada. El alcance incluye el formulario y el contenido normalizado. La clave se reconoce mientras exista el sobre de cola. Repite con un token CAPTCHA nuevo.

~~~json
{"form_id":"contact","name":"Juan Pérez","email":"juan@example.com","message":"Solicito información"}
~~~

| Código | Resultado |
| --- | --- |
| 200 | Destino requerido aceptó según la estrategia |
| 202 | Existe copia persistente y sigue pendiente un trabajo |
| 400 | Cuerpo, consulta o clave de idempotencia inválidos |
| 403 | Origen, honeypot, sesión o CAPTCHA rechazado |
| 405 | Método no admitido |
| 409 | Clave de idempotencia reutilizada con contenido diferente |
| 413 | Cuerpo excede el límite |
| 415 | Tipo de contenido no admitido |
| 422 | Formulario o campos inválidos |
| 429 | Límite agotado, con Retry-After |
| 500 | Error interno o configuración inválida |
| 503 | Almacenamiento, transporte requerido o verificación temporalmente indisponible |

Éxito incluye success, message y submission_id, y en 202 incluye status: accepted. Un 422 incluye errors cuando hay errores de campos. Respuesta 202 confirma aceptación para procesamiento posterior. Algunos destinos pueden haber aceptado una entrega antes de un 503 sin cola. CORS acepta las cabeceras Content-Type e Idempotency-Key para orígenes configurados.
