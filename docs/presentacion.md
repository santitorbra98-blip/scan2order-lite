# Guía de presentación — Scan2Order Lite

Estructura para 45 min: **30 min de presentación + 15 min de preguntas**.

El reparto de tiempo por módulo sigue proporcionalmente las horas semanales del ciclo.

---

## Parte 1 — Introducción (3 min)

### Problema que resuelve

Las cartas de papel en restaurantes son costosas de actualizar, no están disponibles en varios idiomas y no permiten al local conocer qué productos tienen mayor visibilidad. Con Scan2Order el restaurante gestiona su propia carta digital accesible por QR desde cualquier móvil, sin que el cliente necesite instalar nada.

### Público objetivo

Pequeños y medianos restaurantes que quieren digitalizar su carta sin pagar por plataformas de delivery externas.

### Valor que aporta

- El responsable del local gestiona restaurante, catálogos, secciones y productos desde un panel web.
- El cliente accede a la carta escaneando el QR, sin cuenta.
- No hay dependencia de terceros: el propietario controla sus datos.

---

## Parte 2 — Arquitectura general (4 min)

### Diagrama de componentes

```
Cliente (navegador)
      │
      ▼
   Nginx
   ├── /assets, /  ──► Vue 3 SPA (build estático del frontend)
   └── /api, /sanctum ──► PHP-FPM → Laravel 12 → PostgreSQL
```

### Componentes principales

| Capa | Tecnología | Rol |
|------|-----------|-----|
| Frontend | Vue 3 + Vite + Pinia + Vue Router | SPA servida por Nginx |
| Backend | Laravel 12 + Sanctum | API REST + autenticación |
| Base de datos | PostgreSQL 16 | Persistencia relacional |
| Infraestructura local | Docker Compose | Nginx + PHP-FPM + PostgreSQL |
| Producción | Render + imagen Docker única | Despliegue cloud gratuito |
| CI/CD | GitHub Actions | Publicación de docs + smoke test automático |

### Por qué una imagen Docker única en producción

En el plan gratuito de Render solo hay un servicio web. Empaquetar Nginx, PHP-FPM y el build del frontend en un único contenedor elimina la necesidad de servicios adicionales y reduce el coste a cero.

---

## Parte 3 — Módulos (23 min)

### 🧩 DSW — Desarrollo web en entorno servidor (8 min)

**Tecnologías:** Laravel 12, Sanctum, PostgreSQL, Jobs, Mail, Policies.

#### Modelo de datos

El dominio central es:

```
User ──► Role ──► Permission
  │
  ├── Restaurant
  │     └── Catalog
  │           └── Section
  │                 └── Product
  └── AuditLog / Setting / EmailMfaCode
```

- `users` tiene soft delete, estado (`active/inactive/suspended`), MFA por email y trazabilidad legal (IP, user-agent, versión de términos aceptada).
- RBAC propio: tabla `roles` + `permissions` + pivot `role_permission`. Los roles globales tienen visibilidad sobre todos los restaurantes.

#### API REST

Más de 40 endpoints agrupados en:
- Públicos: `GET /api/restaurants`, catálogos, menú del cliente.
- Autenticación: login, registro con verificación por email, recuperación de contraseña en dos pasos.
- Protegidos (Sanctum): gestión de restaurantes, catálogos, productos, usuarios y perfil.

Demostración en vivo: `curl https://<host>/api/hello` → 200.

#### Seguridad en backend

- Middleware `SecurityHeaders`: Content-Security-Policy, HSTS, X-Frame-Options DENY, Permissions-Policy, server_tokens off.
- Throttling diferenciado por ruta: `auth-login`, `auth-register-request`, `auth-forgot-password`, etc.
- `/api/health` protegido en producción con token `X-Health-Token`.
- Subida de imágenes: extensión inferida por MIME (`guessExtension`) para evitar RCE por políglotas. Nginx bloquea ejecución de PHP en `/storage`.
- `verifyPasswordResetCode` devuelve mensaje genérico si el email no existe (evita enumeración de usuarios).
- Tokens Sanctum con expiración configurable (por defecto 7 días). Token almacenado en `sessionStorage`, no `localStorage`.

