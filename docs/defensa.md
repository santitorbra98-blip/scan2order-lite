# Rúbrica y Defensa del Proyecto

Criterios cubiertos y argumentación técnica por asignatura. El tiempo indicado corresponde al bloque de defensa.

---

## DSW — Desarrollo Web en Entorno Servidor · 8 min

### ✅ PHP + Laravel como backend de la API REST

El backend es una API REST construida con Laravel 11 sobre PHP 8.4-FPM. NGINX actúa como reverse proxy y redirige todo lo que llega a `/api/*` al proceso PHP-FPM. Las rutas están definidas en `backend/routes/api.php` y cada endpoint tiene su propio controlador, FormRequest de validación y, si aplica, su Policy de autorización.

---

### ✅ RBAC propio (sin librerías)

Hay dos roles de sistema: `admin` y `superadmin`. Los permisos van asociados al rol, no al usuario individual. El modelo `User` expone métodos de comprobación que cualquier capa del backend puede usar:

- `hasRole('superadmin')` — compara contra el nombre del rol
- `hasPermission('manage_products')` — itera los permisos del rol; el superadmin los tiene todos
- `canAccessRestaurant($id)` — comprueba la tabla pivote `user_restaurant`

El acceso a cada restaurante se centraliza en `RestaurantPolicy`, registrada mediante `Gate::policy()`. Cualquier controlador invoca `$this->authorize('manage', $restaurant)` y la Policy decide si el acceso es válido.

---

### ✅ Laravel Sanctum (autenticación stateless)

El login devuelve un Bearer token que el frontend guarda en `sessionStorage`. Ese token se incluye en la cabecera `Authorization` de cada petición. El middleware `auth:sanctum` lo valida contra la tabla `personal_access_tokens`. No hay cookies ni sesiones — la API es consumible desde cualquier cliente. El logout elimina el token de la base de datos de forma inmediata.

---

### ✅ Throttling diferenciado por endpoint

Cada flujo de autenticación tiene su propio límite, definido en `RouteServiceProvider` con `RateLimiter::for(...)`:

| Nombre | Límite | Clave |
|--------|--------|-------|
| `api` | 60 req/min | user id o IP |
| `auth-login` | 8 req/min | email + IP (bloquea spray attacks) |
| `auth-register-request` | 4 cada 15 min | IP |
| `auth-forgot-password` | 3 cada 30 min | IP |
| `auth-reset-password` | 5 cada 15 min | IP |

Un atacante que agote el límite de login no afecta al límite del API general ni al de registro.

---

### ✅ Auditoría asíncrona

Cada acción sensible (crear restaurante, cambio de contraseña, login…) se registra en la tabla `audit_logs` con: actor, acción, recurso, IP y user-agent. El registro **no ocurre en el hilo de la petición** — se despacha un `LogAuditAction` job a la cola. Si la cola es `sync` (desarrollo) se ejecuta en el mismo proceso; en producción es asíncrono puro. Si el job falla, escribe un `Log::warning` y no rompe la petición original.

---

### ✅ Mail dinámico y MFA por correo

Toda la lógica de códigos de verificación (registro, recuperación, cambio de email/contraseña) está centralizada en `MfaCodeService`. El servicio genera un código, lo guarda hasheado en `email_mfa_codes` con TTL configurable (`security.mfa_email_code_ttl_minutes`), y envía el Mailable de Laravel. Los límites de intentos se leen de `config/security.php`, que a su vez viene del `.env`, sin tocar código para cambiarlos.

---

## DEW — Desarrollo Web en Entorno Cliente · 7 min

### ✅ Vue 3 + SFC + Composition API

Todos los componentes usan `<script setup>` (Single File Components). La Composition API permite colocar lógica, template y estilos en un mismo fichero sin mezclar responsabilidades. `ref()` crea estado reactivo, `computed()` deriva valores que se actualizan solos, y `watch()` reacciona a cambios. El componente `StatsCard.vue` usa además `<script setup lang="ts">` con genéricos de `defineProps`, cubriendo el requisito de TypeScript.

---

### ✅ Vue Router con lazy loading y guards

