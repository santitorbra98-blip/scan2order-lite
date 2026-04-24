# Deploy en Render (opcion simple y gratuita)

Esta es la opcion recomendada para este proyecto porque:
- Soporta Docker directamente (ya tienes `Dockerfile.railway`).
- Puede crear web + PostgreSQL desde `render.yaml`.
- Tiene plan gratuito para pruebas y proyectos pequenos (con limitaciones de inactividad y recursos).

## 1) Preparar el proyecto

1. Asegura que el repositorio remoto tiene estos archivos actualizados:
   - `Dockerfile.railway`
   - `render.yaml`
   - `backend/.env.example`
2. Genera una APP_KEY para Laravel:

```bash
cd backend
php artisan key:generate --show
```

Guarda ese valor para Render.

## 2) Crear servicios en Render con Blueprint

1. Entra en Render y conecta tu cuenta de GitHub.
2. `New` -> `Blueprint`.
3. Selecciona este repositorio (Render detectara `render.yaml`).
4. Confirma creacion de:
   - Servicio web `scan2order-lite`
   - PostgreSQL `scan2order-db`

## 3) Configurar variables obligatorias

En el servicio web, completa estas variables (las `sync: false`):

- `APP_KEY`
- `APP_URL` (la URL publica de Render, o tu dominio)
- `FRONTEND_URL` (igual que APP_URL)
- `SANCTUM_STATEFUL_DOMAINS` (sin `https://`, por ejemplo `scan2order-lite.onrender.com`)
- `CORS_ALLOWED_ORIGINS` (con `https://`, por ejemplo `https://scan2order-lite.onrender.com`)
- Variables de email SMTP (`MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`)
- Todas las variables legales `LEGAL_*`

## 4) Primer despliegue

1. Lanza deploy manual desde Render o push al repositorio.
2. Verifica endpoints:
   - `GET /api/hello` -> 200
   - `GET /api/health?token=HEALTH_CHECK_TOKEN` -> 200
3. Comprueba login y flujo principal de negocio.

## 5) Despues del primer despliegue

1. Cambia `RUN_MIGRATIONS` a `false` para evitar migraciones en cada reinicio.
2. Crea usuario administrador inicial.
3. Si usas dominio propio, vuelve a ajustar:
   - `APP_URL`
   - `FRONTEND_URL`
   - `SANCTUM_STATEFUL_DOMAINS`
   - `CORS_ALLOWED_ORIGINS`

## 6) Limitaciones del plan gratuito

- El servicio puede "dormirse" por inactividad y tardar en despertar.
- Recursos reducidos (CPU/RAM) para carga baja-media.
- Recomendado para MVP, demos y primeras validaciones.
