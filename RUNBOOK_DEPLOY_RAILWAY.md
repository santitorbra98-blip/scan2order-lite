# Runbook de despliegue en Railway (minuto a minuto)

Este runbook esta pensado para una salida controlada a produccion de Scan2Order Lite.

## Objetivo

Desplegar de forma segura, validar salud de la aplicacion y dejar capacidad de rollback.

## Roles sugeridos

- Operador: ejecuta acciones en Railway.
- Verificador: valida UI y endpoints.
- Responsable tecnico: decide rollback si algo falla.

## Preparacion previa (T-60 a T-15)

### T-60

- Confirmar que el codigo desplegable incluye:
  - Dockerfile.railway
  - railway.json
  - DEPLOY_RAILWAY.md
  - PRODUCTION_CHECKLIST_RAILWAY.md
- Confirmar acceso a Railway y proyecto correcto.

### T-45

- Confirmar variables obligatorias en servicio app:
  - APP_ENV=production
  - APP_DEBUG=false
  - APP_KEY con valor real
  - APP_URL y FRONTEND_URL con dominio objetivo
  - DB_CONNECTION/DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD
  - SANCTUM_STATEFUL_DOMAINS
  - CORS_ALLOWED_ORIGINS
  - HEALTH_CHECK_TOKEN
  - RUN_MIGRATIONS=true para primer deploy

### T-30

- Confirmar variables legales y de correo completas.
- Confirmar que existe servicio PostgreSQL en el mismo proyecto.

### T-20

- Preparar comandos de comprobacion para ejecutar al final:

```bash
curl -i "https://TU_DOMINIO/api/hello"
curl -i "https://TU_DOMINIO/api/health?token=TU_HEALTH_CHECK_TOKEN"
curl -i "https://TU_DOMINIO/api/health"
```

### T-15

- Avisar ventana de despliegue al equipo.
- Congelar cambios en main hasta cerrar validacion.

## Ejecucion del despliegue (T-0 a T+20)

### T-0

- Lanzar Deploy en Railway (servicio app conectado al repo).

### T+3

- Revisar build logs:
  - Build de frontend correcto.
  - Composer install correcto.
  - Contenedor arranca sin restart loop.

Si falla build, detener salida y corregir antes de continuar.

### T+7

- Revisar startup logs:
  - Se crea storage link sin error bloqueante.
  - Migraciones completadas (si RUN_MIGRATIONS=true).

Si migraciones fallan, no continuar pruebas funcionales. Resolver y relanzar deploy.

### T+10

- Verificacion tecnica minima:
  - GET /api/hello devuelve 200.
  - GET /api/health con token devuelve 200.
  - GET /api/health sin token devuelve 403 (entorno production).

### T+12

- Smoke funcional rapido:
  - Cargar home.
  - Login.
  - Crear restaurante.
  - Crear catalogo/seccion/producto.
  - Ver menu publico.
  - Exportar PDF.

Si alguna prueba critica falla, ir a rollback.

### T+20

- Si todo esta OK:
  - Cambiar RUN_MIGRATIONS=false.
  - Guardar evidencia de logs y checks.
  - Cerrar ventana de despliegue.

## Rollback operativo (si hay fallo critico)

### Criterios de rollback

- API principal no responde establemente.
- Login o flujo base de negocio roto.
- Error de migracion con impacto funcional.

### Pasos

1. En Railway, redeploy de la ultima version estable.
2. Verificar de nuevo /api/hello y login.
3. Si hubo migracion destructiva, restaurar backup de base de datos.
4. Comunicar estado y abrir postmortem tecnico.

## Checklist de cierre (T+30)

- Servicio estable sin reinicios anormales.
- Errores 5xx en nivel bajo o nulo.
- Flujos criticos OK.
- RUN_MIGRATIONS en false.
- Registro de incidencia o cierre exitoso documentado.

## Comandos utiles

Generar APP_KEY en local:

```bash
cd backend
php artisan key:generate --show
```

Backup rapido de PostgreSQL:

```bash
PGPASSWORD="$DB_PASSWORD" pg_dump \
  -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" \
  -Fc -f "scan2order_$(date +%F_%H%M).dump"
```
