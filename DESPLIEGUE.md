# Instalación, actualización y operación

## 1. Requisitos

- PHP 8.2 o superior con PDO MySQL, OpenSSL, Mbstring, JSON y Fileinfo.
- MySQL 8.
- Composer 2.
- Servidor HTTPS cuyo directorio público apunte únicamente a `public/`.

No se debe publicar la raíz del proyecto. `.env`, `storage/`, `vendor/` y las migraciones deben permanecer fuera del directorio web.

## 2. Primera instalación

1. Copiar el proyecto al servidor.
2. Ejecutar `composer install --no-dev --optimize-autoloader`.
3. Copiar `.env.production.example` como `.env` y reemplazar todos los valores de ejemplo.
4. Crear `portal_salud` con `utf8mb4_unicode_ci`.
5. Crear el usuario de permisos mínimos tomando como base `database/production_user.sql.example`.
6. Ejecutar `php console migrate` y `php console db:seed`.
7. Dar escritura al usuario PHP solamente sobre `storage/cache`, `storage/logs` y `storage/documentos`.
8. Configurar el servidor para que la raíz web sea `public/`.
9. Registrar en Google Cloud el origen HTTPS y el callback exacto `https://DOMINIO/auth/google/callback`.
10. Ejecutar `php console app:check --production`; todos los puntos deben aparecer con `[x]`.

## 3. Configuración de producción

Valores obligatorios:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMINIO
FORCE_HTTPS=true
SESSION_SECURE=true
GOOGLE_REDIRECT_URI=https://DOMINIO/auth/google/callback
```

Si existe un proxy inverso, este debe reemplazar y controlar `X-Forwarded-Proto`; no debe aceptar dicho encabezado directamente desde Internet.

## 4. Actualización

1. Crear respaldo de la base y de `storage/documentos`.
2. Activar una página de mantenimiento en el servidor web.
3. Instalar el nuevo código conservando `.env` y `storage/`.
4. Ejecutar `composer install --no-dev --optimize-autoloader`.
5. Ejecutar `php console migrate` y `php console db:seed`.
6. Revisar `php console migrate:status`.
7. Desactivar mantenimiento y comprobar acceso, dashboard y descarga privada.

Las migraciones no se revierten manualmente en producción. Ante un fallo se restaura el respaldo completo.

## 5. Respaldos y restauración

Respaldar diariamente como una misma unidad:

- Base MySQL `portal_salud` mediante `mysqldump --single-transaction`.
- Carpeta completa `storage/documentos`.

Conservar copias cifradas fuera del servidor y aplicar una política de retención. Una restauración válida debe recuperar primero MySQL y después la carpeta de documentos del mismo punto temporal. La restauración debe ensayarse en un entorno aislado antes del lanzamiento.

## 6. Logs

La aplicación escribe un archivo diario `storage/logs/app-AAAA-MM-DD.log`, elimina automáticamente los archivos que superen `LOG_RETENTION_DAYS` y oculta patrones de credenciales conocidos. Limitar el acceso al usuario del servicio y revisar periódicamente que los logs no contengan información clínica ingresada por usuarios.

## 7. Lista de aceptación del MVP

- [ ] El dominio responde exclusivamente por HTTPS.
- [ ] La raíz web apunta a `public/`.
- [ ] `.env`, logs y documentos responden 404 desde Internet.
- [ ] El callback HTTPS está registrado en Google Cloud.
- [ ] Acceso y cierre de sesión Google funcionan en el dominio final.
- [ ] Un usuario no accede a otra familia cambiando IDs.
- [ ] Estados y tipos incorrectos son rechazados.
- [ ] Atenciones, medicamentos y documentos completan su flujo principal.
- [ ] Descargas privadas requieren sesión y familia autorizada.
- [ ] `APP_DEBUG=false`, `FORCE_HTTPS=true` y `SESSION_SECURE=true`.
- [ ] Las migraciones y seeders terminan sin pendientes.
- [ ] Las pruebas automatizadas están aprobadas.
- [ ] Existe un respaldo reciente de MySQL y documentos.
- [ ] La restauración fue probada en un entorno aislado.
