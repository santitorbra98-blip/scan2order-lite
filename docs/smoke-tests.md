# Smoke Tests Post Deploy (Render)

Este proyecto incluye un test automatizado para validar en produccion:

- `GET /api/hello`
- `GET /api/health?token=...`
- `POST /api/login`

## Secrets requeridos en GitHub

Configura en `Settings -> Secrets and variables -> Actions`:

- `RENDER_SMOKE_URL` (ejemplo: `https://scan2order-lite.onrender.com`)
- `RENDER_SMOKE_HEALTH_TOKEN`
- `RENDER_SMOKE_LOGIN`
- `RENDER_SMOKE_PASSWORD`

## Workflow

Archivo: `.github/workflows/render-smoke-test.yml`

- Se ejecuta manualmente (`workflow_dispatch`).
- Puede ejecutarse por `schedule` diario.
- Falla el pipeline si cualquier comprobacion devuelve estado inesperado.

## Usuario smoke recomendado

Crea un usuario dedicado para pruebas de disponibilidad (rol minimo necesario).

Ejemplo (desde consola de Laravel):

```php
use App\\Models\\User;
use App\\Models\\Role;

$role = Role::where('name', 'admin')->first();

User::updateOrCreate(
  ['email' => 'smoke@scan2order-lite.onrender.com'],
  [
    'name' => 'Smoke User',
    'password' => 'Cambiar123456789!',
    'role_id' => $role?->id,
    'status' => 'active',
  ]
);
```

## Ejecucion local del smoke test

```bash
SMOKE_BASE_URL=https://scan2order-lite.onrender.com \
SMOKE_HEALTH_TOKEN=... \
SMOKE_LOGIN=smoke@scan2order-lite.onrender.com \
SMOKE_PASSWORD='Cambiar123456789!' \
npm run smoke:render
```