#### Jobs y Mail

- `LogAuditAction`: job síncrono que registra acciones sensibles en `audit_logs`.
- `MfaCodeMail` y `WelcomeMail`: correos transaccionales con configuración dinámica desde tabla `settings`.

#### Decisiones técnicas justificables

- `CACHE_STORE=file`: evita dependencia de tabla cache en entornos sin Redis.
- `QUEUE_CONNECTION=sync`: sin worker adicional en plan gratuito; los jobs corren en el mismo proceso.

---

### 🌐 DEW — Desarrollo web en entorno cliente (7 min)

**Tecnologías:** Vue 3 Composition API, Vite, Pinia, Vue Router.

#### Estructura del frontend

```
src/
├── views/
│   ├── admin/       # Dashboard, Restaurants, Products, Catalogs,
│   │                # Users, Profile, Settings, Onboarding
│   ├── client/      # Menu (carta pública sin autenticación)
│   └── legal/       # Términos y privacidad
├── stores/          # auth.js (Pinia)
├── composables/     # useImageField, useLegalMeta, useToast
├── services/        # Llamadas a la API (fetch + interceptores)
├── router/          # Vue Router con guards de autenticación
└── components/      # Componentes reutilizables
```

#### Flujos clave

1. **Registro**: formulario → verificación por email → acceso al panel.
2. **Gestión de carta**: admin crea restaurante → catálogo → secciones → productos con imagen.
3. **Vista pública**: cliente escanea QR → `Menu.vue` carga la carta sin autenticación → UX limpia solo de lectura.

#### Composables propios

- `useImageField`: previsualización de imagen antes de subir, con validación de tipo y tamaño en cliente.
- `useLegalMeta`: carga versión de términos/privacidad desde la API para el formulario de registro.
- `useToast`: notificaciones globales sin librería externa.

#### Reactibidad y estado

- Pinia gestiona el estado de autenticación (usuario logado, token, permisos).
- Vue Router guard `beforeEach` redirige a `/login` si no hay token válido, o a `/admin/dashboard` si ya está autenticado.

#### Build optimizado

Vite compila el frontend a estáticos (`dist/`) que Nginx sirve directamente. En producción no hay Node.js en ejecución.

---

### 🚀 DPL — Despliegue de aplicaciones web (5 min)

**Tecnologías:** Docker, Docker Compose, Render, GitHub Actions.

#### Local con Docker Compose

```bash
DB_PASSWORD=postgres docker compose up -d --build
docker compose exec php php artisan migrate --seed --force
```

Tres servicios: `nginx`, `php` (PHP-FPM + código Laravel), `postgres`.

#### Producción con Render

`render.yaml` declara:
- Web service con `Dockerfile.railway` (imagen única).
- PostgreSQL managed de Render.
- Variables de entorno como secretos (`APP_KEY`, `HEALTH_CHECK_TOKEN`, etc.).

El `Dockerfile.railway` fusiona Nginx + PHP-FPM + build de Vue en una sola imagen:
1. Stage Node: compila el frontend.
2. Stage PHP: `composer install --no-dev`.
3. Stage final: Nginx + PHP-FPM + estáticos del frontend listos.

#### CI/CD con GitHub Actions

| Workflow | Cuándo se ejecuta | Qué hace |
|---------|-------------------|---------|
| `docs-deploy.yml` | Push a `main` | Publica VitePress en GitHub Pages |
| `render-smoke-test.yml` | Push a `main` | Llama a `/api/hello`, `/api/health` y `POST /api/login` en producción; falla el workflow si algo no responde |

El smoke test detecta despliegues rotos antes de que el usuario los encuentre.

#### Problemas reales resueltos durante el despliegue

