# Consultar los mensajes de SQLite

El panel permite iniciar sesión con una cuenta por instalación y abrir los mensajes ya guardados. La bandeja incluye fecha, formulario, datos de contacto, mensaje y campos adicionales como UTM. El panel usa una conexión SQLite de solo lectura para los mensajes.

## Activar la cuenta

1. Usa la versión 1.1.0 del módulo. Si el sitio ya está instalado, sigue la actualización selectiva de abajo.
2. En tu computadora, abre Terminal y navega a la carpeta privada del ZIP extraído. Ejecuta `php bin/admin-password.php` y escribe una contraseña exclusiva para este panel. El comando imprime la línea `ADMIN_PASSWORD_HASH` lista para copiar.
3. En el File Manager del hosting, abre el archivo privado `contact-module/.env`. Agrega estas líneas y usa el hash generado, con las comillas simples.

~~~ini
ADMIN_ENABLED=true
ADMIN_USERNAME=operador
ADMIN_PASSWORD_HASH='PEGA_AQUÍ_EL_HASH_GENERADO'
~~~

Elige un usuario de 3 a 64 caracteres alfanuméricos. También puedes usar punto, guion, guion bajo y arroba. El archivo `.env` guarda el hash de la contraseña. Para cambiarla, genera otro hash y reemplaza el valor. El cambio invalida las sesiones anteriores.

4. Abre `https://tudominio.com/contact/admin.php` y entra con el usuario y la contraseña que elegiste. Puedes cerrar la sesión con el botón de la bandeja.

El panel requiere almacenamiento SQLite y limitación activa con SQLite, como en el ZIP rápido de cPanel. En hosting público, el servidor PHP debe recibir la señal HTTPS del servidor web. El panel queda desactivado por defecto. Usa `ADMIN_ENABLED=false` para desactivarlo.

## Agregar el panel a un sitio que ya funciona

Extrae el ZIP actualizado en una carpeta de trabajo. Copia los siguientes archivos en sus rutas correspondientes de la instalación, conservando el `.env` y la carpeta `storage/` del sitio.

- `contact-module/.env.example`
- `contact-module/src/Database/Connection.php`
- `contact-module/src/Http/AdminPanel.php`
- `contact-module/templates/admin.php`
- `public_html/contact/admin.php`
- `public_html/contact/loader.php`

En la instalación por alias de Apache, usa las dos rutas web en `contact/public/`. Agrega las credenciales a tu `.env` existente. El panel usa las tablas actuales y funciona con los mensajes guardados antes de la actualización. [Instalación rápida](quick-cpanel.md)
