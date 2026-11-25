# Solución de problemas

- 500 tras copiar archivos. Ejecuta healthcheck en CLI y comprueba ubicación de CONTACT_MODULE_ROOT, autoload y .env. El loader requiere raíces privadas.
- 403 Turnstile. Comprueba site/secret del mismo widget, hostname exacto y action del registro. Un token usado o expirado requiere un nuevo widget.
- 503 Turnstile o Sheets. Comprueba conectividad TLS, bloqueo del hosting y estado de dependencias externas. Para Sheets revisa /exec, propiedades, permisos del despliegue y pestaña.
- 503 SMTP. Verifica SSL o STARTTLS, puerto permitido y autorización del From. La aceptación SMTP no garantiza entrega en bandeja. Revisa SPF, DKIM y DMARC con tu proveedor.
- 422. Compara nombres y opciones con el esquema PHP. Usa el HTML correspondiente y evita nombrar el botón submit si el nombre no está previsto en el esquema.
- 429. Ajusta límites de origen y formulario según NAT/proxy y volumen esperado. Confirma que PHP recibe la cadena de proxy correcta.
- Cola pendiente. Comprueba zona horaria/sistema, cron, permisos y lease. Si existe retry_scheduled, espera su próxima fecha. Una entrega ambiguous de SMTP requiere revisión manual. Busca por UUID o Message-ID.
- SQLite locked. Revisa almacenamiento local con WAL y que los procesos compartan el mismo archivo. Evita sincronización de archivos SQLite en red durante su uso.
- Google indica CONFIGURATION. Verifica CONTACT_SPREADSHEET_ID, CONTACT_FORM_MAP, pestaña y las primeras diez columnas. Las filas manuales no se sobrescriben.
- Dependencias no instaladas. Usa composer install con conectividad o un ZIP de producción que incluya vendor/.