Las rutas admin usan carga dinámica (`() => import(...)`) para que el código de cada vista se descargue solo cuando el usuario navega a ella. El guard `router.beforeEach` intercepta cada navegación: comprueba `meta.requiresAuth` y `meta.roles`, y redirige a `/login` o al dashboard según corresponda. Las rutas públicas (carta del cliente, login, legal) no requieren token.

---

### ✅ Pinia — estado global de autenticación

El único store global es `useAuthStore`. Centraliza el token, el objeto usuario, los métodos `login` / `logout`, y los helpers `hasRole` / `hasAnyRole` que usan tanto el router como los componentes. El token se persiste en `sessionStorage` al login y se elimina al logout.

---

### ✅ Props, Emits y composición de componentes

Las vistas admin están descompuestas en modales extraídos como componentes independientes (`ProductModal`, `CatalogModal`, `RestaurantFormModal`, `RestaurantDeleteModal`…). Cada modal recibe datos via `defineProps` y comunica el resultado al padre via `defineEmits`. El flujo siempre es unidireccional: datos bajan por props, eventos suben por emits.

---

### ✅ Composables propios

| Composable | Función |
|-----------|---------|
| `useToast` | Notificación temporal con auto-cierre configurable |
| `useImageField` | Carga de imagen: validación MIME, compresión automática, previsualización |
| `useLegalMeta` | Fetch de metadatos legales con caché y fallback |

Los composables evitan duplicar lógica entre vistas y encapsulan el ciclo de vida de sus efectos internamente.

---

### ✅ localStorage / sessionStorage

El token se almacena en `sessionStorage` (se borra al cerrar el tab). `api.js` lo inyecta automáticamente en la cabecera `Authorization` de cada petición. Hay lógica de migración para sesiones antiguas que usaban `localStorage`.

---

## DPL — Despliegue de Aplicaciones Web · 5 min

### ✅ NGINX + Docker + stack completo

El stack local se levanta con `docker compose up -d --build` e incluye cinco servicios: **nginx** (reverse proxy, SSL local), **php** (Laravel FPM), **postgres** (PostgreSQL 15 con volumen persistente), **frontend** (build de Vite) y **scheduler** (ejecuta `artisan schedule:run` cada 60 s). Cada imagen usa Alpine para minimizar tamaño.

---

### ✅ Dockerfile.railway multistage (dos entornos)

Para producción existe `Dockerfile.railway`, una imagen única que fusiona todo en dos fases:

1. **Fase Node**: compila el frontend con Vite → genera `dist/`
2. **Fase PHP**: instala PHP, NGINX y Supervisor; copia solo el `dist/` compilado (no Node.js)

El resultado es una imagen de ~120 MB autocontenida con frontend, backend, NGINX y scheduler. `render.yaml` la despliega en Render con `autoDeploy: true` y `APP_DEBUG=false`.

Los dos entornos diferenciados son: **local** (Docker Compose, debug activo, cola síncrona) y **producción** (imagen única, debug desactivado, cola de base de datos, SSL automático).

---

### ✅ CI/CD con GitHub Actions

**Workflow 1 — Docs a GitHub Pages** (`.github/workflows/docs-deploy.yml`): cada push a `main` que modifique `docs/` compila VitePress y publica automáticamente en GitHub Pages. Sin intervención manual.

**Workflow 2 — Smoke test diario** (`.github/workflows/render-smoke-test.yml`): cada día a las 07:30 UTC (o al lanzarlo manualmente) prueba tres endpoints críticos de la URL de producción: `/api/hello`, `/api/health` y `POST /api/auth/login`. Si alguno falla, el workflow queda en rojo y GitHub notifica por email.

---

### ✅ Control de versiones

Repositorio en GitHub con rama `main`. Todos los ficheros de infraestructura (Dockerfiles, workflows, render.yaml, config) están versionados junto al código. El despliegue en Render se dispara automáticamente en cada push.

---

### ✅ Documentación desplegada (VitePress + GitHub Pages)

La documentación se genera con VitePress (`npm run docs:build`) y se publica en `https://santitorbra98-blip.github.io/scan2order-lite/`. La configuración en `docs/.vitepress/config.mjs` ajusta el `base` automáticamente según el nombre del repositorio, sin configuración manual de GitHub Pages.

---

