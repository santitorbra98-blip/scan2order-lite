# DSW — Desarrollo Web en Entorno Servidor

> Módulo: **Desarrollo web en entorno servidor**  
> Stack: PHP 8.4 · Laravel 11 · NGINX · PostgreSQL 15 · Laravel Sanctum

---

## 1. NGINX como servidor de aplicación

NGINX actúa como **reverse proxy** y servidor de ficheros estáticos. Todas las peticiones entran por NGINX, que las enruta:

- `/api/*` y `/sanctum/*` → PHP-FPM (Laravel)
- `/storage/*` → ficheros subidos (sin ejecución PHP)
- cualquier otra ruta → SPA Vue (siempre devuelve `index.html`)

```nginx
# docker/nginx/default.conf
server {
    listen 443 ssl http2;

    # Assets del frontend con cache de 1 año
    location ~* \.(js|css|woff2?|png|jpg|svg)$ {
        root /var/www/frontend;
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Imágenes subidas (uploads)
    location /storage/ {
        alias /var/www/backend/public/storage/;
        expires 7d;
    }

    # API → PHP-FPM
    location ~ ^/(api|sanctum)/ {
        root /var/www/backend/public;
        fastcgi_pass php:9000;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
    }

    # SPA fallback
    location / {
        root /var/www/frontend;
        try_files $uri /index.html;
    }
}
```

**Referencia**: [`docker/nginx/default.conf`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docker/nginx/default.conf)

### Cabeceras de seguridad

NGINX añade cabeceras de seguridad en todas las respuestas:

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Frame-Options "DENY" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
add_header Content-Security-Policy "default-src 'self'; ..." always;
```

---

## 2. PHP 8.4 como backend

El backend usa **PHP 8.4-FPM** (FastCGI Process Manager), que gestiona un pool de procesos PHP y atiende las peticiones enviadas por NGINX.

```dockerfile
# docker/php/Dockerfile
FROM php:8.4-fpm-alpine
RUN docker-php-ext-install pdo_pgsql bcmath opcache
```

**Referencia**: [`docker/php/Dockerfile`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/docker/php/Dockerfile)

---

## 3. Laravel 11

Laravel es el framework MVC de PHP que estructura todo el backend.

### Estructura del backend

```
backend/
  app/
    Http/
      Controllers/    ← lógica de cada endpoint
      Requests/       ← validación de entrada (FormRequests)
      Middleware/     ← Guards: Sanctum, roles, rate-limiting
    Models/           ← Eloquent ORM (Restaurant, Catalog, Product…)
    Policies/         ← Autorización basada en Policy
    Jobs/             ← Tareas asíncronas (LogAuditAction)
    Services/         ← Lógica de negocio reutilizable
  routes/
    api.php           ← definición de rutas de la API
  database/
    migrations/       ← esquema de base de datos versionado
    seeders/          ← datos de prueba
```

**Referencia**: [`backend/`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend)

### Eloquent ORM

Los modelos extienden `Illuminate\Database\Eloquent\Model` y definen relaciones:

```php
// backend/app/Models/Catalog.php
class Catalog extends Model {
    public function restaurant(): BelongsTo {
        return $this->belongsTo(Restaurant::class);
    }
    public function sections(): HasMany {
        return $this->hasMany(Section::class)->orderBy('order');
    }
}
```

**Referencia**: [`backend/app/Models/`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/app/Models)

### FormRequests — validación centralizada

Cada endpoint de escritura tiene su `FormRequest` con las reglas de validación:

```php
// backend/app/Http/Requests/StoreProductRequest.php
class StoreProductRequest extends FormRequest {
    public function rules(): array {
        return [
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'allergens'   => 'nullable|array',
            'allergens.*' => 'string|in:gluten,lacteos,huevos,...',
            'diet_tags'   => 'nullable|array',
        ];
    }

    // Normaliza JSON strings enviados como multipart/form-data
    protected function prepareForValidation(): void {
        if (is_string($this->allergens)) {
            $this->merge(['allergens' => json_decode($this->allergens, true) ?? []]);
        }
    }
}
```

**Referencias FormRequests**: [`backend/app/Http/Requests/`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/app/Http/Requests)

### Policy — Autorización

El acceso a los recursos de cada restaurante se controla mediante una **Policy** de Laravel, registrada en `AppServiceProvider`:

```php
// backend/app/Policies/RestaurantPolicy.php
class RestaurantPolicy {
    public function manage(User $user, Restaurant $restaurant): bool {
        if ($user->isSuperAdmin()) return true;
        return $user->hasPermission('manage_products')
            && $user->canAccessRestaurant($restaurant->id);
    }
}
```

```php
// backend/app/Providers/AppServiceProvider.php
Gate::policy(Restaurant::class, RestaurantPolicy::class);
```

**Referencia**: [`RestaurantPolicy.php`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/app/Policies/RestaurantPolicy.php) · [`AppServiceProvider.php`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/app/Providers/AppServiceProvider.php)

### Jobs — tareas en cola

El registro de auditoría se hace **de forma asíncrona** mediante un Job de Laravel:

```php
// backend/app/Jobs/LogAuditAction.php
class LogAuditAction implements ShouldQueue {
    public int $tries = 1;

