# DPL — Despliegue de Aplicaciones Web

> Módulo: **Despliegue de aplicaciones web** (10 puntos)  
> Herramientas: Docker · Docker Compose · NGINX · GitHub Actions · VitePress · GitHub Pages

---

## 1. Instalación y configuración del stack (1,5 pts)

El proyecto define toda su infraestructura como código. No hay configuración manual de servicios.

### Stack completo

| Capa | Tecnología | Fichero de definición |
|------|-----------|----------------------|
| Servidor web | NGINX | [`docker/nginx/Dockerfile`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docker/nginx/Dockerfile) + [`docker/nginx/default.conf`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docker/nginx/default.conf) |
| Backend | PHP 8.4-FPM + Laravel 11 | [`docker/php/Dockerfile`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docker/php/Dockerfile) |
| Frontend | Vue 3 + Vite (build estático) | [`frontend/Dockerfile`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/Dockerfile) |
| Base de datos | PostgreSQL 15 | `docker-compose.yml` |
| Scheduler | Laravel `artisan schedule:run` | `docker-compose.yml` |
| Producción | Imagen all-in-one (Nginx + PHP + Scheduler) | [`Dockerfile.railway`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/Dockerfile.railway) |

### Dockerfile multi-stage (producción)

```dockerfile
# Dockerfile.railway — build de producción
# Fase 1: compilar el frontend
FROM node:20-alpine AS frontend-build
WORKDIR /app
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# Fase 2: imagen PHP con todo integrado
FROM php:8.4-fpm-alpine
# instala NGINX, supervisor, extensiones PHP...
COPY --from=frontend-build /app/dist /var/www/frontend
COPY backend/ /var/www/backend
```

**Referencia**: [`Dockerfile.railway`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/Dockerfile.railway)

---

## 2. Orquestación con Docker Compose (1 pt)

`docker-compose.yml` define **5 servicios** que se levantan con un solo comando:

```bash
docker compose up -d --build
```

```yaml
# docker-compose.yml (esquema simplificado)
services:
  nginx:
    build: ./docker/nginx
    ports: ["8080:80", "8443:443"]
    depends_on: [php, frontend]

  php:
    build: ./docker/php
    depends_on: [postgres]
    environment:
      DB_HOST: postgres
      DB_DATABASE: scan2order

  postgres:
    image: postgres:15-alpine
    volumes: [pgdata:/var/lib/postgresql/data]

  frontend:
    build: ./frontend
    # Solo genera el build estático; NGINX lo sirve

  scheduler:
    build: ./docker/php
    command: ["sh", "-c", "while true; do php artisan schedule:run; sleep 60; done"]
    depends_on: [php]
```

**Referencia**: [`docker-compose.yml`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docker-compose.yml)

### Comprobación local

```bash
# Levantar
DB_PASSWORD=postgres DB_PORT=55433 docker compose up -d --build

# Verificar rutas (debe mostrar 48 rutas)
DB_PASSWORD=postgres DB_PORT=55433 \
  docker compose exec php php artisan route:list | tail -5

# Health check
curl http://localhost:8080/api/health
```

---

## 3. Despliegue en dos entornos diferenciados (1,5 pts)

### Entorno 1 — Local / Desarrollo

Definido por `docker-compose.yml` + variables de `.env`:

```env
APP_ENV=local
APP_DEBUG=true
DB_PORT=55433
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_LEVEL=debug
```

Levantado con `docker compose up`. El desarrollador tiene NGINX local en `localhost:8080`.

### Entorno 2 — Producción (Railway / Render)

Definido por [`render.yaml`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/render.yaml) (blueprint de Render) y por [`Dockerfile.railway`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/Dockerfile.railway):

```yaml
# render.yaml (fragmento)
services:
  - type: web
    name: scan2order-lite
    dockerfile: Dockerfile.railway
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: false
      - key: LOG_LEVEL
        value: warning
    healthCheckPath: /api/hello
    autoDeploy: true
```

**Diferencias clave entre entornos**

| Configuración | Local | Producción |
|--------------|-------|-----------|
| `APP_DEBUG` | `true` | `false` |
| `QUEUE_CONNECTION` | `sync` | `database` |
| `CACHE_STORE` | `file` | `database` |
| Base datos | PostgreSQL local | PostgreSQL Railway cloud |
| SSL | Certificados locales (self-signed) | Certificado Let's Encrypt automático |
| Imagen Docker | Múltiples contenedores (Compose) | Un solo contenedor (Dockerfile.railway) |

**Referencia guía de despliegue**: [`docs/deploy-render.md`](./deploy-render)

