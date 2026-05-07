# DEW — Desarrollo Web en Entorno Cliente

> Módulo: **Desarrollo web en entorno cliente**  
> Stack: Vue 3 · Composition API · SFC · Vue Router · Pinia

---

## 1. Vue 3 + SFC + Composition API

Todos los componentes y vistas usan **Single File Components (SFC)** con `<script setup>`, la sintaxis azucarada de la Composition API de Vue 3. Esto encapsula template, lógica y estilos en un solo archivo `.vue`.

```vue
<!-- Ejemplo de SFC con <script setup> -->
<script setup>
import { ref, computed } from 'vue'
const count = ref(0)
const double = computed(() => count.value * 2)
</script>
<template>
  <p>{{ double }}</p>
</template>
```

**Ejemplos reales en el proyecto**

| Archivo | Descripción |
|---------|------------|
| [`frontend/src/views/admin/Restaurants.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/views/admin/Restaurants.vue) | Vista principal de gestión de restaurantes |
| [`frontend/src/views/admin/Products.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/views/admin/Products.vue) | Vista de catálogos y productos (jerarquía 3 niveles) |
| [`frontend/src/components/ProductModal.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/components/ProductModal.vue) | Modal de creación/edición de producto |
| [`frontend/src/components/CatalogModal.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/components/CatalogModal.vue) | Modal de catálogo con Composition API |

---

## 2. TypeScript en componentes

El componente `StatsCard.vue` usa `<script setup lang="ts">` con genéricos de `defineProps`, que es la sintaxis oficial de TS en Vue 3:

```ts
// frontend/src/components/StatsCard.vue — línea 10
<script setup lang="ts">
defineProps<{
  icon: string
}>()
</script>
```

