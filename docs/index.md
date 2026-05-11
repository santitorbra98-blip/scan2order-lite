# Scan2Order Lite

Aplicacion web para crear y gestionar cartas digitales de restaurantes mediante codigos QR.

## Stack

| Capa | Tecnologia |
|------|-----------|
| Backend | Laravel 12 · PHP 8.4 · Sanctum |
| Base de datos | PostgreSQL |
| Frontend | Vue 3 · Vite · Pinia |
| Infra local | Docker Compose (Nginx + PHP-FPM) |
| Despliegue | Render (render.yaml incluido) |
| Docs | VitePress → GitHub Pages |

## Funcionalidades principales

- Registro con verificacion por email (MFA de 6 digitos)
- Autenticacion con tokens Sanctum (expiracion configurable)
- Gestion de restaurantes, catalogos, secciones y productos
- Subida segura de imagenes (validacion por MIME, bloqueo de ejecucion PHP en uploads)
- Exportacion de catalogos a PDF
- Panel de administracion: gestion de usuarios, roles y permisos
- Ajustes de correo configurables desde el panel (superadmin)
- Recuperacion de contrasena con codigo temporal
- API publica de solo lectura para visualizacion de menus (sin autenticacion)

## Inicio rapido local

```bash
cp .env.example .env
DB_PASSWORD=postgres docker compose up -d --build
DB_PASSWORD=postgres docker compose exec php php artisan migrate --seed --force --no-interaction
curl -k https://localhost:8443/api/hello
```

## Documentacion

- [Guia Rapida](./guia-rapida)
- [Arquitectura](./arquitectura)
- [Defensa del proyecto](./defensa)

## Scripts utiles

```bash
# Documentacion
npm run docs:dev        # servidor local VitePress
npm run docs:build      # compilar docs
npm run docs:preview    # previsualizar build

# Tests
npm test                # Playwright e2e

# Smoke test de produccion
npm run smoke:render
```

## CI/CD

- Publicacion de documentacion en Pages: `.github/workflows/docs-deploy.yml`
- Smoke test de produccion: `.github/workflows/render-smoke-test.yml`

## Seguridad destacada

- Tokens de salud (`HEALTH_CHECK_TOKEN`) protegen `/api/health` en produccion
- CSP, HSTS, X-Frame-Options y Permissions-Policy en cabeceras Nginx
- Token almacenado en `sessionStorage` (no localStorage)
- Validaciones de longitud en todos los campos de entrada
- Mensajes genericos ante errores de autenticacion para evitar enumeracion de usuarios