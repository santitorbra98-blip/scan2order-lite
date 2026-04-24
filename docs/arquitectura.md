# Arquitectura

## Vista general

Scan2Order Lite ejecuta en una sola aplicacion Docker con:

- Nginx como reverse proxy y servidor de SPA.
- PHP-FPM con Laravel 12 para API.
- Frontend Vue 3 compilado con Vite.
- PostgreSQL como base de datos.

## Componentes

- `frontend/`: SPA Vue 3 + Pinia + Vue Router.
- `backend/`: API Laravel con autenticacion Sanctum y modulos de negocio.
- `docker/`: imagenes y configuraciones locales.
- `Dockerfile.railway`: imagen unica para despliegue cloud.

## Flujo de peticiones

1. Cliente solicita recurso.
2. Nginx enruta:
   - `/api/*` y `/sanctum/*` a Laravel.
   - `/assets/*` y rutas SPA a build frontend.
3. Laravel procesa middleware de seguridad, CORS y auth.
4. Respuesta JSON para API o HTML para SPA.

## Seguridad

- Header hardening en middleware/servidor.
- Throttling en autenticacion.
- Endpoint `/api/health` protegido por token en produccion.
- Sanitizacion de origenes CORS por variable de entorno.

## Decisiones tecnicas

- `CACHE_STORE=file` para simplicidad y evitar dependencia de tabla cache en entornos iniciales.
- `QUEUE_CONNECTION=sync` para reducir complejidad operativa en plan gratuito.
- Imagen Docker unica para reducir acoplamiento y costo de infraestructura.
