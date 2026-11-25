# Despliegue y cron

Coloca los archivos privados fuera de cada raíz web. En Apache o Nginx copia los endpoints a un subdirectorio del document root y pásalos al manejador PHP existente. Deshabilita listados de directorios. Si usas rutas personalizadas, define CONTACT_MODULE_ROOT en el entorno de PHP-FPM o el vhost, y APP_BASE_PATH en .env. Cuando el ZIP y la instancia viven en rutas separadas, define además CONTACT_MODULE_PACKAGE con la ruta privada del ZIP extraído. Con clear_env de PHP-FPM, define env[CONTACT_MODULE_ROOT] en el pool. Si PHP consume fastcgi_param, comprueba que getenv lee esa variable en tu hosting. El loader valida las rutas canónicas. Restringe URLs privadas también en el servidor como defensa adicional.

Revisa post_max_size, max_input_vars, max_execution_time y el límite de cuerpo del proxy. Conserva el límite de aplicación por debajo de la capacidad PHP. Para estrategias con varios destinos y hosting con timeouts cortos, configura QUEUE_PROCESS_INLINE=false.

~~~cron
*/5 * * * * /ruta/php /home/cliente/contact-module/bin/retry.php --limit=50
20 3 * * * /ruta/php /home/cliente/contact-module/bin/cleanup.php
~~~

retry.php --dry-run muestra conteos y el límite sin reclamar trabajos. Limpieza --dry-run calcula los registros elegibles. healthcheck.php --check-connections autentica SMTP sin enviar y comprueba acceso HTTPS a Google/Turnstile sin afirmar que sus credenciales funcionen. El límite de ejecución detiene nuevas reclamaciones una vez vencido. Un transporte ya iniciado puede terminar después del límite.

## ZIP

Desde un checkout de distribución limpio, resuelve y conserva composer.lock y ejecuta:

~~~bash
composer install
composer check
php bin/build-release.php --version=1.0.0
unzip -l dist/php-contact-module-v1.0.0.zip
~~~

El constructor crea un directorio temporal, instala solo dependencias de producción y usa una lista de rutas de distribución. El paquete incluye integraciones y documentación. Las credenciales de agencia, pruebas, bases, logs, caché y herramientas Turnstile permanecen fuera. Para distribución privada mediante Composer, publica una etiqueta semántica en el repositorio privado.

## Ejemplos de servidor

En un VirtualHost Apache con el handler PHP configurado:

~~~apache
DocumentRoot /home/cliente/public_html
SetEnv CONTACT_MODULE_ROOT /home/cliente/contact-module
<Directory /home/cliente/public_html>
    Options -Indexes
</Directory>
~~~

En un bloque server Nginx con PHP-FPM, ajusta el socket a tu instalación:

~~~nginx
root /home/cliente/public_html;
autoindex off;
client_max_body_size 128k;
location ~ ^/contact/(contact|config|admin)\.php$ {
    try_files $uri =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param CONTACT_MODULE_ROOT /home/cliente/contact-module;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
location = /contact/loader.php { return 404; }
~~~
