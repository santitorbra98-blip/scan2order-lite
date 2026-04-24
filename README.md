# Scan2Order Lite

Aplicacion web para crear y gestionar cartas digitales de restaurantes.

## Resumen rapido

- Backend: Laravel 12 + Sanctum + PostgreSQL
- Frontend: Vue 3 + Vite + Pinia
- Infra local: Docker Compose
- Despliegue recomendado: Render (plan gratuito)
- Documentacion: VitePress publicada en GitHub Pages

## Estado actual

- Arranque local validado.
- Endpoints de salud operativos: /api/hello y /api/health.
- Despliegue en Render preparado con [render.yaml](render.yaml).
- Smoke test de produccion automatizado por GitHub Actions.

## Inicio rapido local

```bash
cp .env.example .env
DB_PASSWORD=postgres docker compose up -d --build
DB_PASSWORD=postgres docker compose exec -T php php artisan migrate --seed --force --no-interaction
curl -k https://localhost:8443/api/hello
```

## Documentacion completa

La documentacion de evaluacion y operacion esta en [docs](docs):

- [Paso a paso completo](docs/paso-a-paso-completo.md)
- [Inicio](docs/index.md)
- [Guia Rapida](docs/guia-rapida.md)
- [Arquitectura](docs/arquitectura.md)
- [Checklist de Produccion](docs/produccion-checklist.md)
- [Despliegue Render](docs/deploy-render.md)
- [Smoke Tests explicados](docs/smoke-tests.md)
- [Guia para defensa del proyecto](docs/guia-profesor.md)

## Scripts utiles

```bash
npm run docs:dev
npm run docs:build
npm run docs:preview
npm run smoke:render
npm test
```

## CI/CD configurado

- Publicacion de documentacion en Pages: [.github/workflows/docs-deploy.yml](.github/workflows/docs-deploy.yml)
- Smoke test de produccion: [.github/workflows/render-smoke-test.yml](.github/workflows/render-smoke-test.yml)
