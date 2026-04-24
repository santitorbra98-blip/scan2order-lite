# Paso a paso completo: local -> GitHub -> Render -> GitHub Actions

Esta guia esta pensada para seguirse en orden y dejar todo funcionando.

## Fase 1. Validar en local

### 1) Arrancar contenedores

Desde la raiz del proyecto:

```bash
DB_PASSWORD=postgres DB_PORT=55433 docker compose up -d --build
```

### 2) Ejecutar migraciones y seed

```bash
DB_PASSWORD=postgres DB_PORT=55433 docker compose exec -T php php artisan migrate --seed --force --no-interaction
```

### 3) Comprobar salud

```bash
curl -k https://localhost:8443/api/hello
curl -k https://localhost:8443/api/health
```

Resultado esperado:

- hello devuelve 200.
- health devuelve 200.

## Fase 2. Subir a GitHub

### 1) Crear repositorio en GitHub

- Crea un repo vacio (sin README inicial).
- Copia la URL remota.

### 2) Conectar remoto y subir

```bash
git remote add origin TU_URL_GITHUB
git add .
git commit -m "docs: guia completa de despliegue y configuracion"
git push -u origin main
```

Si ya existe remoto y da error en `git remote add`, usa:

```bash
git remote set-url origin TU_URL_GITHUB
```

## Fase 3. Desplegar en Render

### 1) Crear servicios desde blueprint

- Entra en Render.
- New -> Blueprint.
- Selecciona el repo.
- Render usara [render.yaml](../render.yaml).

### 2) Configurar variables obligatorias

Usa la tabla exacta de [Checklist de Produccion](./produccion-checklist).

Especial atencion a:

- APP_KEY
- APP_URL
- FRONTEND_URL
- SANCTUM_STATEFUL_DOMAINS
- CORS_ALLOWED_ORIGINS
- HEALTH_CHECK_TOKEN
- MAIL_* y LEGAL_*

### 3) Primer deploy

- Mantener RUN_MIGRATIONS=true.
- Lanza deploy.
- Verifica:
  - GET /api/hello
  - GET /api/health?token=TU_TOKEN

### 4) Endurecimiento post primer deploy

- Cambiar RUN_MIGRATIONS=false.

## Fase 4. Configurar GitHub Actions (docs + smoke)

## Parte A. Publicar documentacion en GitHub Pages

### 1) Activar Pages

- Repo -> Settings -> Pages.
- Source: GitHub Actions.

### 2) Lanzar deploy de docs

- Haz push a main o ejecuta workflow Deploy Documentation.
- Workflow: [docs-deploy.yml](../.github/workflows/docs-deploy.yml)

## Parte B. Configurar smoke test

### 1) Que es

Smoke test = prueba rapida para validar que en produccion todo lo basico esta vivo.

Comprueba:

- /api/hello
- /api/health
- /api/login

### 2) Crear secrets en GitHub

Ruta:

- Settings -> Secrets and variables -> Actions -> New repository secret

Crear estos 4:

1. RENDER_SMOKE_URL = https://scan2order-lite.onrender.com
2. RENDER_SMOKE_HEALTH_TOKEN = token real de HEALTH_CHECK_TOKEN
3. RENDER_SMOKE_LOGIN = email del usuario smoke
4. RENDER_SMOKE_PASSWORD = password del usuario smoke

### 3) Ejecutar smoke test

- Ir a Actions.
- Seleccionar Render Smoke Test.
- Run workflow.

Workflow: [render-smoke-test.yml](../.github/workflows/render-smoke-test.yml)

## Fase 5. Checklist final para cerrar

- Local validado en 200.
- Codigo subido a GitHub.
- Render desplegado y respondiendo.
- GitHub Pages publicado.
- Smoke test en verde.
- RUN_MIGRATIONS en false.