## DOR — Diseño de Interfaces Web · 3 min

### ✅ Dos interfaces visuales diferenciadas

**Carta del cliente** (`/restaurant/:id`): interfaz minimalista orientada al móvil, sin autenticación, sin sidebar. El comensal escanea el QR y ve únicamente los catálogos, secciones, productos con foto, precio y alérgenos.

**Panel de administración** (`/admin/*`): sidebar fijo con indicador de ruta activa, cabecera con nombre y rol del usuario, modales de creación/edición, paginación y confirmaciones explícitas para acciones destructivas. Accesible solo con token válido.

---

### ✅ Paleta de colores justificada

| Rol | Hex | Justificación |
|-----|-----|--------------|
| Primario `#667eea` | Violeta azulado | Tecnología y confianza; se diferencia de la paleta roja/naranja del sector restauración |
| Secundario `#764ba2` | Morado | Forma gradiente con el primario; coherencia visual en toda la aplicación |
| Éxito `#48bb78` | Verde | Convención universal para confirmación / estado activo |
| Error `#fc8181` | Rojo suave | Menos agresivo que el rojo puro; alerta sin alarma |
| Fondo `#f7fafc` | Gris muy claro | Reduce fatiga ocular en uso prolongado |

---

### ✅ Responsive sin framework CSS

El diseño adapta el número de columnas sin media queries fijas, usando CSS Grid con `auto-fill` y `minmax`:

```css
grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
```

En móvil se reduce a una columna, en tablet a dos, en escritorio a tres o más. Los modales usan `width: 90vw; max-width: 560px` para adaptarse a cualquier pantalla. No se usa Tailwind ni Bootstrap — CSS nativo con `<style scoped>` por componente.

---

### ✅ Usabilidad

- **Feedback inmediato**: toast de confirmación tras cada acción (useToast, auto-cierre 2,5 s)
- **Estados de carga**: botones deshabilitados con texto "Guardando…" durante peticiones
- **Confirmación de destructivas**: eliminar un restaurante requiere checkbox explícito + botón de confirmar
- **Errores contextuales**: los errores de validación se muestran junto al campo, no solo en consola

---

## SSG — Sistemas de Gestión · 2 min

### ✅ Gestión de usuarios, roles y permisos

El superadmin crea administradores desde `/admin/users`. Cada usuario tiene un único rol y el rol agrupa los permisos. La relación es:

```
usuario ─── rol ─── permisos
              └─── restaurantes asignados (user_restaurant)
```

Un admin solo accede a los restaurantes que le han sido asignados explícitamente. El superadmin accede a todo sin restricción.

---

### ✅ Límites por cuenta

Para prevenir abuso de recursos, `config/security.php` define topes configurables sin tocar código:

| Límite | Por defecto | Variable de entorno |
|--------|------------|---------------------|
| Restaurantes por admin | 3 | `LIMIT_RESTAURANTS_PER_ADMIN` |
| Catálogos por restaurante | 10 | `LIMIT_CATALOGS_PER_RESTAURANT` |
| Secciones por catálogo | 20 | `LIMIT_SECTIONS_PER_CATALOG` |
| Productos por sección | 100 | `LIMIT_PRODUCTS_PER_SECTION` |

El superadmin puede ajustar límites globales adicionales desde `/admin/settings`, que los persiste en la tabla `settings` con `Setting::set()`.

---

### ✅ Auditoría de acciones

Cada acción sensible queda en `audit_logs`: quién (`actor_user_id`), qué (`action`), sobre qué (`resource_type` / `resource_id`), desde dónde (`ip_address`, `user_agent`). El registro es asíncrono para no penalizar el tiempo de respuesta (ver DSW — Auditoría).

---

### ✅ Flujo completo: del clic del usuario al dato en pantalla

```
Clic en "Mis restaurantes"
  → Vue Router comprueba meta.requiresAuth y roles (Pinia store)
  → Restaurants.vue llama a restaurantService.getAll()
  → api.js inyecta el Bearer token en la cabecera HTTP
  → NGINX reenvía /api/restaurants a PHP-FPM
  → Sanctum valida el token en personal_access_tokens
  → RestaurantController filtra por rol (admin: solo los suyos; superadmin: todos)
  → Devuelve JSON paginado
  → restaurants.value = data.data  →  Vue actualiza el DOM
```

