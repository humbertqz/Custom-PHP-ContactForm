# Plan de implementación y estado

| Fase | Estado |
| --- | --- |
| 1. Inicialización | Dependencias resueltas y verificadas en PHP 8.3.30 con composer.lock. |
| 2. Núcleo | Contratos, DTO, formularios y validación implementados y probados localmente. |
| 3. Bases de datos | Migraciones, adaptadores y limitación persistente. SQLite verificado. MySQL y MariaDB requieren una base de ensayo. |
| 4. Almacenamiento | Seis estrategias, aceptación por destino y retención implementadas. |
| 5. Google Sheets | Adaptador y Apps Script verificados con mocks. Falta un ensayo autorizado. |
| 6. Correo | Prueba de MIME aprobada con PHPMailer 7.2.0 y transporte simulado. SMTP real pendiente. |
| 7. Seguridad | Turnstile, honeypot, CSRF, proxies, orígenes y límites implementados con pruebas locales selectivas. |
| 8. Cola | Reclamación, leases, recuperación, reintentos y cron. La reclamación SQLite se verificó con dos procesos. |
| 9. API | Endpoints verificados con SQLite desde procesos PHP aislados. La protección Apache y el host MAMP pasaron la revisión sintáctica. Queda el ensayo a través del servidor web. |
| 10. Frontend | SDK explícito, estado y ejemplos. Dos widgets, grupos y salida segura verificados con DOM simulado. |
| 11. CLI | Instalador, migraciones, salud, cron, limpieza y herramienta administrativa implementados. Limpieza verificada en SQLite. |
| 12. Distribución | ZIP de producción 1.0.0 preparado con dependencias de runtime para ensayo local. |
| 13. Testing | composer check aprobado en PHP 8.3.30. Quedan MySQL/MariaDB y servicios externos, y endurecer el análisis estático más allá del nivel configurado. |
| 14. Documentación | Guías en español, matriz de entrega, modelo de privacidad y registro de verificación. |

Consulta [verificación](docs/verification.md) para las pruebas ejecutadas y los requisitos del ensayo de producción.
