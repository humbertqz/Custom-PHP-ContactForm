# Seguridad

Sirve únicamente public/ a través de HTTPS. Mantén .env, src, vendor, herramientas, migraciones, colas, hojas exportadas y logs fuera del directorio público. Deshabilita display_errors en la configuración PHP del hosting. Usa permisos 0700 para directorios privados, 0600 para secretos y un usuario de PHP dedicado por cuenta. Si el host usa dos usuarios, concede un grupo privado con permisos mínimos.

CONFIG permite GET y POST es el único método de recepción. Las solicitudes tienen un límite de tamaño y las credenciales quedan en PHP. Define CORS por origen exacto para frontends externos e incluye sus hostnames en Turnstile. Sin Origin, la solicitud todavía requiere las demás protecciones. CSRF utiliza un token de sesión con cookie HttpOnly, SameSite=Lax y Secure en producción. Actívalo para usos con sesión del mismo origen.

Los encabezados X-Forwarded-For solo se consideran cuando el peer coincide con TRUSTED_PROXIES. El módulo lee la cadena desde la derecha y usa la primera dirección no confiable. Configura únicamente proxies que anexen o saneen la cadena. La limitación usa buckets HMAC por instalación, por formulario, por IP y por combinación de IP y formulario. Las confirmaciones tienen un límite adicional por destinatario. Ajusta valores para redes compartidas.

La URL de Apps Script se restringe al host y path de despliegue; el transportador solo permite TLS. La única redirección admitida usa GET al endpoint de respuesta de Google. El visitante no controla URLs de integración, SMTP, destinatarios, plantillas ni reglas de acción. Las consultas usan parámetros preparados y Apps Script protege celdas contra fórmulas.

Para una CSP, permite el SDK y el iframe mediante https://challenges.cloudflare.com según las directivas del sitio. Añade el backend explícito a connect-src si el frontend está alojado aparte. [Guía CSP de Cloudflare](https://developers.cloudflare.com/turnstile/reference/content-security-policy/).
