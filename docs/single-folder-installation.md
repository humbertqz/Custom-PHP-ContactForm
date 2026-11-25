# Instalar el formulario en una sola carpeta del sitio

Extrae `contact-folder-v1.0.0.zip` en la carpeta del proyecto del sitio. El ZIP crea una sola carpeta `contact/` con el módulo, las dependencias PHP y el fragmento HTML. El servidor publica su subcarpeta `public/` en la URL `/contact/`.

## 1. Coloca la carpeta junto a la raíz web

~~~text
/home/cliente/misitio/
├── public/                        ← Raíz web del sitio
│   └── contacto.html              ← Página donde se inserta el formulario
└── contact/                       ← Única carpeta que subes y extraes
    ├── public/                    ← Visible en la URL /contact/
    ├── examples/embed-form.html   ← Fragmento para copiar y pegar
    ├── bin/
    ├── src/
    ├── vendor/
    ├── .env                       ← Lo genera el instalador
    └── storage/                   ← Lo genera el instalador
~~~

En este ejemplo, toda la instalación está en `misitio/`. La raíz pública del dominio es `misitio/public/`. Si el hosting solo admite `public_html` como raíz y no permite publicar rutas desde una carpeta privada, usa la instalación de cPanel con los archivos públicos separados. [Instalación en cPanel](hosting-installation.md)

## 2. Publica la ruta una vez

En el VirtualHost de Apache del sitio, añade estas directivas. Sustituye la ruta de ejemplo por la ruta absoluta del módulo y conserva el manejador PHP existente en el host. Si administras el sitio desde cPanel, solicita el alias al proveedor cuando tu cuenta no tenga acceso al VirtualHost.

~~~apache
Alias "/contact/" "/home/cliente/misitio/contact/public/"

<Directory "/home/cliente/misitio/contact">
    AllowOverride None
    Require all denied
</Directory>

<Directory "/home/cliente/misitio/contact/public">
    Options -Indexes
    AllowOverride None
    Require all granted
</Directory>
~~~

Comprueba que ninguna otra raíz web del servidor incluye la carpeta privada `contact/`. La aplicación valida que el paquete y sus datos queden fuera de la raíz web principal.

## 3. Configura el módulo y pega el HTML

1. Ejecuta `php bin/install.php` desde `misitio/contact/`, con PHP 8.3 o superior. Edita el `.env` generado mientras espera y pulsa Intro para aplicar las migraciones.
2. Para la primera prueba, usa SQLite y configura Turnstile. Mantén `APP_BASE_PATH=/contact` y ajusta `APP_URL` y `ALLOWED_ORIGINS` al origen HTTPS donde está la página del formulario. [Valores de prueba y diagnóstico](hosting-installation.md#2-configura-e-instala)
3. Copia el contenido de `contact/examples/embed-form.html` a la posición deseada en la página. Conserva la etiqueta `<script>`, que carga el JavaScript del formulario.
4. Abre `/contact/config.php?form_id=contact` en el dominio, comprueba que devuelve JSON con `"success": true` y haz un envío de prueba desde la página.

## Generar este ZIP desde el proyecto

~~~bash
composer check
php bin/build-release.php --version=1.0.0 --site-folder
~~~

El archivo queda en `dist/contact-folder-v1.0.0.zip`.
