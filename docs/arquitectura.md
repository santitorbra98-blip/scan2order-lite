# Arquitectura del Sistema

---

## Visión general

Scan2Order Lite es una aplicación web SaaS para digitalización de cartas de restaurante. Una misma URL sirve:

- **Carta pública** — el comensal escanea un QR y accede a `/restaurant/:id` sin autenticación.
- **Panel de administración** — el restaurador gestiona sus catálogos desde `/admin/*` con login.
- **API REST** — backend Laravel que responde a ambas interfaces bajo el prefijo `/api`.

---

## Diagrama de componentes

```
┌─────────────────────────────────────────────────────────┐
│                        NGINX                            │
│   /api/*  /sanctum/* ──► PHP-FPM (Laravel)              │
│   /storage/*         ──► ficheros subidos (sin PHP)     │
│   /*                 ──► dist/ Vue (SPA fallback)       │
└────────────┬──────────────────────┬────────────────────-┘
             │                      │
     ┌───────▼──────┐      ┌────────▼────────┐
     │  Laravel 11  │      │   Vue 3 (Vite)  │
     │  PHP 8.4-FPM │      │   Pinia · Router│
     └───────┬──────┘      └────────────────-┘
             │
     ┌───────▼──────┐
     │ PostgreSQL 15│
     └──────────────┘
```

En local hay un quinto servicio (`scheduler`) que ejecuta `artisan schedule:run` cada 60 segundos para tareas periódicas (limpieza de códigos MFA expirados, etc.).

---

## Flujo de una petición autenticada

```
Usuario (navegador)
  │  clic en "Mis restaurantes"
  ▼
Vue Router
  │  comprueba meta.requiresAuth
  │  lee token de Pinia store (origen: sessionStorage)
  ▼
restaurantService.getAll()  →  api.js
  │  añade  Authorization: Bearer <token>
  │  GET /api/restaurants
  ▼
NGINX
  │  prefijo /api → reenvía a PHP-FPM :9000
  ▼
Laravel Kernel  (middlewares en orden)
  │  1. CORS — valida origen permitido
  │  2. throttle:api — máx 60 req/min por usuario/IP
  │  3. auth:sanctum — busca el token en personal_access_tokens
  ▼
RestaurantController@index
  │  admin    → solo sus restaurantes (filtro user_restaurant)
  │  superadmin → todos
  ▼
JSON paginado  { data: [...], meta: { current_page, last_page } }
  ▼
api.js resuelve la promesa
  ▼
restaurants.value = data.data   →   Vue actualiza el DOM
```

---

## Estructura de carpetas

```
scan2order-lite/
├── backend/                  Laravel 11
│   ├── app/
│   │   ├── Http/Controllers/ ← un controller por recurso
│   │   ├── Http/Requests/    ← validación de entrada (FormRequests)
│   │   ├── Http/Middleware/  ← guards, CORS, rate limiting
│   │   ├── Models/           ← Eloquent ORM
│   │   ├── Policies/         ← autorización por recurso
│   │   ├── Jobs/             ← tareas asíncronas (audit log)
│   │   └── Services/         ← lógica reutilizable (MFA, etc.)
│   ├── routes/api.php        ← definición de rutas
│   └── database/migrations/  ← esquema versionado
│
├── frontend/                 Vue 3 + Vite
│   └── src/
│       ├── views/            ← páginas (admin/*, client/*, legal/*)
│       ├── components/       ← modales y cards reutilizables
│       ├── composables/      ← useToast, useImageField, useLegalMeta
│       ├── stores/           ← Pinia (auth)
│       ├── services/         ← api.js, catalogService, restaurantService
│       └── router/           ← rutas + guards
│
├── docker/                   imágenes locales (nginx, php)
├── Dockerfile.railway         imagen única para producción
├── docker-compose.yml        orquestación local
└── render.yaml               blueprint de despliegue en Render
```

---

## Base de datos

### Diagrama entidad-relación

```
roles ──────────────── role_permission ──── permissions
  │
  └── users ─────────── user_restaurant ─── restaurants
        │                                        │
        │                                    catalogs
        │                                        │
        └── audit_logs                       sections
                                                 │
              email_mfa_codes              products
              personal_access_tokens
              settings
              jobs / failed_jobs
```

### Tablas principales

| Tabla | Propósito |
|-------|-----------|
| `roles` | Roles del sistema (`admin`, `superadmin`) |
| `permissions` | Permisos atómicos (`manage_products`, …) |
| `role_permission` | Pivote rol ↔ permiso |
| `users` | Usuarios con rol, estado y campos legales (RGPD) |
| `user_restaurant` | Qué admin gestiona qué restaurante |
| `restaurants` | Restaurantes con horario (JSON) e imagen |
| `catalogs` | Cartas de un restaurante (nombre, activo, orden) |
| `sections` | Secciones dentro de un catálogo |
| `products` | Productos: precio, alérgenos (JSON), dietas (JSON), imagen |
| `audit_logs` | Registro de acciones (actor, acción, recurso, IP) |
| `email_mfa_codes` | Códigos OTP para MFA por email con TTL y contador de intentos |
| `personal_access_tokens` | Tokens Sanctum de sesión |
| `settings` | Configuración global clave-valor (límites, etc.) |
| `jobs` / `failed_jobs` | Cola de Laravel para tareas asíncronas |

