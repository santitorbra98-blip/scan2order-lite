# Scan2Order Lite

Scan2Order Lite es una plataforma para digitalizacion y gestion de cartas de restaurantes.

## Stack tecnologico

- Backend: Laravel 12 + Sanctum + PostgreSQL
- Frontend: Vue 3 + Vite + Pinia
- Infra local: Docker Compose
- Deploy recomendado: Render (plan gratuito)
- Documentacion: VitePress + GitHub Pages

## Estado del proyecto

- Arranque local validado con Docker.
- Health checks funcionales (`/api/hello`, `/api/health`).
- Blueprint de Render listo (`render.yaml`).
- Smoke test post-deploy automatizado listo (GitHub Actions).
- Sitio de documentacion profesional listo para GitHub Pages.

## Inicio rapido

```bash
cp .env.example .env
DB_PASSWORD=postgres docker compose up -d --build
DB_PASSWORD=postgres docker compose exec -T php php artisan migrate --seed --force --no-interaction
curl -k https://localhost:8443/api/hello
```

## Documentacion completa

Toda la documentacion esta en `docs/` y se publica automaticamente en GitHub Pages.

- Inicio: `docs/index.md`
- Guia rapida: `docs/guia-rapida.md`
- Arquitectura: `docs/arquitectura.md`
- Checklist de produccion: `docs/produccion-checklist.md`
- Deploy Render: `docs/deploy-render.md`
- Smoke tests: `docs/smoke-tests.md`

## Despliegue del proyecto (Render)

1. Haz push del repositorio a GitHub.
2. En Render: `New -> Blueprint`.
3. Selecciona el repo y aplica `render.yaml`.
4. Completa variables `sync: false`.
5. Primer deploy con `RUN_MIGRATIONS=true`.
6. Tras migrar, cambia a `RUN_MIGRATIONS=false`.

Guia detallada: `DEPLOY_RENDER.md` y `docs/deploy-render.md`.

## Publicar documentacion en GitHub Pages

El workflow `.github/workflows/docs-deploy.yml` publica automaticamente la docs al hacer push en `main/master`.

Pasos en GitHub:

1. `Settings -> Pages`.
2. Source: `GitHub Actions`.
3. Push a `main`.
4. Esperar workflow `Deploy Documentation`.

## Smoke test post-deploy

Workflow: `.github/workflows/render-smoke-test.yml`

Secrets requeridos:

- `RENDER_SMOKE_URL`
- `RENDER_SMOKE_HEALTH_TOKEN`
- `RENDER_SMOKE_LOGIN`
- `RENDER_SMOKE_PASSWORD`

Ejecucion manual:

1. `Actions -> Render Smoke Test`.
2. `Run workflow`.

## Scripts utiles

```bash
npm run docs:dev
npm run docs:build
npm run docs:preview
npm run smoke:render
npm test
```

## Nota sobre GitHub (subida del proyecto)

No puedo autenticarme en tu cuenta desde este entorno para hacer `git push` por ti, pero el repositorio ya queda preparado para subirlo y desplegarlo sin cambios adicionales.