En una sola petición intervienen: Vue Router (autorización de navegación), Pinia (token), api.js (cabeceras), NGINX (enrutamiento), Sanctum (autenticación), Policy (autorización de recurso) y el Controller (lógica de negocio).


### RBAC propio

El sistema implementa **Role-Based Access Control (RBAC)** sin librerías externas, directamente sobre Eloquent.

Hay dos roles de sistema: `admin` y `superadmin`. Los permisos son registros de la tabla `permissions`, asociados al rol mediante una relación many-to-many. El modelo `User` expone los métodos de comprobación:

```php
// backend/app/Models/User.php
public function hasRole($roleName): bool
{
    return $this->role?->name === $roleName;
}

public function hasPermission($permissionName): bool
{
    if ($this->hasRole('superadmin')) return true;   // superadmin lo tiene todo
    return $this->permissions()->contains('name', $permissionName);
}
```

La autorización de acceso a un restaurante concreto se delega a `RestaurantPolicy`:

```php
// backend/app/Policies/RestaurantPolicy.php
public function manage(User $user, Restaurant $restaurant): bool
{
    if ($user->hasRole('superadmin')) return true;
    return $user->hasPermission('manage_products')
        && $user->canAccessRestaurant($restaurant->id);
}
```

Registrada en `AppServiceProvider` con `Gate::policy(Restaurant::class, RestaurantPolicy::class)`, cualquier controlador puede comprobar el acceso con una sola línea:

```php
$this->authorize('manage', $restaurant);
```

---

### Laravel Sanctum

Sanctum gestiona la autenticación **stateless mediante Bearer tokens**. El flujo es:

1. `POST /api/auth/login` → crea un token de API en la tabla `personal_access_tokens` y lo devuelve.
2. El frontend lo guarda en `sessionStorage` y lo añade a todas las peticiones en la cabecera `Authorization: Bearer <token>`.
3. El middleware `auth:sanctum` en las rutas protegidas verifica el token contra la base de datos.
4. `DELETE /api/auth/logout` → elimina el token, invalidando la sesión inmediatamente.

No se usan cookies ni sesiones para la API; eso hace que sea consumible desde cualquier cliente (SPA, app móvil, Postman).

---

### Throttling diferenciado

Cada endpoint de autenticación tiene su propio límite de peticiones, definido en `RouteServiceProvider`:

```php
// backend/app/Providers/RouteServiceProvider.php
RateLimiter::for('api', fn($r) =>
    Limit::perMinute(60)->by($r->user()?->id ?: $r->ip())
);

RateLimiter::for('auth-login', fn($r) =>
    // 8 intentos/min; clave = email + IP (evita spray attacks)
    Limit::perMinute(8)->by($r->input('login') . '|' . $r->ip())
);

RateLimiter::for('auth-register-request', fn($r) =>
    Limit::perMinutes(15, 4)->by($r->ip())   // 4 registros cada 15 min
);

RateLimiter::for('auth-forgot-password', fn($r) =>
    Limit::perMinutes(30, 3)->by('forgot:' . $r->ip())  // 3 cada 30 min
);
```

Así el login tiene un límite mucho más estricto que el API general, sin que un atacante que bloquee un endpoint afecte a los demás.

---

### Auditoría asíncrona

Cada acción relevante (crear/borrar restaurante, cambiar contraseña, login, etc.) se registra en la tabla `audit_logs`. El registro no ocurre en el hilo principal de la petición — se despacha un **Job** a la cola:

```php
// backend/app/Http/Controllers/Controller.php
protected function auditAction(string $action, ...): void
{
    LogAuditAction::dispatch(
        auth()->id(), $targetUserId, $action,
        $resourceType, $resourceId,
        request()->ip(), request()->userAgent(), $metadata
    );
}
```

```php
// backend/app/Jobs/LogAuditAction.php
class LogAuditAction implements ShouldQueue
{
    public int $tries = 1;

    public function handle(): void
    {
        AuditLog::create([
            'actor_user_id' => $this->actorUserId,
            'action'        => $this->action,
            'ip_address'    => $this->ipAddress,
            'user_agent'    => $this->userAgent,
            // ...
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('LogAuditAction failed', ['error' => $e->getMessage()]);
    }
}
```

