# Deploy en Railway (Scan2Order Lite)

Esta guia asume que desplegaras una sola aplicacion web en Railway usando `Dockerfile.railway`.

## 1) Preparar repositorio

1. Sube estos cambios al repositorio remoto.
2. Confirma que Railway puede leer `railway.json` y `Dockerfile.railway`.

## 2) Crear proyecto y base de datos

1. En Railway, crea `New Project` desde GitHub y selecciona este repositorio.
2. Agrega un servicio PostgreSQL dentro del mismo proyecto.
3. Abre el servicio de app (el que usa Dockerfile) y verifica que se detecta el build.

## 3) Variables de entorno (servicio app)

Configura estas variables en Railway (service variables):

- `APP_NAME=Scan2Order`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=` (genera una clave con `php artisan key:generate --show` y pegala aqui)
- `APP_URL=https://TU_DOMINIO_RAILWAY`
- `FRONTEND_URL=https://TU_DOMINIO_RAILWAY`
- `LOG_CHANNEL=stack`
- `LOG_LEVEL=error`
- `DB_CONNECTION=pgsql`
- `DB_HOST=${{Postgres.PGHOST}}`
- `DB_PORT=${{Postgres.PGPORT}}`
- `DB_DATABASE=${{Postgres.PGDATABASE}}`
- `DB_USERNAME=${{Postgres.PGUSER}}`
- `DB_PASSWORD=${{Postgres.PGPASSWORD}}`
- `CACHE_STORE=file`
- `FILESYSTEM_DISK=local`
- `QUEUE_CONNECTION=sync`
- `SESSION_DRIVER=file`
- `SESSION_LIFETIME=120`
- `SANCTUM_EXPIRATION=240`
- `SANCTUM_STATEFUL_DOMAINS=TU_DOMINIO_RAILWAY`
- `CORS_ALLOWED_ORIGINS=https://TU_DOMINIO_RAILWAY`
- `HEALTH_CHECK_TOKEN=TOKEN_LARGO_Y_ALEATORIO`
- `RUN_MIGRATIONS=true`

Variables de email (minimo):

- `MAIL_MAILER=smtp`
- `MAIL_HOST=...`
- `MAIL_PORT=587`
- `MAIL_USERNAME=...`
- `MAIL_PASSWORD=...`
- `MAIL_ENCRYPTION=tls`
- `MAIL_FROM_ADDRESS=...`
- `MAIL_FROM_NAME=Scan2Order`

Variables legales obligatorias:

- `LEGAL_COMPANY_NAME`
- `LEGAL_OWNER_TYPE`
- `LEGAL_TAX_ID`
- `LEGAL_ADDRESS`
- `LEGAL_POSTAL_CODE`
- `LEGAL_CITY`
- `LEGAL_PROVINCE`
- `LEGAL_COUNTRY`
- `LEGAL_CONTACT_EMAIL`
- `LEGAL_SUPPORT_EMAIL`
- `LEGAL_PRIVACY_EMAIL`
- `LEGAL_SUPPORT_PHONE`
- `LEGAL_REGISTRY_DATA`
- `LEGAL_JURISDICTION_CITY`

## 4) Primer despliegue

1. Lanza deploy desde Railway.
2. Espera a que termine build y arranque.
3. Verifica healthcheck: `GET /api/hello` debe devolver 200.
4. Verifica salud protegida: `GET /api/health?token=HEALTH_CHECK_TOKEN`.

## 5) Post-deploy recomendado

1. Una vez aplicadas migraciones, cambia `RUN_MIGRATIONS=false` para evitar ejecutar migraciones en cada restart.
2. Crea usuario admin inicial (por tinker o endpoint de onboarding).
3. Prueba login, alta de restaurante, alta de catalogo y exportacion PDF.

## 6) Dominio propio

1. En Railway, configura Custom Domain en el servicio web.
2. Actualiza en variables:
   - `APP_URL`
   - `FRONTEND_URL`
   - `SANCTUM_STATEFUL_DOMAINS`
   - `CORS_ALLOWED_ORIGINS`
