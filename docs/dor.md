# DOR — Diseño de Interfaces Web

> Módulo: **Diseño de interfaces web**  
> Stack: Vue 3 · CSS Custom Properties · Flexbox · CSS Grid · Responsive Design

---

## 1. Aplicación Responsive

La interfaz se adapta a cualquier tamaño de pantalla usando **CSS Grid con `auto-fill`/`minmax`** y **Flexbox con `flex-wrap`**. No se usa un framework CSS externo; el diseño adaptativo es 100% CSS nativo.

### Grid adaptable — lista de restaurantes

```css
/* frontend/src/views/admin/Restaurants.vue */
.restaurants-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 1.5rem;
}
```

Con `auto-fill` + `minmax(380px, 1fr)` el número de columnas se calcula automáticamente: en pantallas grandes muestra 3-4 columnas, en tablet 2, en móvil 1.

**Referencia**: [`Restaurants.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/views/admin/Restaurants.vue) — bloque `.restaurants-grid`

### Cabeceras con Flexbox adaptable

```css
/* Cabecera que pasa de row a columna en pantallas pequeñas */
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;      /* en móvil los items se apilan */
  gap: 1rem;
}
```

**Referencia**: [`Restaurants.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/views/admin/Restaurants.vue) — bloque `.header`

### Formularios en rejilla

```css
/* frontend/src/components/ProductModal.vue */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 600px) {
  .form-grid { grid-template-columns: 1fr; }
}
```

