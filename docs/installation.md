# Instalación

## Desarrollo local

Instala las dependencias con composer install. Ejecuta php bin/install.php --non-interactive para crear .env y directorios. La configuración de ejemplo conserva Turnstile activado y requiere sus claves; el CLI termina con error de configuración hasta completarlas. Para un ensayo local exclusivamente con SQLite, modifica .env así:

~~~ini
APP_ENV=local
APP_URL=http://localhost:8765
ALLOWED_ORIGINS=http://localhost:8765
APP_BASE_PATH=
TURNSTILE_ENABLED=false
MAIL_ENABLED=false
GOOGLE_SHEETS_ENABLED=false
DATABASE_ENABLED=true
STORAGE_MODE=database_first
~~~

Ejecuta php bin/install.php y php -S localhost:8765 -t public. Puedes abrir el ejemplo copiándolo a public/ durante el ensayo local. Ajusta action, data-endpoint, data-config-endpoint y el script a /contact.php, /config.php y /assets/contact.js para este servidor. Retira la copia del ejemplo antes de desplegar. Nunca sirvas la raíz del repositorio como raíz web.

## Desarrollo con MAMP

En MAMP selecciona PHP 8.3 o posterior. Para verificar con la misma versión, antepone su directorio bin al PATH de tu terminal. En esta instalación, el directorio de PHP 8.3 es /Applications/MAMP/bin/php/php8.3.30/bin.

Si el checkout está en /Applications/MAMP/htdocs/contact-form y el document root general sigue siendo /Applications/MAMP/htdocs, el archivo .htaccess de la raíz bloquea su acceso en Apache cuando AllowOverride incluye las directivas de autorización. Prepara un host de ensayo con document root exclusivo en /Applications/MAMP/htdocs/contact-form/public. En la configuración Apache del host, deshabilita AllowOverride para el directorio del repositorio y concede acceso local explícitamente a public/. Así el bloqueo de la raíz no se hereda en el host de ensayo. Con MAMP en modo Nginx, usa la misma separación mediante la configuración del servidor, ya que Nginx ignora .htaccess.

Este ejemplo usa un puerto de loopback independiente para mantener los otros sitios de MAMP en su puerto actual.

~~~apache
Listen 127.0.0.1:8892
<VirtualHost 127.0.0.1:8892>
    ServerName localhost
    DocumentRoot "/Applications/MAMP/htdocs/contact-form/public"
    <Directory "/Applications/MAMP/htdocs/contact-form">
        AllowOverride None
        Require all denied
    </Directory>
    <Directory "/Applications/MAMP/htdocs/contact-form/public">
        Options -Indexes
        AllowOverride None
        Require local
    </Directory>
</VirtualHost>
~~~

Usa la URL de ese host en APP_URL y ALLOWED_ORIGINS con APP_ENV=local. Ajusta APP_BASE_PATH a la ruta que sirva el host. Mantén TURNSTILE_ENABLED, MAIL_ENABLED y GOOGLE_SHEETS_ENABLED desactivados para el ensayo SQLite. Antes de crear configuración o datos locales, confirma que el host general deniega el acceso a la copia y que el host de ensayo sirve solo public/.

## Paquete privado Composer

Publica el repositorio como paquete library en un repositorio Composer privado o configúralo como repositorio vcs. Mantén el proyecto Composer del cliente fuera del document root.

~~~bash
composer config repositories.contact-module vcs https://git.example.com/agency/php-contact-module.git
composer require contact-module/php-contact-module:^1.0
php vendor/contact-module/php-contact-module/bin/install.php
~~~

Copia vendor/contact-module/php-contact-module/public/ al subdirectorio público. Configura CONTACT_MODULE_ROOT en el pool o entorno PHP para apuntar al proyecto privado del cliente, o instálalo con el nombre contact-module como hermano de public_html. Mantén los formularios propios dentro de config/forms/ del proyecto cliente. La presencia de esa carpeta sustituye el registro de ejemplos completo, así que copia también los ejemplos que quieras conservar.

## Sin Composer en el servidor

Construye el ZIP en la máquina de desarrollo con composer.lock actualizado. Sube el ZIP al espacio privado, extrae php-contact-module/ y cambia su nombre a contact-module/ cuando uses descubrimiento automático. Instala desde cPanel Terminal, SSH o pide al hosting ejecutar la migración mediante una tarea cron de una sola ejecución. Retira esa tarea después de comprobar salud.
