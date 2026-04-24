# Despliegue en Render

## Opcion recomendada

Render con Blueprint (`render.yaml`) es la opcion mas simple y gratuita para este proyecto.

## Paso a paso

1. Conectar repositorio en Render.
2. `New` -> `Blueprint`.
3. Seleccionar repo y confirmar creacion de:
   - web service `scan2order-lite`
   - PostgreSQL `scan2order-db`
4. Completar variables pendientes (`sync: false`).
5. Ejecutar primer deploy.

## APP_KEY

Genera localmente:

```bash
cd backend
php artisan key:generate --show
```

Pega el resultado en `APP_KEY` del servicio web.

## Primer deploy

- Mantener `RUN_MIGRATIONS=true`.
- Validar health checks.
- Ejecutar smoke test automatizado del workflow.

## Deploys siguientes

- Cambiar a `RUN_MIGRATIONS=false`.
- Desplegar por push a rama principal.
- Verificar workflow de smoke test tras cada release.