    public function handle(): void {
        AuditLog::create([
            'actor_user_id'  => $this->actorUserId,
            'action'         => $this->action,
            'resource_type'  => $this->resourceType,
            'ip_address'     => $this->ipAddress,
        ]);
    }

    public function failed(\Throwable $e): void {
        Log::warning('LogAuditAction failed', ['error' => $e->getMessage()]);
    }
}
```

**Referencia**: [`backend/app/Jobs/LogAuditAction.php`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/app/Jobs/LogAuditAction.php)

---

## 4. API REST con Laravel

La API sigue el estilo REST con recursos anidados. El prefijo global es `/api`.

### Autenticación

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/auth/register` | Registro con verificación por email |
| `POST` | `/api/auth/login` | Login → devuelve Bearer token Sanctum |
| `POST` | `/api/auth/verify-register` | Verificación OTP del registro |
| `DELETE` | `/api/auth/logout` | Invalida el token actual |
| `GET` | `/api/auth/me` | Usuario autenticado |

**Referencia**: [`docs/api-auth.md`](./api-auth)

### Recursos REST — Catálogos y Productos

```
GET    /api/restaurants/{id}/catalogs          → lista los catálogos del restaurante
POST   /api/restaurants/{id}/catalogs          → crea catálogo
PUT    /api/restaurants/{id}/catalogs/{cid}    → actualiza catálogo
DELETE /api/restaurants/{id}/catalogs/{cid}    → elimina catálogo

POST   /api/restaurants/{id}/catalogs/{cid}/sections              → crea sección
PUT    /api/restaurants/{id}/catalogs/{cid}/sections/{sid}        → actualiza sección
DELETE /api/restaurants/{id}/catalogs/{cid}/sections/{sid}        → elimina sección

POST   /api/restaurants/{id}/catalogs/{cid}/sections/{sid}/products          → crea producto
PUT    /api/restaurants/{id}/catalogs/{cid}/sections/{sid}/products/{pid}    → actualiza
DELETE /api/restaurants/{id}/catalogs/{cid}/sections/{sid}/products/{pid}    → elimina
```

**Rutas completas**: [`backend/routes/api.php`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/routes/api.php)

### Controllers responsables

| Controller | Responsabilidad |
|-----------|----------------|
| [`AuthController`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/app/Http/Controllers/AuthController.php) | Login, registro, verificación, logout |
| [`CatalogController`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/app/Http/Controllers/CatalogController.php) | CRUD catálogos, secciones, stats, PDF |
| [`ProductController`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/app/Http/Controllers/ProductController.php) | CRUD productos (con imagen) |
| [`RestaurantController`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/app/Http/Controllers/RestaurantController.php) | CRUD restaurantes |
| [`UserController`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/backend/app/Http/Controllers/UserController.php) | Gestión de usuarios (superadmin) |

### Respuesta de error estándar

Laravel devuelve JSON para todos los errores gracias al `Handler.php`:

```json
// 422 Unprocessable Entity (validación)
{
  "message": "El campo nombre es obligatorio.",
  "errors": {
    "name": ["El campo nombre es obligatorio."]
  }
}

// 403 Forbidden (Policy)
{ "message": "This action is unauthorized." }
```

### Autenticación Sanctum (Bearer Token)

```js
// frontend/src/services/api.js
headers: {
  'Authorization': `Bearer ${getToken()}`,
  'Accept': 'application/json',
}
```

**Referencia**: [`frontend/src/services/api.js`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/services/api.js)

---

## Resumen de cumplimiento DSW

| Criterio | Estado | Referencia |
|----------|--------|------------|
| NGINX como servidor de aplicación | ✅ | `docker/nginx/default.conf` |
| PHP como backend | ✅ | `docker/php/Dockerfile` |
| Laravel | ✅ | `backend/` |
| Implementación API REST | ✅ | `backend/routes/api.php` |
