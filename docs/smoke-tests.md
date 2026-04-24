# Smoke tests explicados facil

## Que es un smoke test

Un smoke test es una prueba rapida para responder: la app esta viva y funciona lo mas critico.

En este proyecto comprueba solo 3 cosas:

1. El backend responde: GET /api/hello.
2. La app y la base de datos estan sanas: GET /api/health.
3. El login funciona: POST /api/login.

Si una de esas 3 falla, el workflow falla.

## Donde esta implementado

- Script: [scripts/smoke-render.mjs](../scripts/smoke-render.mjs)
- Workflow: [\.github/workflows/render-smoke-test.yml](../.github/workflows/render-smoke-test.yml)

## Que son los secrets (sin tecnicismos)

Los secrets son valores privados en GitHub (contrasenas, tokens, urls internas).
No se guardan en texto plano en el repositorio.

Para este smoke test necesitas 4:

1. RENDER_SMOKE_URL
2. RENDER_SMOKE_HEALTH_TOKEN
3. RENDER_SMOKE_LOGIN
4. RENDER_SMOKE_PASSWORD

## Como crear los secrets paso a paso

1. Abre tu repo en GitHub.
2. Entra a Settings.
3. En el menu lateral: Secrets and variables > Actions.
4. Pulsa New repository secret.
5. Crea uno por uno:

- Name: RENDER_SMOKE_URL
- Secret: https://scan2order-lite.onrender.com

- Name: RENDER_SMOKE_HEALTH_TOKEN
- Secret: el token real que pusiste en HEALTH_CHECK_TOKEN en Render

- Name: RENDER_SMOKE_LOGIN
- Secret: email del usuario de prueba (ejemplo smoke@scan2order-lite.onrender.com)

- Name: RENDER_SMOKE_PASSWORD
- Secret: contrasena de ese usuario de prueba

## Como ejecutar la prueba en GitHub

1. Ve a Actions.
2. Entra en Render Smoke Test.
3. Pulsa Run workflow.
4. Espera resultado:

- OK /api/hello
- OK /api/health
- OK /api/login

## Recomendacion practica

Usa un usuario dedicado solo para smoke test. No uses tu usuario personal de admin.

## Ejecucion local manual

```bash
SMOKE_BASE_URL=https://scan2order-lite.onrender.com \
SMOKE_HEALTH_TOKEN=TU_TOKEN \
SMOKE_LOGIN=smoke@scan2order-lite.onrender.com \
SMOKE_PASSWORD=TuPasswordSeguro \
npm run smoke:render
```
