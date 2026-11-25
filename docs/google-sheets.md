# Conectar una hoja privada de Google

El cliente realiza estos pasos desde su propia cuenta.

1. Crea un Spreadsheet y conserva el acceso general en Restringido. Crea una pestaña Contactos y, si corresponde, Cotizaciones. El script instala encabezados en pestañas vacías. Con una pestaña existente, usa los encabezados exactos del archivo Code.gs en sus primeras diez columnas y reserva las columnas once y doce para Estado y Observaciones.
2. Abre Extensiones → Apps Script. Pega el contenido de integrations/google-apps-script/Code.gs. En Configuración del proyecto, habilita la visualización del manifiesto si vas a sustituir appsscript.json.
3. Copia el ID de la hoja desde el segmento situado entre /d/ y /edit en su URL. Añade CONTACT_SPREADSHEET_ID en Propiedades de la secuencia de comandos.
4. Genera un token de alta entropía con un gestor de contraseñas o con php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'. Guárdalo como CONTACT_TOKEN. Usa caracteres alfanuméricos, guion o guion bajo, con al menos 32 caracteres.
5. Define CONTACT_FORM_MAP como JSON en una sola línea. Ejemplo:

~~~json
{"contact":{"tab":"Contactos","fields":["name","email","phone","subject","message"]},"quotation":{"tab":"Cotizaciones","fields":["name","email","phone","subject","message","company","service"]}}
~~~

6. Usa Implementar → Nueva implementación → Aplicación web. Selecciona Ejecutar como yo y acceso que permita llamadas HTTPS del backend sin una cuenta Google, normalmente Cualquier persona. Autoriza el script desde tu cuenta. Una política de Google Workspace puede limitar esta modalidad.
7. Copia la URL aplicable a producción, https://script.google.com/macros/s/.../exec. Comparte solo esta URL y el token con el administrador mediante un canal privado. Este configura GOOGLE_SHEETS_ENABLED, GOOGLE_SCRIPT_URL y GOOGLE_SCRIPT_TOKEN.
8. Realiza un envío autorizado y verifica la fila. Mantén estables las pestañas y el mapa mientras haya reintentos pendientes. Conserva la columna Submission ID en las filas existentes para la deduplicación.
9. Para revocar la integración, desactiva el despliegue o cambia CONTACT_TOKEN e invalida el anterior. Actualiza el secreto del servidor para reanudar los envíos. Gestiona las filas y los accesos desde tu cuenta.

El endpoint puede estar disponible públicamente al desplegarse. La hoja conserva sus permisos privados. El endpoint acepta anexados autenticados y devuelve UUID y estado. El cliente conserva acceso a la hoja y a las columnas de seguimiento. Una ruta alternativa requiere sheets_route en el formulario PHP y route en la entrada del mapa, con un nombre autorizado y una pestaña fija.

Apps Script puede redirigir la lectura de su respuesta JSON a script.googleusercontent.com. El cliente HTTP realiza esa consulta por GET sin enviar el token. Un timeout después del POST deja el resultado incierto y el reintento reutiliza el UUID. Las cuotas dependen de la cuenta y pueden cambiar. Pueden afectar tiempos, bloqueos y capacidad total. [Web apps de Apps Script](https://developers.google.com/apps-script/guides/web) · [Content Service](https://developers.google.com/apps-script/guides/content) · [Cuotas oficiales](https://developers.google.com/apps-script/guides/services/quotas).