Si la cola está configurada como `sync` (desarrollo), el job se ejecuta en el mismo proceso. En producción con `database` o `redis`, es completamente asíncrono.

---

### Mail dinámico desde Settings

Las opciones globales de la aplicación (límites de recursos, etc.) se guardan en la tabla `settings` mediante un modelo de clave-valor:

```php
// backend/app/Models/Setting.php
Setting::get('default_max_restaurants');   // lee de BD
Setting::set('default_max_restaurants', 5); // escribe en BD
```

Solo el `superadmin` puede modificar la configuración desde `/admin/settings`. El `SettingController` valida las claves permitidas con una lista blanca (`ALLOWED_KEYS`) para evitar escrituras arbitrarias.

El envío de correo (verificación de registro, OTP, recuperación de contraseña) se centraliza en `MfaCodeService`, que usa las clases `Mailable` de Laravel y obtiene el `MAIL_FROM` del `.env` / config:

```php
// backend/app/Services/MfaCodeService.php
public function sendToUser(User $user, string $purpose, ...): void
{
    $code = $this->generateCode();
    // guarda $code hasheado en email_mfa_codes con TTL
    Mail::to($user->email)->send(new MfaCodeMail($code, $purpose, $user));
}
```

---

## DEW — Desarrollo Web en Entorno Cliente · 7 min

### Vue 3 Composition API y SFC

Todos los componentes y vistas son **Single File Components** con `<script setup>`, la sintaxis compacta de la Composition API. La lógica, el template y los estilos coexisten en un mismo `.vue` y el compilador de Vite genera módulos JavaScript optimizados.

```vue
<script setup>
import { ref, computed } from 'vue'

const nombre = ref('')                           // estado reactivo
const enMayusculas = computed(() => nombre.value.toUpperCase())  // valor derivado
</script>

<template>
  <input v-model="nombre" />
  <p>{{ enMayusculas }}</p>
</template>
```

---

### Pinia — gestión de estado global

El store `auth` centraliza toda la lógica de autenticación. Es el único estado global del proyecto.

```js
// frontend/src/stores/auth.js
export const useAuthStore = defineStore('auth', () => {
  const user  = ref(null)
  const token = ref(null)

  const isAuthenticated = computed(() => !!token.value)

  async function login(email, password) {
    const data = await api.post('/auth/login', { email, password })
    token.value = data.token
    user.value  = data.user
    setToken(data.token)      // persiste en sessionStorage
  }

  function hasRole(role)      { return user.value?.role === role }
  function hasAnyRole(roles)  { return roles.includes(user.value?.role) }

  return { user, token, isAuthenticated, login, logout, hasRole, hasAnyRole }
})
```

Todas las vistas admin importan este store y lo usan para controlar qué elementos mostrar según el rol.

---

### Composables propios

Los composables encapsulan lógica reutilizable que sería costoso duplicar en cada componente:

| Composable | Qué hace |
|-----------|---------|
| `useToast` | Toast con auto-cierre configurable; maneja el timer internamente |
| `useImageField` | Sube imagen: valida MIME, comprime con `browser-image-compression`, genera previsualización |
| `useLegalMeta` | Fetch de metadatos legales con caché y fallback |

```js
// Cómo se usa useToast en cualquier vista
const { toast, showToast } = useToast()
showToast('Restaurante guardado', 'success')
```

---

### Router Guards — rutas protegidas

El `router.beforeEach` intercepta cada navegación. Si la ruta requiere autenticación y el usuario no está autenticado, redirige a `/login`. Si requiere un rol específico y el usuario no lo tiene, redirige al dashboard:

```js
// frontend/src/router/index.js
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.initFromStorage()          // restaura sesión desde sessionStorage

  if (!to.meta.public && !auth.isAuthenticated)
    return { name: 'Login' }

  if (to.meta.roles && !auth.hasAnyRole(to.meta.roles))
    return { name: 'AdminDashboard' }
})
```

---

### Build estático con Vite

`npm run build` genera un directorio `dist/` con HTML + JS + CSS completamente estático. NGINX sirve este directorio y devuelve siempre `index.html` para cualquier ruta (SPA fallback), dejando a Vue Router gestionar la navegación en cliente:

