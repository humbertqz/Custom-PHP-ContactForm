# Configuración de la instalación

El módulo carga los valores base de .env.example, luego .env del cliente y finalmente las variables del proceso PHP definidas para las mismas claves. Usa APP_ENV=production con orígenes HTTPS y APP_BASE_PATH con la ruta de las cookies CSRF. APP_NAME, APP_TIMEZONE y APP_LOCALE identifican la instalación. Las fechas de sistemas se almacenan en UTC y los mensajes predeterminados están en español. APP_DEBUG jamás publica trazas.

## SMTP

Establece MAIL_ENABLED=true, MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD y MAIL_FROM_ADDRESS autorizado. Usa MAIL_ENCRYPTION=tls para STARTTLS, habitualmente puerto 587, y ssl para SMTPS, habitualmente 465. MAIL_TO_ADDRESS, MAIL_CC y MAIL_BCC son listas de direcciones separadas por coma. Los formularios pueden sustituirlas mediante mail.to, mail.cc y mail.bcc. Reply-To usa el correo validado del visitante. Un resultado completed significa aceptación SMTP.

MAIL_CONFIRMATION_ENABLED activa respuestas de texto fijo y exige Turnstile y limitación persistente. Limita cada dirección validada mediante CONFIRMATION_MAX_PER_HOUR. El destinatario de la confirmación controla su dirección de entrada, así que conservar los límites y la protección CAPTCHA es importante. Las confirmaciones esperan a que se cumpla el criterio de entrega del formulario. Las plantillas de confirmación incluidas contienen texto fijo. Para evitar reflejar contenido hacia terceros, las plantillas de confirmación propias reciben únicamente UUID, formulario y hora. Las plantillas personalizadas de notificación requieren pares HTML y texto, por ejemplo templates/emails/sales.php y sales-text.php, y escape contextual de todos los valores del visitante.

## Almacenamiento y entrega

Activa DATABASE_ENABLED o GOOGLE_SHEETS_ENABLED según STORAGE_MODE. Configura los tres motores de persistencia de manera independiente mediante DB_DRIVER, QUEUE_DRIVER y RATE_LIMIT_DRIVER. Los motores MySQL y MariaDB reutilizan DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME y DB_PASSWORD. Para SQLite, DB_PATH y QUEUE_DB_PATH admiten rutas bajo storage/ sin segmentos ascendentes ni enlaces simbólicos. Usa la misma ruta para transacciones compartidas de database_first. La limitación SQLite usa storage/cache/rate.sqlite.

La cola está deshabilitada por defecto. Activa QUEUE_ENABLED solo si la instalación autoriza almacenamiento temporal. QUEUE_PROCESS_INLINE deja que la solicitud intente los trabajos nuevos. Cron sigue siendo necesario para progreso posterior. RETRY_INTERVALS_MINUTES se aplica después de cada intento, según RETRY_MAX_ATTEMPTS. QUEUE_MAX_RUN_SECONDS limita el inicio de trabajos nuevos, y cada transporte conserva sus propios timeouts. LOG_LEVEL controla Monolog. La limpieza aplica LOG_RETENTION_DAYS, LOCAL_RETENTION_DAYS y QUEUE_RETENTION_DAYS.

## Seguridad y mensajes

Configura secretos con un editor privado o variables de entorno. Genera RATE_LIMIT_KEY con el instalador o php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'. Define ALLOWED_ORIGINS explícitos, ajusta MAX_REQUEST_SIZE_KB en PHP y el servidor y configura TRUSTED_PROXIES solo si la infraestructura lo requiere. Configura TURNSTILE_ALLOWED_HOSTNAMES con nombres exactos, incluidos los de frontends separados.

Usa config/messages.php del cliente para sustituir entradas del paquete. Los campos del formulario pueden definir mensajes de éxito y validación. Los mensajes JavaScript de carga, espera y error de red se pueden personalizar con data-contact-loading, data-contact-checking, data-contact-error y data-contact-unavailable.
