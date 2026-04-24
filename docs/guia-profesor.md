# Guia para profesor

## Objetivo de la defensa

Mostrar que el proyecto esta:

1. Bien documentado.
2. Desplegable en entorno real gratuito.
3. Verificable con pruebas automaticas post-deploy.

## Demo recomendada (5 minutos)

### 1) Arquitectura (1 min)

Explicar brevemente:

- Frontend Vue.
- API Laravel.
- Base de datos PostgreSQL.
- Orquestacion con Docker en local y Render en produccion.

Apoyo visual: [arquitectura](./arquitectura)

### 2) Despliegue (1.5 min)

Mostrar:

- Archivo de blueprint: [render.yaml](../render.yaml)
- Guia operativa: [deploy-render](./deploy-render)
- Checklist exacto: [produccion-checklist](./produccion-checklist)

### 3) Calidad operativa (1.5 min)

Mostrar en GitHub Actions:

- Workflow de docs: [docs-deploy.yml](../.github/workflows/docs-deploy.yml)
- Workflow de smoke test: [render-smoke-test.yml](../.github/workflows/render-smoke-test.yml)

Explicar que el smoke test valida hello, health y login en produccion.

### 4) Evidencia funcional (1 min)

En navegador o curl:

- /api/hello -> 200
- /api/health -> 200 con token
- login de usuario smoke -> 200 con token

## Rubrica de entrega sugerida

- Documentacion tecnica clara: completado.
- Despliegue reproducible: completado.
- Control de calidad post-deploy: completado.
- Seguridad basica (secretos y health token): completado.

## Preguntas tipicas y respuesta corta

- Por que Render: minimiza complejidad y costo para un MVP academico.
- Que evita el smoke test: detectar despliegues rotos antes de que afecten al usuario.
- Por que secrets: evitar exponer credenciales en el codigo fuente.
