# Instalar en cPanel sin Terminal

Usa `contact-cpanel-v1.1.0.zip` para una instalación nueva con SQLite. El paquete contiene las dependencias PHP, bases SQLite vacías con sus tablas y un fragmento HTML. Usa PHP 8.3 o posterior con PDO SQLite en el dominio.

1. **Sube y extrae el ZIP.** En File Manager, abre la carpeta principal de la cuenta, donde ya ves `public_html/`. Extrae ahí el ZIP. Aparecerán `contact-module/` y `public_html/contact/`. Conserva esta estructura. Si el dominio usa otra raíz pública o ya existen archivos en esas rutas, usa la guía de instalación manual para elegir las rutas sin sobrescribir una instalación existente. [Instalación manual](hosting-installation.md)
2. **Completa tres valores.** En File Manager, activa **Show Hidden Files**, abre `contact-module/.env` y completa `APP_URL`, `TURNSTILE_SITE_KEY` y `TURNSTILE_SECRET_KEY`. Usa la URL HTTPS exacta, incluido `www` si corresponde. El módulo toma de ahí el hostname de Turnstile y deriva la clave privada del limitador del secreto del widget. [Configurar Turnstile](turnstile.md)
3. **Pega el formulario.** Copia el contenido de `contact-module/examples/embed-form.html` en la página del sitio donde quieras mostrarlo. Conserva la etiqueta `<script>`. Abre la página por HTTPS y envía un mensaje de prueba.

Para revisar la configuración desde el navegador, abre `https://tudominio.com/contact/config.php?form_id=contact`. Debe responder JSON con `"success": true`. Al enviar el formulario, espera un mensaje de confirmación y un `submission_id` en la respuesta. Los envíos se guardan en `contact-module/storage/database/contacts.sqlite`. El usuario con el que corre PHP debe poder escribir en `contact-module/storage/`.

Este ZIP está preparado para la ruta `public_html/contact/`. Mantén `contact-module/` al lado de `public_html/`, así las claves y los datos quedan dentro de la cuenta del sitio, fuera de la raíz pública. Cada instalación debe usar su propio widget. Si rotas su secreto, se reinician los contadores del limitador asociados a la clave anterior.

Si quieres consultar los envíos desde el navegador, activa el panel privado de SQLite. [Configurar el usuario y la contraseña](sqlite-admin.md)

## Añadir la captura UTM a una instalación existente

Extrae el ZIP en una carpeta temporal. Copia estos archivos a las mismas rutas de la instalación:

- `contact-module/src/Config/FormRegistry.php`
- `contact-module/src/Http/ConfigController.php`
- `contact-module/src/Http/ContactController.php`
- `contact-module/src/Support/UtmParameters.php`
- `public_html/contact/assets/contact.js`

Conserva el `.env` y la carpeta `storage/` de tu instalación. El cambio usa el campo de datos adicionales y funciona con la base existente. Puedes conservar el mismo formulario HTML. Si el navegador tiene una copia anterior de `contact.js` en caché, añade `?v=1.0.1` al final de la URL del script.

Prueba abriendo la página del formulario con `?utm_source=google&utm_medium=cpc&utm_campaign=verano`. Tras el envío, SQLite guarda un solo campo `utm` dentro de `extra_fields`. Si usas Google Sheets, actualiza también la versión desplegada del Apps Script con `contact-module/integrations/google-apps-script/Code.gs`. El campo aparecerá dentro de la celda **Campos adicionales**. [Parámetros y comportamiento](forms.md#parámetros-utm)

## Generar el ZIP

~~~bash
composer check
php bin/build-release.php --version=1.1.0 --cpanel
~~~