```nginx
location / {
    root /var/www/frontend;
    try_files $uri /index.html;   # SPA fallback
}
```

---

## DPL — Despliegue de Aplicaciones Web · 5 min

### Docker Compose local

El entorno de desarrollo levanta 5 servicios con un solo comando:

```bash
DB_PASSWORD=postgres DB_PORT=55433 docker compose up -d --build
```

```yaml
# docker-compose.yml (servicios)
services:
  nginx:      # reverse proxy; puertos 8080/8443
  php:        # Laravel FPM; se comunica con postgres
  postgres:   # PostgreSQL 15; volumen persistente
  frontend:   # build de Vite; NGINX sirve el output
  scheduler:  # artisan schedule:run cada 60s (tareas periódicas)
```

Cada servicio tiene su `Dockerfile` en `docker/` con imagen Alpine para minimizar tamaño.

---

### Dockerfile.railway multistage

Para producción se usa una imagen única que incluye frontend, backend, NGINX y supervisor:

```dockerfile
# Dockerfile.railway
# — Fase 1: compilar el frontend
FROM node:20-alpine AS frontend-build
WORKDIR /app
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build           # genera /app/dist

# — Fase 2: imagen final con PHP + NGINX
FROM php:8.4-fpm-alpine
# instala nginx, supervisor, extensiones PHP…
COPY --from=frontend-build /app/dist /var/www/frontend
COPY backend/ /var/www/backend
```

La imagen final es ~120 MB porque no carga Node.js ni el código fuente del frontend — solo el build compilado.

---

### Despliegue en Render/Railway

El archivo `render.yaml` es un **Infrastructure as Code** que describe el servicio en Render:

```yaml
services:
  - type: web
    name: scan2order-lite
    dockerfile: Dockerfile.railway
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: false
    healthCheckPath: /api/hello
    autoDeploy: true          # deploy automático en cada push a main
```

En producción, `APP_DEBUG=false` evita que los stack traces lleguen al cliente, y la queue usa `database` en lugar de `sync` para audit logs asíncronos reales.

---

### GitHub Actions + Smoke Test real

Dos workflows automáticos:

**1. Despliegue de docs** (`.github/workflows/docs-deploy.yml`)  
Cada push a `main` que toca `docs/` compila VitePress y publica en GitHub Pages automáticamente.

**2. Smoke test diario** (`.github/workflows/render-smoke-test.yml`)  
Cada día a las 07:30 UTC (y manualmente) ejecuta `scripts/smoke-render.mjs` contra la URL de producción:

```js
// scripts/smoke-render.mjs — comprueba 3 endpoints críticos
await check('GET /api/hello',  200)           // app responde
await check('GET /api/health', 200, token)    // BD conectada + token válido
await check('POST /api/auth/login', 200)      // autenticación funciona
```

Si alguno falla, GitHub Actions marca el workflow en rojo y notifica por email.

---

## DOR — Diseño de Interfaces Web · 3 min

### Separación visual admin / cliente

La aplicación tiene **dos interfaces completamente distintas** para dos tipos de usuario:

**Vista cliente** (`/restaurant/:id`)  
Interfaz minimalista: solo el logo del restaurante, los catálogos, secciones y productos con sus imágenes y alérgenos. Sin panel de navegación, sin autenticación. Diseñada para visualizarse en el móvil del comensal al escanear el QR.

**Panel admin** (`/admin/*`)  
Dashboard con sidebar fijo, barra de navegación con rol del usuario, tablas de gestión y modales de edición. Solo accesible con token Sanctum válido. El sidebar muestra opciones distintas según si el usuario es `admin` o `superadmin`.

---

### Paleta de colores

La paleta está diseñada para un producto **SaaS de restauración**: profesional, moderna y con contraste suficiente para uso en pantallas de trabajo.

