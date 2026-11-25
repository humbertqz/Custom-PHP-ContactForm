# Bases de datos

SQLite usa pdo_sqlite, almacenamiento local privado y un sistema de archivos con bloqueos fiables. Las migraciones activan WAL, y cada conexión habilita claves foráneas y espera de bloqueo. Conserva los archivos -wal y -shm hasta cerrar los procesos, y usa una copia consistente con la API o comando .backup de SQLite. Evita SQLite en sistemas de archivos de red. Las consultas usan PDO y parámetros preparados.

Para MySQL o MariaDB, crea una base dedicada por instalación con utf8mb4. Usa InnoDB y una versión compatible con columnas JSON, por ejemplo MySQL 8 o MariaDB 10.11. DB_DRIVER=mysql y DB_DRIVER=mariadb usan pdo_mysql. El usuario que ejecuta migraciones requiere permisos DDL sobre esa base. La cuenta de ejecución requiere solo SELECT, INSERT, UPDATE y DELETE. Cuando DB va sobre una red, protege la conexión con la red privada o las políticas TLS del proveedor.

Ejecuta php bin/migrate.php tras respaldar. Las migraciones se registran por archivo y se serializan mediante un bloqueo SQLite o GET_LOCK en MySQL/MariaDB. MySQL confirma DDL implícitamente; una migración interrumpida debe volver a ejecutarse tras diagnosticar el error. La migración inicial usa CREATE IF NOT EXISTS en MySQL para permitir recuperar ejecuciones parciales.

submissions conserva mensajes según la estrategia. queue_submissions contiene el sobre temporal y el identificador de idempotencia. deliveries referencia el sobre y registra un estado por canal. rate_limits contiene HMAC de identidades y expiración. Los tiempos son UTC, en ISO 8601 para mensajes y segundos Unix para cola.
