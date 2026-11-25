# Arquitectura

Los controladores validan el transporte y delegan en un registro de formularios y un coordinador. Cada adaptador devuelve un resultado estructurado: completed, temporary, failed o ambiguous. La cola registra estados por UUID y canal. El procesador aplica la política de reintentos y usa PDO con un token de reclamación como condición de actualización. Apps Script usa UUID y un bloqueo común para operaciones de anexado.

| Modo | DB | Sheets | SMTP | Éxito inmediato requerido |
| --- | --- | --- | --- | --- |
| email_only | No | No | Sí | Notificación SMTP aceptada |
| google_sheets_only | No | Sí | Opcional | Sheets confirma el UUID |
| database_only | Sí | No | Opcional | Escritura local |
| parallel | Sí | Sí | Opcional | Ambos destinos |
| fallback | Sí | Sí | Opcional | Sheets o respaldo local |
| database_first | Sí | Opcional | Opcional | Escritura local |

La cola optativa permite 202 si queda trabajo recuperable; también registra SMTP opcional por separado. Sin cola, parallel devuelve 503 ante entrega parcial y los modos con destino principal mantienen su resultado si falla un canal secundario. database_first escribe la copia principal antes de las entregas externas y usa una sola transacción cuando comparte conexión con la cola. Dos bases independientes no tienen transacción distribuida. El almacenamiento local de mensajes y la cola requieren autorizaciones independientes. La cola separada guarda una copia temporal; una cola compartida usa referencias cuando es posible.

El origen se valida por lista explícita y el proxy por dirección exacta o CIDR. Las IP utilizadas por el limitador se transforman con HMAC. CAPTCHA falla de forma cerrada. El servidor aísla credenciales y datos de la raíz web y registra códigos técnicos. El trabajador nunca repite un SMTP ambiguo automáticamente. Se eliminan copias temporales terminales según retención; failed y ambiguous conservan metadatos durante el período operativo. Las políticas del cliente rigen las copias de Google y SMTP.

## Límites y estructura

Los resultados accepted implican trabajo persistente abierto. En estrategias con destino primario, un canal secundario fallido definitivamente no cambia el éxito del destino primario. Parallel exige que ambos destinos estén confirmados o todavía puedan llegar a confirmarse mediante la cola. Si hay una entrega parcial sin cola, un 503 no revierte la entrega ya aceptada. En fallback se procesa Sheets primero y el trabajo de base local solo escribe tras un fallo en Sheets. Después de sincronizar, la política se aplica por la herramienta de limpieza.

El modelo usa entregas por canal, leases y un registro temporal común. Una reclamación compara estado e intento al actualizar. El token protege contra escrituras tardías. Un worker recupera trabajos abandonados de DB y Sheets según su presupuesto de intentos. SMTP abandonado queda ambiguo. Un resultado ambiguo de Sheets se reintenta con el mismo UUID.

La configuración de transporte se agrupa en ConfigLoader, ConfigValidator y el archivo de entorno. Los adaptadores PDO abstraen las consultas que difieren entre motores y la cola usa su implementación de base de datos directamente. Las excepciones se ubican junto al componente que las maneja. Estos cambios reducen los archivos de configuración duplicados y facilitan ejecutar el módulo como biblioteca privada o como ZIP autónomo.