**Referencia**: [`frontend/src/components/StatsCard.vue#L10`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/components/StatsCard.vue#L10)

> ⚠️ **Cobertura parcial** — el resto del proyecto usa JavaScript. Ver sección [Qué falta](#qué-falta).

---

## 3. Vue Router — Página no continua (Lazy Loading)

El router aplica **code splitting dinámico**: cada vista admin se carga bajo demanda con `() => import(...)`. Esto genera chunks separados en el build y únicamente descarga el código cuando el usuario navega a esa ruta.

```js
// frontend/src/router/index.js — línea 11
const AdminDashboard = () => import('../views/admin/Dashboard.vue')
const AdminRestaurants = () => import('../views/admin/Restaurants.vue')
const AdminProducts = () => import('../views/admin/Products.vue')
```

**Referencia**: [`frontend/src/router/index.js`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/router/index.js)

### Rutas definidas

| Ruta | Componente | Acceso |
|------|-----------|--------|
| `/` | `Home.vue` | público |
| `/login` | `Login.vue` | público |
| `/register` | `Register.vue` | público |
| `/restaurant/:id` | `ClientMenu.vue` | público (carta QR) |
| `/admin` | `Dashboard.vue` | `requiresAuth` |
| `/admin/restaurants` | `Restaurants.vue` | admin / superadmin |
| `/admin/products` | `Products.vue` | admin / superadmin |
| `/admin/users` | `Users.vue` | solo superadmin |
| `/admin/settings` | `Settings.vue` | solo superadmin |
| `/admin/profile` | `Profile.vue` | `requiresAuth` |
| `/legal/*` | páginas legales | público |
| `/:pathMatch(.*)* ` | `NotFound.vue` | público (404) |

### Navigation Guards

El guard global en el router comprueba `meta.requiresAuth` y `meta.roles` antes de cada navegación, usando el store de Pinia para leer el rol del usuario autenticado.

```js
// frontend/src/router/index.js
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!to.meta.public && !auth.isAuthenticated) return '/login'
  if (to.meta.roles && !auth.hasAnyRole(to.meta.roles)) return '/admin'
})
```

---

## 4. Reactividad: `ref`, `reactive`, `computed`

### `ref()`

Crea una referencia reactiva a un valor primitivo o complejo. Al acceder dentro del template no se necesita `.value`.

```js
// frontend/src/composables/useToast.js — línea 10
const toast = ref({ show: false, type: 'success', message: '' })
```

```js
// frontend/src/views/admin/Restaurants.vue
const restaurants = ref([])
const loading = ref(false)
const showFormModal = ref(false)
```

**Referencias**: [`useToast.js`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/composables/useToast.js#L10) · [`Restaurants.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/views/admin/Restaurants.vue)

### `computed()`

Valor derivado que se recalcula automáticamente sólo cuando sus dependencias cambian:

```js
// frontend/src/views/admin/Restaurants.vue
const isSuperAdmin = computed(() => authStore.hasRole('superadmin'))
const ownRestaurants = computed(() =>
  restaurants.value.filter(r => r.owner_id === authStore.user?.id)
)
```

```js
// frontend/src/views/admin/Dashboard.vue
const isSuperadmin = computed(() => auth.hasRole('superadmin'))
```

**Referencias**: [`Restaurants.vue#L152`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/views/admin/Restaurants.vue#L152) · [`Dashboard.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/views/admin/Dashboard.vue)

### `reactive()`

> ⚠️ **No utilizado actualmente** — ver sección [Qué falta](#qué-falta).

---

## 5. `localStorage` / `sessionStorage`

El token de autenticación se almacena en **`sessionStorage`** (pestaña actual, se borra al cerrar). Hay lógica de migración para usuarios que tuviesen sesiones antiguas en `localStorage`.

```js
// frontend/src/services/api.js — línea 7
let _authToken = sessionStorage.getItem(TOKEN_KEY)
               || localStorage.getItem(TOKEN_KEY)  // migración de sesiones viejas
               || null

// Si venía de localStorage, migrar y borrar
if (_authToken && !sessionStorage.getItem(TOKEN_KEY)) {
  sessionStorage.setItem(TOKEN_KEY, _authToken)
  localStorage.removeItem(TOKEN_KEY)           // borrar rastro
}
```

**Referencia**: [`frontend/src/services/api.js`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/services/api.js)

---

## 6. Props / Emits

Comunicación entre componentes padre e hijo mediante `defineProps` (datos hacia abajo) y `defineEmits` (eventos hacia arriba), siguiendo el flujo unidireccional de Vue.

### Ejemplo — `ProductModal.vue`

```js
// frontend/src/components/ProductModal.vue
const props = defineProps({
  modelValue: Boolean,          // controla visibilidad (v-model)
  editing:    { type: Object, default: null }, // null → crear, objeto → editar
  allergenOptions: { type: Array, default: () => [] },
  dietOptions:     { type: Array, default: () => [] },
  saving: Boolean,
  error:  { type: String, default: null },
})
const emit = defineEmits(['close', 'save'])

// Al guardar, emite el payload hacia el padre
emit('save', { form: { ...form }, imageFile: imageFile.value })
```

**Referencia**: [`ProductModal.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/components/ProductModal.vue)

### Ejemplo — `RestaurantDeleteModal.vue`

```js
// frontend/src/components/RestaurantDeleteModal.vue
defineProps({
  modelValue:     Boolean,
  restaurantName: { type: String, default: '' },
  deleting:       Boolean,
})
defineEmits(['close', 'confirm'])
```

**Referencia**: [`RestaurantDeleteModal.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/components/RestaurantDeleteModal.vue)

---

## 7. Pinia

El store `auth.js` gestiona toda la autenticación: token, usuario activo, roles y ciclo de vida de la sesión. Se consume en **todas** las vistas admin.

```js
// frontend/src/stores/auth.js (resumen)
export const useAuthStore = defineStore('auth', () => {
  const user  = ref(null)
  const token = ref(null)

  const isAuthenticated = computed(() => !!token.value)

  async function login(email, password) { /* POST /api/auth/login */ }
  async function logout()               { /* DELETE /api/auth/logout */ }
  function hasRole(role)                { return user.value?.role === role }
  function hasAnyRole(roles)            { return roles.includes(user.value?.role) }

  return { user, token, isAuthenticated, login, logout, hasRole, hasAnyRole }
})
```

**Referencia**: [`frontend/src/stores/auth.js`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/stores/auth.js)

Uso en vistas:

```js
// frontend/src/views/admin/Restaurants.vue
import { useAuthStore } from '../../stores/auth'
const authStore = useAuthStore()
const isSuperAdmin = computed(() => authStore.hasRole('superadmin'))
```

---

## 8. Slots

Los **slots** permiten que un componente padre inyecte contenido en el template del hijo.

```vue
<!-- frontend/src/components/StatsCard.vue — slot sin nombre -->
<template>
  <div class="stat-card">
    <div class="stat-icon">{{ icon }}</div>
    <div class="stat-info">
      <slot />        <!-- el padre pone aquí título y valor -->
    </div>
  </div>
</template>
```

Uso desde `Dashboard.vue`:

```vue
<StatsCard icon="🍽️">
  <p class="stat-label">Restaurantes</p>
  <p class="stat-value">{{ stats.total_restaurants }}</p>
</StatsCard>
```

**Referencia**: [`StatsCard.vue#L5`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/components/StatsCard.vue#L5)

---

## 9. Composables

Los composables encapsulan lógica reutilizable basada en la Composition API. Equivalen a los hooks de React.

| Composable | Función |
|-----------|---------|
| [`useToast.js`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/composables/useToast.js) | Notificaciones temporales con auto-cierre configurable |
| [`useImageField.js`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/composables/useImageField.js) | Gestión de carga de imagen: validación MIME, compresión (`browser-image-compression`), previsualización |
| [`useLegalMeta.js`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/composables/useLegalMeta.js) | Recupera metadatos legales de `/api/legal/meta`, los cachea y usa valores por defecto si el endpoint falla |

```js
// Uso de useToast en cualquier vista
import { useToast } from '../../composables/useToast'
const { toast, showToast } = useToast()
showToast('Guardado correctamente', 'success')
```

```js
// Uso de useImageField en modal de producto
import { useImageField } from '../../composables/useImageField'
const { imageFile, imagePreview, handleFileChange, resetImage } = useImageField()
```

---

## ❌ Qué falta

| Requisito | Estado | Solución sugerida |
|-----------|--------|-------------------|
| **TypeScript completo** | Solo `StatsCard.vue` | Convertir composables y el store a `.ts`; añadir `lang="ts"` al resto de vistas |
| **`reactive()`** | No aparece en el código | Reemplazar el objeto de formulario en un modal con `reactive({ name:'', price:0 })` |
| **Más slots** | Un único `<slot />` | Añadir slot nombrado `#actions` en cards o layout, o slot `#header` en modales |

Implementar estas tres mejoras es lo más prioritario para cubrir completamente la rúbrica DEW.
