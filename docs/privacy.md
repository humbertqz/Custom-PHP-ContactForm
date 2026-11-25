# Privacidad y retención

Cada instalación guarda sus credenciales y registros de forma independiente. Los datos de formulario se envían únicamente a los destinos habilitados. Monolog registra UUID y códigos de estado. El limitador guarda HMAC de IP o dirección de confirmación, no su texto. Turnstile recibe su token y, cuando puede resolverse de forma fiable, la IP.

| Política local | Comportamiento |
| --- | --- |
| permanent | Conserva submissions hasta que el cliente aplique su política |
| days | Limpieza según LOCAL_RETENTION_DAYS, preservando mensajes con trabajos abiertos |
| until_synced | Limpieza de submissions cuando sus trabajos quedan terminales |
| none | Requiere DATABASE_ENABLED=false |

QUEUE_ENABLED autoriza copias temporales incluso con none. Con la cola apagada no hay persistencia de sus trabajos ni idempotencia de navegador. Con ambos almacenes apagados se siguen pudiendo conservar contadores HMAC y logs técnicos por seguridad. Con una cola compartida y escritura primaria exitosa se referencia la misma fila local. Si la base es independiente, la cola guarda el contenido temporal hasta completar o agotar trabajos. FALLBACK_DELETE_AFTER_SYNC elimina respaldos sincronizados en la siguiente limpieza.

Errores failed y ambiguous son terminales. La cola retira su copia de contenido cuando deja de haber operaciones abiertas y conserva metadatos hasta QUEUE_RETENTION_DAYS. Una copia local sigue la política configurada. Antes de reenviar casos SMTP ambiguos, compara el Message-ID con el servidor SMTP y coordina la revisión manual.

Sheets puede ser privada y propiedad del cliente. Los administradores del servidor tienen acceso potencial a mensajes durante procesamiento y almacenamiento. Para eliminar una instalación, detén cron, elimina la configuración y los datos privados conforme a la política del cliente, revoca las credenciales y gestiona la eliminación en hojas, correo y respaldos. Las instantáneas de cPanel pueden conservar datos tras borrarlos del disco operativo.
