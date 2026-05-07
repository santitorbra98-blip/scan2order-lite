# Defensa del Proyecto

Guía de argumentación técnica por asignatura. Cada sección corresponde al tiempo asignado en la defensa.

---

## DSW — Desarrollo Web en Entorno Servidor · 8 min

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