- `npm ci` fallaba porque no existía `package-lock.json` en el frontend → cambiado a `npm install`.
- Laravel 12 usa `CACHE_STORE`, no `CACHE_DRIVER`; la clave antigua causaba que el health check respondiera 500.
- En Render, si `APP_KEY` no está configurado, Laravel devuelve 500 en todas las rutas → documentado en checklist con paso explícito de generación de clave.

---

### 🎨 DOR — Diseño de interfaces web (3 min)

**Tecnologías:** Vue 3, CSS propio, diseño responsive.

#### Principios aplicados

- **Separación de roles visuales**: el panel de administración y la vista pública del cliente son mundos distintos. La carta del cliente es minimalista (sin barra de navegación, sin botones de acción).
- **Onboarding guiado**: cuando un admin entra por primera vez sin restaurante, `Onboarding.vue` muestra un asistente paso a paso en lugar de un panel vacío.
- **Feedback inmediato**: el composable `useToast` muestra mensajes de éxito/error sin bloquear la UI.
- **Previsualización de imágenes**: antes de subir una foto de producto, el usuario la ve renderizada en la misma tarjeta del formulario.

#### Accesibilidad básica

- Contraste de colores revisado.
- Etiquetas `<label>` asociadas a todos los inputs.
- Navegación por teclado funcional en formularios.

---

### 💼 IPW — Itinerario personal para la empleabilidad (2 min)

#### Prácticas profesionales aplicadas

- **Control de versiones**: todo el desarrollo en Git con mensajes de commit descriptivos.
- **Documentación técnica**: docs completos en VitePress con guía rápida, arquitectura, checklist de producción, smoke tests y esta misma guía de defensa. Publicados automáticamente en GitHub Pages.
- **Checklist de producción**: lista de verificación antes de cada despliegue real, que incluye generación de `APP_KEY`, variables de entorno, datos de prueba y validación post-deploy.
- **README operativo**: cualquier persona con el repositorio puede levantar el proyecto con tres comandos.

#### Relevancia para el mercado laboral

El conjunto {Laravel + Vue + Docker + GitHub Actions} es la pila estándar en muchas empresas de desarrollo web en España. Haber pasado por las fricciones reales del despliegue (secrets, variables de entorno, health checks, CI/CD) diferencia este proyecto de un CRUD local.

---

### 🎨 SSG — Sistemas de gestión empresarial (2 min)

**Funcionalidades de tipo ERP/backoffice:**

- **Gestión de usuarios**: el superadmin crea y gestiona cuentas de administradores de restaurante, puede activar/suspender cuentas.
- **Límites por usuario**: cada usuario tiene `max_restaurants` y `max_catalogs` configurables, controlados por el superadmin.
- **Roles y permisos**: RBAC con roles globales (`superadmin`) y roles de restaurante. Los permisos se asignan a nivel de rol.
- **Auditoría**: `AuditLog` registra acciones sensibles (login, cambio de contraseña, borrado de datos) con IP y timestamp.
- **Configuración dinámica**: tabla `settings` permite ajustar parámetros como el servidor SMTP sin redeploy.
- **Setup inicial**: endpoint `/api/setup/create-superadmin` crea el primer superadmin y se auto-desactiva cuando ya existen dos.

---

### 🌱 SOJ — Sostenibilidad aplicada al sistema productivo (2 min)

#### Eficiencia de recursos

- **Imagen Docker única**: en lugar de tres contenedores en producción, todo corre en uno. Menos memoria, menos CPU, menos coste energético.
- **Plan gratuito viable**: el diseño intencional de no depender de Redis, workers de colas, ni servicios adicionales hace que la aplicación funcione en el tier gratuito de Render. Menor consumo de infraestructura = menor huella energética.
- **Sin servidor Node.js en producción**: Vite solo se usa en build time. En runtime, Nginx sirve estáticos. No hay proceso Node corriendo consumiendo recursos.
- **`QUEUE_CONNECTION=sync`**: los jobs se ejecutan en el mismo proceso sin un worker adicional permanente.

#### Escalabilidad responsable