### Campos destacados de `users`

- `role_id` — FK a `roles`; un usuario tiene un único rol global
- `status` — `active` / `inactive` / `suspended`
- `terms_accepted_at`, `privacy_accepted_at`, `legal_acceptance_ip` — trazabilidad RGPD
- `created_by` — qué superadmin creó este usuario (auto-referencia)
- `soft_deletes` — los usuarios se borran lógicamente, sus datos quedan intactos

---

## Jerarquía de datos

```
Restaurante
└── Catálogo  (ej. "Carta de verano")
    └── Sección  (ej. "Entrantes")
        └── Producto  (ej. "Croquetas" — precio, alérgenos, imagen)
```

Un admin puede tener varios restaurantes asignados. Cada restaurante puede tener múltiples catálogos (solo uno activo a la vez se muestra en la carta pública).

---

## Seguridad

| Capa | Mecanismo |
|------|-----------|
| Transporte | HTTPS (TLS 1.2+); HSTS en producción |
| Autenticación | Sanctum Bearer token en `sessionStorage` |
| Autorización | RBAC propio + `RestaurantPolicy` via `Gate::policy()` |
| Rate limiting | 5 limitadores distintos según el endpoint |
| Headers HTTP | CSP, X-Frame-Options, X-Content-Type-Options, Permissions-Policy |
| Uploads | Solo MIME validados; zona `/storage` sin ejecución PHP |
| Auditoría | Toda acción sensible queda en `audit_logs` (asíncrono) |

---

## Ejecutar en local

### Requisitos

- Docker Desktop (o Docker Engine + Compose) instalado y en marcha
- Git

### Pasos

```bash
# 1. Clonar el repositorio
git clone https://github.com/santitorbra98-blip/scan2order-lite.git
cd scan2order-lite

# 2. Crear el fichero de variables de entorno del backend
cp backend/.env.example backend/.env

# 3. Levantar todos los servicios (primera vez tarda ~2 min en construir)
DB_PASSWORD=postgres DB_PORT=55433 docker compose up -d --build

# 4. Generar la clave de la aplicación y migrar la base de datos
DB_PASSWORD=postgres DB_PORT=55433 docker compose exec php php artisan key:generate
DB_PASSWORD=postgres DB_PORT=55433 docker compose exec php php artisan migrate --seed

# 5. Crear un superadmin inicial
DB_PASSWORD=postgres DB_PORT=55433 docker compose exec php php artisan app:create-superadmin
```

La aplicación queda disponible en **http://localhost:8080**.

### Comandos útiles

```bash
# Ver logs en tiempo real
docker compose logs -f php

# Verificar que todas las rutas están registradas
DB_PASSWORD=postgres DB_PORT=55433 docker compose exec php php artisan route:list

# Parar los servicios
docker compose down

# Parar y borrar la base de datos (reset completo)
docker compose down -v
```

---

## Despliegue en Render

Render es la plataforma cloud donde está alojada la versión de producción. El despliegue es **completamente automático** a partir del repositorio de GitHub.

### Cómo funciona

1. **`render.yaml`** — blueprint de infraestructura que declara el servicio web y la base de datos PostgreSQL. Render lo lee y crea los recursos automáticamente al conectar el repositorio.

2. **`Dockerfile.railway`** — imagen de producción en dos fases:
   - Fase 1 (Node 20): compila el frontend con Vite → genera `dist/`
   - Fase 2 (PHP 8.4-alpine): instala NGINX, Supervisor y las extensiones PHP; copia solo el `dist/` compilado

3. **Variables de entorno** — Render inyecta automáticamente `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` desde la base de datos PostgreSQL gestionada. Las demás se configuran una vez en el panel de Render.

4. **Migraciones automáticas** — la variable `RUN_MIGRATIONS=true` hace que el `entrypoint.sh` ejecute `php artisan migrate --force` en cada arranque del contenedor antes de servir tráfico.

5. **`autoDeploy: true`** — cada `git push` a `main` dispara un nuevo deploy. Render construye la imagen, la valida con el `healthCheckPath: /api/hello` y solo entonces reemplaza el contenedor anterior (zero-downtime).

### Diferencias local vs producción

| Parámetro | Local | Producción (Render) |
|-----------|-------|---------------------|
| `APP_DEBUG` | `true` | `false` |
| `QUEUE_CONNECTION` | `sync` | `sync` (plan gratuito) |
| `CACHE_STORE` | `file` | `file` |
| Base de datos | PostgreSQL en Docker | PostgreSQL gestionado por Render |
| SSL | certificado local self-signed | Let's Encrypt automático |
| Imagen Docker | múltiples contenedores (Compose) | un contenedor único (Dockerfile.railway) |

