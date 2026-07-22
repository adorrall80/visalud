# Portal Familiar de Salud

Aplicación PHP 8.2 y MySQL 8 para organizar personas, atenciones, medicamentos, horarios y documentos médicos dentro de una familia. El acceso se realiza exclusivamente con Google.

La persona con la que se está trabajando se selecciona una sola vez en el encabezado. Dashboard, atenciones, medicamentos, documentos y nuevos registros utilizan automáticamente ese contexto.

El dashboard muestra por defecto un calendario mensual de sus atenciones y permite cambiar a una lista cronológica. La preferencia se conserva durante la sesión y los colores se administran desde el mantenedor de estados.

Las familias pueden archivarse y restaurarse desde “Mis familias”. El archivado es reversible y conserva integrantes, personas y toda la información clínica.

Un administrador puede generar desde “Integrantes” un enlace de invitación de 24 horas, copiarlo y compartirlo. La persona invitada elige su propia cuenta Google y, después de confirmar, se incorpora con rol Familiar. Cada enlace se utiliza una sola vez y puede revocarse mientras esté pendiente.

## Instalación local

1. Copiar `.env.example` como `.env`.
2. Completar MySQL y las credenciales OAuth de Google.
3. Ejecutar `composer install`.
4. Ejecutar `php console migrate` y `php console db:seed`.
5. Iniciar el servidor con `php -S localhost:8000 -t public`.
6. Abrir `http://localhost:8000`.

Para cargar la familia ficticia de revisión:

```text
php console db:seed --demo
```

El demo solo se admite con `APP_ENV=local`; puede ejecutarse nuevamente sin duplicar datos.

## Comprobaciones

```text
php console migrate:status
php console app:check
vendor/bin/phpunit
```

## Documentación

- `PLAN_DESARROLLO.md`: avance y etapas.
- `MODELO_DATOS_BASICO.md`: tablas y relaciones.
- `ARQUITECTURA_Y_ESTRUCTURA.md`: componentes y carpetas.
- `DESPLIEGUE.md`: instalación, actualización, seguridad, respaldos y aceptación.
