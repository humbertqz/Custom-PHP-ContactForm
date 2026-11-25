# Actualizaciones

El paquete administra src/, public/, database/migrations/, bin/ y vendor/. El cliente administra .env, storage/, formularios y plantillas personalizados. En Composer vive en un proyecto privado separado, y el paquete se actualiza solo bajo vendor/. En ZIP, mantén un directorio privado de instancia con sus personalizaciones. Usa CONTACT_MODULE_ROOT para la instancia y CONTACT_MODULE_PACKAGE para el directorio del paquete en el endpoint web. En CLI, ejecuta el script bin/ del paquete con CONTACT_MODULE_ROOT apuntando a la instancia. Evita editar plantillas o formularios del paquete. Si se usa ZIP y se sobreescriben nombres como contact.php o notification.php, respalda esos archivos y restaura la versión del cliente de forma explícita.

1. Detén cron y bloquea temporalmente nuevos envíos si la actualización incluye migraciones o cambios de destinos.
2. Respalda .env, formularios, plantillas, SQLite o MySQL, incluidos trabajos pendientes. Para SQLite usa la API de backup o un snapshot consistente.
3. Descomprime la versión nueva en un área temporal. Copia la configuración y datos del cliente preservando permisos. Si debes conservar una raíz estable, reemplaza solo las rutas de paquete identificadas y copia los archivos públicos de la versión correspondiente.
4. Ejecuta php bin/migrate.php y php bin/healthcheck.php --json. Verifica credenciales y un envío autorizado en staging.
5. Activa la versión y reanuda cron. Si hubo cambios de esquema, revierte el código y restaura el respaldo correspondiente como conjunto.

Coordina la reconfiguración de canales, formularios, destinatarios, rutas de Sheets y retención después de drenar trabajos que dependan de la configuración anterior. Un envío en cola usa destinos SMTP y plantillas de la configuración vigente al procesarlo.
