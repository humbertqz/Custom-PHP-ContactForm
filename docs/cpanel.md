# Instalación en cPanel

1. Selecciona PHP 8.3 o posterior en MultiPHP y confirma extensiones curl, mbstring, openssl, PDO y los controladores que hayas elegido. Verifica la versión del binario CLI en Terminal con php -v; el PHP web puede usar otro binario.
2. Sube el ZIP a /home/cliente/, extráelo allí y nombra su directorio contact-module. Con File Manager copia solo public/ a public_html/contact/. Mantén la raíz privada fuera de public_html y de raíces de dominios adicionales.
3. Ejecuta php bin/install.php en Terminal o SSH. Sin Terminal, crea manualmente storage/database, storage/cache y storage/logs con permiso privado, copia .env.example como .env y usa el CLI de migración a través de Cron Jobs una sola vez. Coloca la salida del CLI en un archivo fuera de public_html.
4. Para MySQL, crea base y usuario en MySQL Database Wizard, asigna los permisos de la base y usa los nombres completos con prefijo cPanel. Para SQLite usa la ubicación privada que trae el ejemplo.
5. Añade APP_URL, SMTP, Turnstile y cualquier hoja configurada. Pregunta al hosting si el puerto SMTP elegido está permitido y confirma la ruta del binario PHP para cron.
6. Configura trabajos de reintentos y limpieza y ejecuta healthcheck o prográmalo una vez con cron para verificar la instalación.
7. Abre la página del formulario por HTTPS y realiza un envío autorizado. Comprueba los destinos activos y la cola.

El descubrimiento automático funciona con /home/cliente/contact-module y /home/cliente/public_html/contact. En una disposición distinta, configura CONTACT_MODULE_ROOT en el entorno PHP y cron. Aplica los ejemplos de Apache y Nginx de [despliegue](deployment.md).