La arquitectura actual está pensada para MVP. Si la demanda crece, la migración natural es: separar frontend/backend, añadir Redis para caché y colas, y escalar solo el componente que lo necesite (no todo el sistema).

---

## Parte 4 — Demo en vivo (recomendada, dentro de los 30 min)

Integrar durante los módulos DSW y DPL:

```bash
# 1. Levantar local
DB_PASSWORD=postgres docker compose up -d

# 2. Health check
curl -k https://localhost:8443/api/hello

# 3. Login
curl -k -X POST https://localhost:8443/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# 4. Ver carta pública
# Abrir en navegador: https://localhost:8443/menu/{id}
```

O bien mostrar el panel de administración en el navegador: crear un producto, ver cómo aparece en la carta pública.

---

## Preguntas previsibles y respuestas cortas

| Pregunta | Respuesta |
|---------|-----------|
| ¿Por qué Laravel y no Express/NestJS? | Laravel tiene ORM, migraciones, autenticación, jobs y mail integrados. Para un equipo pequeño reduce el tiempo de desarrollo y la superficie de errores. |
| ¿Por qué Vue y no React? | API de composición de Vue 3 es más cercana a las opciones del ciclo y tiene menor curva de entrada. Pinia es más simple que Redux. |
| ¿Por qué Render y no Heroku o un VPS? | Render tiene plan gratuito con PostgreSQL managed y despliegue desde Git. Heroku eliminó su plan gratuito. Un VPS requiere administración de SO que no aporta valor al proyecto. |
| ¿Por qué Sanctum y no JWT? | Sanctum es el estándar oficial de Laravel para SPA. Los tokens viven en base de datos y se pueden revocar; con JWT puro no hay revocación sin lista negra. |
| ¿Qué pasaría si dos restaurantes editan el mismo catálogo a la vez? | En este MVP no hay bloqueo optimista. La mejora natural sería añadir `updated_at` como ETag y devolver 409 si el cliente envía una versión antigua. |
| ¿Cómo se haría para que el cliente pudiera hacer un pedido? | Añadir modelos `Order` y `OrderItem`, estado de pedido (`pending/preparing/ready`), websocket o polling para actualización en tiempo real, y un panel de cocina. |
| ¿Qué es el smoke test? | Un script que tras cada push a `main` hace peticiones reales a producción (`/api/hello`, `/api/health`, login) y falla el pipeline si algo no responde. Detecta deploys rotos antes de que lleguen al usuario. |
| ¿Por qué el token en `sessionStorage` y no `localStorage`? | `localStorage` persiste entre pestañas y sesiones; si el navegador tiene una extensión maliciosa puede leerlo. `sessionStorage` se borra al cerrar la pestaña y reduce la ventana de exposición. |
| ¿Cómo se gestionan los secretos? | `.env` no está en el repositorio. En producción las variables se configuran como secretos en el panel de Render, no en el código. `APP_KEY` se genera una sola vez con `artisan key:generate`. |
| ¿Qué mejorarías si tuvieras más tiempo? | Tests unitarios de los controllers (Feature tests de Laravel), internacionalización (i18n) para la carta del cliente, y un generador de QR integrado en el panel. |

---

## Distribución del tiempo orientativa

| Módulo | Horas/semana | % | Tiempo en presentación |
|--------|-------------|---|----------------------|
| DSW | 8 | 26 % | ~8 min |
| DEW | 7 | 23 % | ~7 min |
| DPL | 5 | 16 % | ~5 min |
| DOR | 3 | 10 % | ~3 min |
| IPW | 3 | 10 % | ~2 min |
| SSG | 3 | 10 % | ~2 min |
| SOJ | 2 | 6 % | ~2 min |
| Intro + Arquitectura | — | — | ~7 min (partes 1 y 2) |
| **Total** | | | **~36 min** (con demo integrada) |

Ajusta según lo que domines mejor: si el despliegue es un punto fuerte, dale más tiempo a DPL. Si la demo de la interfaz es sólida, amplía DOR/DEW.
