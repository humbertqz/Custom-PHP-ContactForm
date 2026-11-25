# PHP Contact Module

Módulo para conectar formularios HTML con un sitio PHP. Recibe envíos, los guarda en una base de datos o en Google Sheets y puede enviar notificaciones por correo. Cada instalación administra su configuración y datos.

- **Formularios.** Campos configurables, varios formularios por sitio y captura opcional de UTM en un solo campo por envío.
- **Protección.** Cloudflare Turnstile, honeypot, límites persistentes de envío y validación en el servidor.
- **Almacenamiento y entregas.** SQLite, MySQL o MariaDB, Google Sheets mediante Apps Script y SMTP con PHPMailer. La cola opcional permite reintentos mediante cron.
- **Consulta.** Panel privado de solo lectura con inicio de sesión para los mensajes guardados en SQLite.
- **Frontend.** JavaScript sin compilación. El sitio define el HTML y el estilo del formulario.

## Instalación

Para un sitio en cPanel con SQLite, la opción más directa es el **ZIP rápido de cPanel**. Se extrae desde File Manager en la carpeta de la cuenta donde está `public_html/`. Incluye las dependencias PHP y las tablas inicializadas. Después se completan el dominio y las claves de Turnstile en el archivo privado `.env` y se pega el formulario. [Guía rápida de cPanel](docs/quick-cpanel.md) · [Fragmento HTML](examples/embed-form.html)

| Entorno | Guía |
| --- | --- |
| cPanel con instalación manual | [Qué subir y en qué carpetas](docs/hosting-installation.md) |
| Una carpeta en el proyecto del sitio con un alias de Apache | [Instalación en una sola carpeta](docs/single-folder-installation.md) |
| Desarrollo local, MAMP, SSH o Composer privado | [Instalación](docs/installation.md) |
| Sitio con el módulo instalado | [Actualización y respaldo](docs/updating.md) |

### Requisitos

PHP 8.3 o superior con cURL, mbstring, OpenSSL, PDO, session, ctype, filter y hash. Habilita `pdo_sqlite` o `pdo_mysql` según los motores elegidos. La limitación con SQLite requiere SQLite 3.35 o posterior. En un sitio público, usa HTTPS y permite las conexiones salientes que correspondan a Turnstile, Google Sheets o SMTP.

Sirve únicamente los archivos de `public/` y guarda `.env`, las bases SQLite y los respaldos fuera del directorio público. Composer y `ext-zip` se usan al construir el paquete. El ZIP ya incluye las dependencias para el hosting. [Seguridad](docs/security.md) · [Configuración](docs/configuration.md)

## Ejemplo HTML

~~~html
<form action="/contact/contact.php" method="post" data-contact-form data-form-id="contact"
      data-endpoint="/contact/contact.php" data-config-endpoint="/contact/config.php">
  <label>Nombre <input name="name" required></label>
  <label>Correo <input name="email" type="email" required></label>
  <label>Mensaje <textarea name="message" required></textarea></label>
  <div data-contact-turnstile></div>
  <button type="submit">Enviar mensaje</button>
  <p data-contact-feedback aria-live="polite"></p>
</form>
<script src="/contact/assets/contact.js" defer></script>
~~~

[Ejemplo con varios formularios](examples/multiple-forms.html) · [Campos, UTM y eventos JavaScript](docs/forms.md) · [API HTTP](docs/api.md)

## Configuración y operación

| Tema | Guía |
| --- | --- |
| Dominio, SMTP, destinatarios y plantillas | [Configuración](docs/configuration.md) |
| SQLite, MySQL y MariaDB | [Bases de datos](docs/database.md) |
| Inicio de sesión y consulta de mensajes en SQLite | [Panel de mensajes](docs/sqlite-admin.md) |
| Hoja privada del cliente y despliegue de Apps Script | [Google Sheets](docs/google-sheets.md) |
| Widgets, hostnames y rotación de claves | [Turnstile](docs/turnstile.md) |
| Destinos obligatorios, almacenamiento de respaldo y reintentos | [Arquitectura y políticas de entrega](docs/architecture.md) |
| Apache, Nginx y cron | [Despliegue](docs/deployment.md) |
| Datos personales y retención | [Privacidad](docs/privacy.md) |
| Conectividad y fallos de instalación | [Solución de problemas](docs/troubleshooting.md) |

## Desarrollo

Desde la raíz del proyecto:

~~~bash
composer install
composer check
node --test tests/*.test.cjs
~~~

`composer check` ejecuta PHPUnit, PHPStan y PHP_CodeSniffer. Node se usa para las pruebas de JavaScript, incluido Apps Script simulado. Las pruebas predeterminadas usan SQLite temporal y dobles para los servicios externos. Las pruebas opcionales de MySQL o MariaDB usan una base de ensayo con sufijo `_test` configurada mediante las variables `CONTACT_TEST_MYSQL_*`. [Verificación](docs/verification.md) · [Historial de cambios](CHANGELOG.md)

## Generar los ZIP

Ejecuta `composer check` antes de generar un paquete con `bin/build-release.php`. Usa el número de la versión que quieras empaquetar. Por ejemplo, con 1.1.1:

| Paquete | Comando | Archivo en dist/ |
| --- | --- | --- |
| cPanel rápido | `php bin/build-release.php --version=1.1.1 --cpanel` | contact-cpanel-v1.1.1.zip |
| Carpeta única con alias de Apache | `php bin/build-release.php --version=1.1.1 --site-folder` | contact-folder-v1.1.1.zip |
| Instalación manual | `php bin/build-release.php --version=1.1.1` | php-contact-module-v1.1.1.zip |

Los ZIP se construyen desde `composer.lock` con las dependencias de producción. `dist/` está excluido del repositorio. Para compartir una versión desde GitHub, adjunta el ZIP a su publicación en Releases. [Guía de distribución](docs/deployment.md#zip)

## Licencia

La decisión de licencia está pendiente. Consulta al titular antes de distribuir el código. [Archivo de licencia](LICENSE)
