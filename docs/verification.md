# Verificación

Las pruebas PHPUnit verifican configuración, validación, captchas simulados, redirecciones, aislamiento de datos, SQLite, cola y SMTP simulado. Las pruebas Node verifican el código de Apps Script contra un Spreadsheet simulado. Las integraciones reales requieren cuentas y servicios de ensayo autorizados.

Antes de distribuir, ejecuta composer check con el lock del repositorio, ejecuta la prueba MySQL/MariaDB sobre una base dedicada, verifica ambos endpoints bajo el servidor del cliente y revisa el inventario del ZIP. Haz una prueba autorizada de correo, de Turnstile y de Apps Script que confirme que repetir un UUID conserva las observaciones de la hoja.

## Resultados del entorno de desarrollo

- PHP 8.5.12. Se verificó la sintaxis de 79 archivos PHP.
- PHPUnit 9.6.34 disponible localmente. De 25 casos, 21 pasaron y 4 se omitieron. Se ejecutaron 108 aserciones. Las omisiones se debieron a PHPMailer, phpdotenv y al arranque HTTP dependiente de Composer, y a la ausencia de una base MySQL de ensayo.
- Node 26.13.1. Pasaron tres pruebas con APIs de navegador y Google simuladas y se verificó la sintaxis de ambos scripts.
- PHP_CodeSniffer 3.13.6 disponible localmente. PSR-12 pasó sin infracciones. El paquete declara PHPCS 4 y PHPUnit 12 como dependencias de desarrollo.
- Composer 2.11.2 validó el manifiesto. El aviso de licencia coincide con la decisión pendiente del propietario.
- Este entorno no resolvió repo.packagist.org y su caché estaba incompleta. No se generó composer.lock ni el ZIP. Composer check y PHPStan siguen pendientes.
- El sandbox rechazó abrir un socket local. La comprobación de la raíz privada se ejecutó en un proceso PHP aislado. Quedan por validar HTTP con un servidor web, PHP 8.3 y cPanel.

Se corrigieron el manejo de SMTP ambiguo tras DATA, la conservación de la ruta de Sheets al recuperar filas de SQLite, la exclusión de trabajo SMTP abandonado de los reintentos y el envío por POST en los ejemplos HTML. Las pruebas de Apps Script comprueban que repetir el UUID deja intacto el estado editado manualmente.

## Verificación actual con MAMP

- Composer generó composer.lock e instaló las dependencias. Con PHP 8.3.30 de MAMP, composer check pasó con PHPUnit 12.5.45, PHPStan al nivel 5 configurado y PHPCS 4. PHPUnit aprobó 24 de 25 casos, con 166 aserciones. Se omitió MySQL/MariaDB por falta de una base de ensayo accesible. Pasaron las tres pruebas JavaScript con mocks.
- Se verificaron phpdotenv, el MIME de PHPMailer 7.2.0 con transporte simulado y los endpoints en procesos PHP aislados con SQLite. La prueba del paquete extraído detectó un problema de autoload en el diagnóstico CLI que se corrigió y quedó cubierto por la suite.
- Una medición complementaria con Xdebug registró 54.85 % de cobertura de líneas de src. No incluye los procesos PHP independientes de la suite. El análisis exploratorio en nivel 9 reportó hallazgos adicionales de tipado. PHPStan ejecuta el nivel configurado en el proceso principal para evitar la necesidad de un socket local.
- El ZIP 1.0.0 se construye desde composer.lock con nueve dependencias de runtime y queda disponible para un ensayo local. Se mantiene pendiente un ensayo por HTTP con el servidor de MAMP, MySQL/MariaDB y la validación real de SMTP, Apps Script y Turnstile.