> ⚠️ **Portainer / Kamal** — ver sección [Qué falta](#qué-falta).

---

## 4. Control de versiones (1 pt)

El proyecto usa **Git** con repositorio en GitHub:

- Repositorio: [`github.com/santitorbra98-blip/scan2order-lite`](https://github.com/santitorbra98-blip/scan2order-lite)
- Rama principal: `main`
- Todos los ficheros de infraestructura (Dockerfiles, workflows, config) están versionados

```bash
# Ver historial de commits
git log --oneline --graph --all

# El despliegue en Render/Railway se dispara automáticamente
# al hacer push a main (autoDeploy: true en render.yaml)
```

---

## 5. CI/CD con GitHub Actions (1,5 pts)

### Workflow 1 — Despliegue de documentación

**Fichero**: [`.github/workflows/docs-deploy.yml`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/.github/workflows/docs-deploy.yml)

```yaml
on:
  push:
    branches: [main, master]
    paths:
      - 'docs/**'
      - 'package.json'
      - '.github/workflows/docs-deploy.yml'

jobs:
  build:
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: npm }
      - run: npm ci
      - run: npm run docs:build
        env:
          DOCS_BASE: /${{ github.event.repository.name }}/
      - uses: actions/upload-pages-artifact@v3
        with: { path: docs/.vitepress/dist }

  deploy:
    environment: github-pages
    steps:
      - uses: actions/deploy-pages@v4
```

**Flujo**: `git push` → build VitePress → publicar en GitHub Pages automáticamente.

### Workflow 2 — Smoke tests automáticos en producción

**Fichero**: [`.github/workflows/render-smoke-test.yml`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/.github/workflows/render-smoke-test.yml)

```yaml
on:
  schedule:
    - cron: '30 7 * * *'      # Cada día a las 07:30 UTC
  workflow_dispatch:            # También manual

jobs:
  smoke:
    steps:
      - uses: actions/checkout@v4
      - run: npm ci
      - run: node scripts/smoke-render.mjs
        env:
          SMOKE_BASE_URL: ${{ secrets.SMOKE_BASE_URL }}
          SMOKE_EMAIL:    ${{ secrets.SMOKE_EMAIL }}
          SMOKE_PASSWORD: ${{ secrets.SMOKE_PASSWORD }}
          SMOKE_TOKEN:    ${{ secrets.SMOKE_TOKEN }}
```

El script `smoke-render.mjs` prueba:
1. `GET /api/hello` → 200
2. `GET /api/health` → 200 (requiere `SMOKE_TOKEN`)
3. `POST /api/auth/login` → 200 + token

**Referencia**: [`scripts/smoke-render.mjs`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/scripts/smoke-render.mjs)

---

## 6. Documentación automática con VitePress (1 pt)

La documentación se genera con **[VitePress](https://vitepress.dev/)**, el generador de sitios estáticos oficial del ecosistema Vue. Es equivalente a MkDocs pero nativo de Vue/Vite.

```bash
# Desarrollar documentación localmente
npm run docs:dev

# Build para producción
npm run docs:build

# Preview del build
npm run docs:preview
```

La configuración completa del sitio está en:

**Referencia**: [`docs/.vitepress/config.mjs`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docs/.vitepress/config.mjs)

```js
// docs/.vitepress/config.mjs
export default defineConfig({
  title: 'Scan2Order Lite',
  lang: 'es-ES',
  base: docsBase,         // ajusta automáticamente según el repo de GitHub
  cleanUrls: true,
  lastUpdated: true,
  themeConfig: {
    search: { provider: 'local' },
    // nav y sidebar configurados ...
  }
})
```

### Documentos generados

| Fichero | Contenido |
|---------|-----------|
| [`index.md`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docs/index.md) | Portada del proyecto |
| [`arquitectura.md`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docs/arquitectura.md) | Diagrama y descripción de la arquitectura |
| [`api-auth.md`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docs/api-auth.md) | Referencia de la API de autenticación |
| [`paso-a-paso-completo.md`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docs/paso-a-paso-completo.md) | Guía completa de instalación local |
| [`deploy-render.md`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docs/deploy-render.md) | Guía de despliegue en Render/Railway |
| [`produccion-checklist.md`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docs/produccion-checklist.md) | Checklist de puesta en producción |
| [`smoke-tests.md`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docs/smoke-tests.md) | Cómo usar y extender los smoke tests |
| `dew.md` | Rubrica DEW (este directorio) |
| `dsw.md` | Rubrica DSW |
| `dpl.md` | Rubrica DPL |
| `dor.md` | Rubrica DOR |

---

## 7. Documentación desplegada en GitHub Pages (1,5 pts)

Cada `push` a `main` que modifique la carpeta `docs/` dispara automáticamente el workflow `docs-deploy.yml`, que compila VitePress y publica el resultado en **GitHub Pages**.

La URL pública de la documentación es:

```
https://santitorbra98-blip.github.io/scan2order-lite/
```

El workflow gestiona permisos de GitHub Pages (`id-token: write`, `pages: write`) sin necesidad de configuración manual.

---

## ❌ Qué falta

| Criterio | Estado | Explicación |
|----------|--------|-------------|
| **Portainer / Kamal** | No implementado | La rúbrica menciona "Portainer/Kamal" como herramienta de despliegue en dos entornos. El proyecto usa **Render** (SaaS) en producción, que cumple el objetivo de "dos entornos diferenciados", pero no usa Portainer ni Kamal. Si se quiere cubrir literalmente: Portainer puede levantarse como contenedor Docker adicional para gestionar el stack local; Kamal requeriría un servidor VPS propio. |

> **Nota**: usar Render/Railway como entorno de producción diferenciado del entorno local Docker es funcionalmente equivalente al objetivo del criterio (dos entornos con configuraciones distintas), aunque no usa las herramientas específicas citadas.
