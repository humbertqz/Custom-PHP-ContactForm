# Instalar el formulario de contacto en un sitio web

Esta guía prepara un primer envío en un hosting con cPanel, SQLite y Cloudflare Turnstile. Usa el archivo `php-contact-module-v1.0.0.zip` de `dist/`. El ZIP incluye las dependencias PHP necesarias para instalarlo en el hosting sin Composer.

Si tu servidor permite publicar un alias, también puedes instalar el módulo como una sola carpeta dentro del proyecto del sitio. [Instalación en una sola carpeta](single-folder-installation.md)

## 1. Sube los archivos

1. En **File Manager** de cPanel, sube el ZIP a `/home/cliente/`, un nivel arriba de `public_html`. Sustituye `cliente` por el usuario de tu cuenta de hosting.
2. Extrae el ZIP en esa carpeta y renombra `php-contact-module` a `contact-module`.
3. Crea `public_html/contact/` y copia allí **el contenido** de `/home/cliente/contact-module/public/`. Copia los tres archivos PHP y la carpeta `assets/`.
4. Copia `/home/cliente/contact-module/examples/basic-form.html` a `public_html/contacto-prueba.html`. El ejemplo ya apunta a los endpoints y al JavaScript de `/contact/`.

~~~text
/home/cliente/
├── contact-module/            ← Paquete completo, privado
│   ├── .env.example
│   ├── .env                   ← Lo genera el instalador
│   ├── bin/
│   ├── config/
│   ├── database/
│   ├── examples/
│   ├── public/
│   ├── src/
│   ├── storage/               ← Lo genera el instalador
│   ├── templates/
│   └── vendor/
└── public_html/               ← Raíz pública del dominio
    ├── contacto-prueba.html
    └── contact/
        ├── contact.php
        ├── config.php
        ├── loader.php
        └── assets/
            └── contact.js
~~~

Mantén el ZIP, `.env`, `vendor/`, `storage/` y el resto del paquete fuera de la raíz pública. Con la estructura anterior, `loader.php` encuentra el módulo automáticamente. Si tu dominio usa otra raíz pública, copia allí los archivos públicos y configura `CONTACT_MODULE_ROOT` en el entorno PHP con la ruta absoluta del módulo privado. [Guía de cPanel](cpanel.md)

## 2. Configura e instala

Selecciona PHP 8.3 o posterior en cPanel y habilita `pdo_sqlite`. Comprueba también la versión de PHP de Terminal o SSH, ya que puede ser distinta de la versión web.

~~~bash
cd ~/contact-module
php -v
php bin/install.php
~~~

En la primera ejecución, el instalador crea `.env` y genera `RATE_LIMIT_KEY`. Mientras espera, abre `.env` desde File Manager, sustituye las variables siguientes y guarda. Activa **Show Hidden Files** si no aparece el archivo.

~~~ini
APP_ENV=production
APP_URL=https://tudominio.com
ALLOWED_ORIGINS=https://tudominio.com
APP_BASE_PATH=/contact

DATABASE_ENABLED=true
DB_DRIVER=sqlite
STORAGE_MODE=database_only

MAIL_ENABLED=false
GOOGLE_SHEETS_ENABLED=false
QUEUE_ENABLED=false

TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=TU_SITE_KEY
TURNSTILE_SECRET_KEY=TU_SECRET_KEY
TURNSTILE_ALLOWED_HOSTNAMES=tudominio.com
~~~

Usa el origen HTTPS exacto del sitio y el hostname sin protocolo para Turnstile. Incluye `www` en ambos valores si es el dominio desde el que vas a probar. Sustituye las claves de ejemplo por las del widget y conserva `RATE_LIMIT_KEY`. [Configuración de Turnstile](turnstile.md)

Pulsa **Intro** en Terminal para aplicar las migraciones. Si el instalador ya terminó, vuelve a ejecutarlo. Comprueba que el diagnóstico devuelve `"ok": true` y `"database": "ready"`.

~~~bash
php bin/install.php
php bin/healthcheck.php --json
~~~

Si no tienes Terminal ni SSH, sigue la alternativa de File Manager y un cron de una sola ejecución para aplicar las migraciones. [Instalación sin Terminal](cpanel.md)

## 3. Verifica el primer envío

1. Abre `https://tudominio.com/contact/config.php?form_id=contact` y comprueba que responde JSON con `"success": true`.
2. Abre `https://tudominio.com/contacto-prueba.html`. Completa el formulario con datos de prueba y envíalo.
3. Confirma que aparece el mensaje de éxito. En la pestaña **Red** del navegador, el `POST /contact/contact.php` debe devolver HTTP 200 y un `submission_id`.
4. Localiza la base de datos en `/home/cliente/contact-module/storage/database/contacts.sqlite`. Si el hosting ofrece la herramienta `sqlite3`, sustituye el marcador por el UUID de la respuesta y comprueba que aparece una fila.

~~~bash
sqlite3 ~/contact-module/storage/database/contacts.sqlite \
  "SELECT uuid, form_id, created_at FROM submissions WHERE uuid = 'PEGA_AQUI_EL_SUBMISSION_ID';"
~~~

## 4. Prueba SMTP si lo necesitas

Una vez confirmado SQLite, cambia estas variables en el mismo `.env`. Usa un remitente autorizado por tu proveedor SMTP y un buzón de destino que controles. Los ejemplos de abajo usan STARTTLS en el puerto 587. Ajusta el puerto y el cifrado a las instrucciones del proveedor.

~~~ini
MAIL_ENABLED=true
MAIL_HOST=smtp.tudominio.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=contacto@tudominio.com
MAIL_PASSWORD=TU_CONTRASEÑA_SMTP
MAIL_FROM_ADDRESS=contacto@tudominio.com
MAIL_TO_ADDRESS=buzon-de-prueba@tudominio.com
~~~

Ejecuta el diagnóstico de conexión y haz otro envío desde el formulario.

~~~bash
php bin/healthcheck.php --check-connections
~~~

**Comprueba el buzón directamente.** Con `STORAGE_MODE=database_only`, el formulario puede devolver éxito al guardar el mensaje en SQLite aunque falle la notificación por correo. [Configuración SMTP](configuration.md)