| Rol | Color | Hex | Justificación |
|-----|-------|-----|--------------|
| Primario | Violeta azulado | `#667eea` | Asociado a tecnología y confianza; diferenciador frente a la paleta roja/naranja típica del sector restauración |
| Secundario | Morado | `#764ba2` | Forma gradiente con el primario; da profundidad a botones y cabeceras |
| Éxito | Verde | `#48bb78` | Convención universal para confirmación / activo |
| Error | Rojo suave | `#fc8181` | Menos agresivo que el rojo puro; transmite alerta sin alarma |
| Fondo | Gris muy claro | `#f7fafc` | Reduce fatiga ocular en uso prolongado frente a blanco puro |

El gradiente `linear-gradient(135deg, #667eea, #764ba2)` es consistente en toda la aplicación (barra lateral, botones primarios, ilustraciones), creando identidad visual cohesionada.

### Responsive

La interfaz adapta el número de columnas automáticamente con CSS Grid nativo:

```css
.restaurants-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 1.5rem;
}
```

En móvil (< 380px disponibles) se reduce a una columna; en tablet a dos; en escritorio a tres o más, sin media queries adicionales.

---

## SSG — Sistemas de Gestión · 2 min

### Gestión de usuarios y roles

El superadmin puede crear adminstradores desde `/admin/users`. Cada usuario tiene un rol único (`admin` / `superadmin`) y el rol lleva asociados sus permisos via tabla intermedia. No hay gestión de permisos individuales por usuario, solo por rol.

```
usuario ──belongs_to──▶ role ──has_many──▶ permissions
```

Un admin solo puede gestionar los restaurantes que le han sido asignados (tabla `user_restaurant`). El superadmin accede a todo sin restricción.

---

### Límites por cuenta

Para prevenir abuso de recursos, la configuración en `config/security.php` define topes globales configurables por variables de entorno:

```php
'limits' => [
    'restaurants_per_admin'   => env('LIMIT_RESTAURANTS_PER_ADMIN', 3),
    'catalogs_per_restaurant' => env('LIMIT_CATALOGS_PER_RESTAURANT', 10),
    'sections_per_catalog'    => env('LIMIT_SECTIONS_PER_CATALOG', 20),
    'products_per_section'    => env('LIMIT_PRODUCTS_PER_SECTION', 100),
]
```

El superadmin puede además ajustar los límites globales desde el panel de configuración (`/admin/settings`), que los persiste en la tabla `settings` via `Setting::set()`.

---

### Auditoría

Cada acción sensible queda registrada en `audit_logs` con:
- `actor_user_id` — quién lo hizo
- `action` — qué hizo (`restaurant.created`, `user.deleted`…)
- `resource_type` / `resource_id` — sobre qué recurso
- `ip_address` + `user_agent` — desde dónde

El registro se hace de forma asíncrona (ver sección DSW — Auditoría) para no penalizar el tiempo de respuesta de la operación principal.

---

### Cómo se conectan backend y frontend

Este es el flujo completo de una petición autenticada desde que el usuario hace clic hasta que ve los datos:

```
[Usuario hace clic en "Mis restaurantes"]
        │
        ▼
[Vue Router] — comprueba meta.requiresAuth → OK (token en Pinia store)
        │
        ▼
[Restaurants.vue: onMounted → restaurantService.getAll()]
        │
        ▼
[restaurantService.js] — llama a api.get('/restaurants')
        │
        ▼
[api.js] — añade cabecera Authorization: Bearer <token de sessionStorage>
        │
        ▼  HTTP GET /api/restaurants
[NGINX] — reconoce prefijo /api → reenvía a PHP-FPM (puerto 9000)
        │
        ▼
[Laravel Kernel] → middleware: cors → throttle:api → auth:sanctum
        │         Sanctum busca el token en personal_access_tokens
        ▼
[RestaurantController@index]
   — si admin: devuelve solo sus restaurantes (filtro por user_restaurant)
   — si superadmin: devuelve todos
        │
        ▼  JSON: { data: [...], meta: { current_page, last_page } }
[api.js] — resuelve la promesa
        │
        ▼
[restaurants.value = data.data]  ← reactividad de Vue actualiza el DOM
        │
        ▼
[Usuario ve la lista de restaurantes]
```

En resumen: **Vue Router** controla la navegación, **Pinia** guarda el token, **api.js** inyecta el token en cada petición HTTP, **NGINX** enruta al backend, **Sanctum** valida el token y **el controller** devuelve los datos filtrados por rol.
