# Guia final: subir a GitHub y dejar todo desplegable

## 1) Crear repositorio en GitHub

1. Crea un repo vacio (sin README inicial).
2. Copia la URL remota (HTTPS o SSH).

## 2) Subir codigo local

Desde la raiz del proyecto:

```bash
git init
git add .
git commit -m "feat: production checklist, docs site and render smoke test"
git branch -M main
git remote add origin <TU_URL_GITHUB>
git push -u origin main
```

## 3) Activar deploy automatico de documentacion

1. Ve a `Settings -> Pages`.
2. En Source selecciona `GitHub Actions`.
3. Haz un commit en `docs/` o ejecuta manualmente el workflow `Deploy Documentation`.

Resultado esperado:

- La docs quedara publicada en `https://<tu-usuario>.github.io/<tu-repo>/`.

## 4) Configurar secretos para smoke test de Render

En `Settings -> Secrets and variables -> Actions`, crea:

- `RENDER_SMOKE_URL=https://scan2order-lite.onrender.com`
- `RENDER_SMOKE_HEALTH_TOKEN=<token_de_health>`
- `RENDER_SMOKE_LOGIN=<usuario_smoke>`
- `RENDER_SMOKE_PASSWORD=<password_smoke>`

## 5) Ejecutar smoke test post-deploy

1. Ve a `Actions -> Render Smoke Test`.
2. Pulsa `Run workflow`.
3. Si todo va bien, veras checks OK para hello, health y login.

## 6) Desplegar app en Render

1. En Render: `New -> Blueprint`.
2. Selecciona este repo.
3. Render usara `render.yaml` automaticamente.
4. Completa variables pendientes (`sync: false`).
5. Primer deploy con `RUN_MIGRATIONS=true`, despues `false`.
