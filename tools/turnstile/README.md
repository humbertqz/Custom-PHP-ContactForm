# CLI privada de Turnstile

Desde un checkout de desarrollo con Composer, copia .env.example a .env con permiso 0600. Limita el token a la cuenta y al permiso Turnstile Sites Read para consultas o Turnstile Sites Write para cambios. Usa un widget por cliente. Conserva el token de agencia en el equipo administrativo.

```bash
php tools/turnstile/turnstile.php list
php tools/turnstile/turnstile.php create --name="Cliente A" --domains="cliente.example,www.cliente.example" --output=/ruta/privada/cliente.turnstile
php tools/turnstile/turnstile.php get --site-key=SITE_KEY
php tools/turnstile/turnstile.php domains --site-key=SITE_KEY
php tools/turnstile/turnstile.php add-domain --site-key=SITE_KEY --domain=landing.cliente.example
php tools/turnstile/turnstile.php remove-domain --site-key=SITE_KEY --domain=landing.cliente.example
php tools/turnstile/turnstile.php update --site-key=SITE_KEY --name="Cliente A MX"
php tools/turnstile/turnstile.php rotate-secret --site-key=SITE_KEY --output=/ruta/privada/cliente.rotated
php tools/turnstile/turnstile.php export --site-key=SITE_KEY --output=/ruta/privada/cliente.turnstile
php tools/turnstile/turnstile.php delete --site-key=SITE_KEY
```

Las consultas muestran metadatos públicos. --output crea un fragmento 0600 en una ruta nueva. Transfiere la clave por un canal privado y elimina la copia operativa cuando termines. La rotación usa el período de gracia de dos horas. Sustituye la clave del servidor y valida un envío durante ese período. Algunos fallos de red dejan el resultado de una mutación indeterminado, así que consulta el widget antes de repetir. Coordina los cambios de dominios con otros operadores porque la API actualiza la lista completa. Ante un HTTP 429, espera y consulta los límites de la cuenta.

Consulta la [referencia de widgets](https://developers.cloudflare.com/api/resources/turnstile/subresources/widgets/) y la [configuración manual](../../docs/turnstile.md).