**Referencia**: [`ProductModal.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/components/ProductModal.vue)

### Modales responsivos

Los modales usan `max-width` con `width: 90vw` para ajustarse a pantallas pequeñas:

```css
.modal-box {
  width: 90vw;
  max-width: 560px;
  border-radius: 16px;
  overflow-y: auto;
  max-height: 90vh;   /* evita desbordar en móviles */
}
```

---

## 2. Criterios WCAG de Accesibilidad

### Qué está implementado

**Textos alternativos (`alt`)** en todas las imágenes:

```html
<!-- frontend/src/components/ProductModal.vue -->
<img :src="imagePreview" alt="Vista previa" />

<!-- frontend/src/components/RestaurantFormModal.vue -->
<img :src="imagePreview" alt="Vista previa" />

<!-- frontend/src/components/RestaurantCardContent.vue -->
<img :src="restaurant.logo_url" :alt="restaurant.name" />
```

**Etiquetas `<label>` asociadas a campos** en todos los formularios:

```html
<label for="name">Nombre del restaurante</label>
<input id="name" v-model="form.name" type="text" />
```

**Contraste de color** — la paleta usa texto oscuro (`#1a202c`) sobre fondos blancos/grisáceos, siguiendo la ratio mínima AA (4,5:1).

**Feedback de errores visible** — los errores de validación se muestran bajo cada campo con color rojo y texto descriptivo, no solo con cambio de color.

### ❌ Qué falta (WCAG)

> Ver sección [Qué falta](#qué-falta).

---

## 3. Framework CSS

> ⚠️ **No se usa un framework CSS externo** (ni Tailwind, ni Bootstrap).

El proyecto usa **CSS nativo con `<style scoped>`** en cada componente Vue. Esto garantiza:
- Aislamiento de estilos (no hay colisiones de clases entre componentes)
- CSS custom properties para colores del tema

```css
/* Ejemplo de variables de tema en la aplicación */
:root {
  --color-primary:    #667eea;
  --color-secondary:  #764ba2;
  --color-success:    #48bb78;
  --color-error:      #fc8181;
  --color-text:       #1a202c;
  --color-bg:         #f7fafc;
}
```

> Ver sección [Qué falta](#qué-falta) para la posible incorporación de Tailwind CSS.

---

## 4. Gama de Colores

La paleta está orientada a un producto **SaaS gastronómico**: transmite modernidad y confianza.

| Rol | Color | Uso |
|-----|-------|-----|
| Primario | `#667eea` (violeta azulado) | Botones principales, acentos |
| Secundario | `#764ba2` (morado) | Gradientes, hover |
| Éxito | `#48bb78` (verde) | Confirmaciones, activo |
| Error | `#fc8181` (rojo suave) | Errores, eliminación |
| Advertencia | `#f6ad55` (naranja) | Estados intermedios |
| Texto | `#1a202c` (casi negro) | Cuerpo de texto |
| Fondo general | `#f7fafc` (gris muy claro) | Fondo de paneles |
| Fondo blanco | `#ffffff` | Cards, modales |

El gradiente primario `linear-gradient(135deg, #667eea, #764ba2)` es consistente en toda la administración (barra lateral, buttons hero, ilustraciones).

---

## 5. Criterios de Usabilidad

### Feedback inmediato al usuario

- **Toast notifications** (useToast composable): confirmación de cada acción en < 2,5 s.
- **Estados de carga** (`loading`, `saving` refs): los botones muestran "Guardando…" y se deshabilitan durante peticiones.
- **Mensajes de error** contextuales: cada modal muestra el error junto al formulario, no solo en consola.

**Referencia**: [`useToast.js`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/composables/useToast.js)

### Navegación clara

- Sidebar fijo con indicador de ruta activa (`router-link-active`).
- Breadcrumb implícito: la jerarquía restaurante → catálogo → sección → producto es visible siempre.
- La carta del cliente (`/restaurant/:id`) no tiene sidebar; interfaz minimalista para el comensal.

### Confirmación de acciones destructivas

Eliminar un restaurante requiere:
1. Clic en botón "Eliminar" → abre `RestaurantDeleteModal`
2. Marcar checkbox de confirmación explícita
3. Clic en "Confirmar eliminación"

```vue
<!-- frontend/src/components/RestaurantDeleteModal.vue -->
<input type="checkbox" v-model="confirmed" id="confirm-delete" />
<label for="confirm-delete">
  Entiendo que esta acción es irreversible
</label>
<button :disabled="!confirmed" @click="$emit('confirm')">
  Confirmar eliminación
</button>
```

**Referencia**: [`RestaurantDeleteModal.vue`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/components/RestaurantDeleteModal.vue)

### Validación de formularios

- Validación en el cliente (campos `required`, `type="number"`, `min`).
- Validación en el servidor (FormRequests de Laravel) con errores devueltos y mostrados.
- Las imágenes se validan en el composable `useImageField`: tipo MIME, tamaño máximo, compresión automática.

**Referencia**: [`useImageField.js`](https://github.com/santitorbra98-blip/scan2order-lite/blob/main/frontend/src/composables/useImageField.js)

### Paginación

Las listas de restaurantes y usuarios usan paginación del servidor (Laravel `paginate()`) con controles de "Anterior / Siguiente" en el frontend para no cargar toda la tabla.

---

## ❌ Qué falta

| Criterio | Estado | Solución concreta |
|----------|--------|-------------------|
| **WCAG — atributos ARIA** | No implementados | Añadir `role="dialog"`, `aria-modal="true"`, `aria-labelledby` a todos los modales; `aria-live="polite"` al contenedor de toasts; `aria-label` a botones con solo icono (ej. botón de cerrar modal con `×`) |
| **WCAG — foco en modales** | No implementado | Al abrir un modal, mover el foco al primer campo (`autofocus`) y atrapar el foco dentro del modal mientras esté abierto (`focus-trap`) |
| **Framework CSS** | CSS nativo | Si se quiere cubrir literalmente la rúbrica: integrar **Tailwind CSS v4** (`npm install tailwindcss`) y migrar las clases utilitarias. Alternativa más ligera: añadir **Pico CSS** (sin clases, solo HTML semántico) |

### Ejemplo de fix ARIA para modales

```html
<!-- Cómo debería quedar un modal -->
<div
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title"
  @keydown.esc="$emit('close')"
>
  <h2 id="modal-title">Nuevo catálogo</h2>
  <!-- ... -->
  <button aria-label="Cerrar modal" @click="$emit('close')">×</button>
</div>
```

### Ejemplo de fix ARIA para toasts

```html
<div role="status" aria-live="polite" aria-atomic="true">
  <p v-if="toast.show">{{ toast.message }}</p>
</div>
```

Implementar ARIA en modales y toasts es la mejora más impactante para la accesibilidad y la más directa de aplicar.
