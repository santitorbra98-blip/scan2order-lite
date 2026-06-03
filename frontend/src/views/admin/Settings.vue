<template>
  <div class="settings-container">
    <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">{{ toast.message }}</div>

    <div class="header">
      <h1>⚙️ Configuración</h1>
    </div>

    <div v-if="isLoading" class="loading">Cargando configuración...</div>
    <div v-else-if="loadError" class="error">{{ loadError }}</div>

    <div v-else class="card">
      <h2 class="card-title">Límites predeterminados para nuevos administradores</h2>
      <p class="card-hint">
        Estos valores se aplicarán automáticamente al crear un nuevo usuario administrador.
        Dejar en blanco significa sin límite.
      </p>

      <form @submit.prevent="save" class="settings-form">
        <div class="form-row">
          <div class="form-group">
            <label for="s-max-restaurants">🏠 Máx. restaurantes por admin:</label>
            <input
              id="s-max-restaurants"
              v-model.number="form.default_max_restaurants"
              type="number" min="0" max="9999"
              placeholder="Sin límite"
            />
          </div>
          <div class="form-group">
            <label for="s-max-catalogs">📋 Máx. catálogos por admin:</label>
            <input
              id="s-max-catalogs"
              v-model.number="form.default_max_catalogs"
              type="number" min="0" max="9999"
              placeholder="Sin límite"
            />
          </div>
          <div class="form-group">
            <label for="s-max-products">🍽️ Máx. productos por admin:</label>
            <input
              id="s-max-products"
              v-model.number="form.default_max_products"
              type="number" min="0" max="9999"
              placeholder="Sin límite"
            />
          </div>
        </div>

        <div v-if="saveError" class="error">{{ saveError }}</div>

        <div class="form-actions">
          <button type="submit" class="btn-save" :disabled="isSaving">
            {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'

const isLoading = ref(false)
const isSaving  = ref(false)
const loadError = ref(null)
const saveError = ref(null)
const toast     = ref({ show: false, type: 'success', message: '' })
let toastTimer  = null

const form = ref({
  default_max_restaurants: null,
  default_max_catalogs:    null,
  default_max_products:    null,
})

function showToast(msg, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { show: true, type, message: msg }
  toastTimer  = setTimeout(() => { toast.value.show = false }, 2500)
}

function toFormValue(v) {
  if (v === null || v === undefined || v === '') return null
  const n = parseInt(v, 10)
  return isNaN(n) ? null : n
}

async function fetchSettings() {
  isLoading.value = true
  loadError.value = null
  try {
    const data = await api.get('/settings')
    form.value.default_max_restaurants = toFormValue(data.default_max_restaurants)
    form.value.default_max_catalogs    = toFormValue(data.default_max_catalogs)
    form.value.default_max_products    = toFormValue(data.default_max_products)
  } catch (err) {
    loadError.value = err.message || 'Error al cargar configuración'
  } finally {
    isLoading.value = false
  }
}

async function save() {
  isSaving.value  = true
  saveError.value = null
  try {
    const payload = {
      default_max_restaurants: form.value.default_max_restaurants === '' ? null : form.value.default_max_restaurants,
      default_max_catalogs:    form.value.default_max_catalogs    === '' ? null : form.value.default_max_catalogs,
      default_max_products:    form.value.default_max_products    === '' ? null : form.value.default_max_products,
    }
    await api.put('/settings', payload)
    showToast('Configuración guardada')
  } catch (err) {
    saveError.value = err?.message || 'Error al guardar'
  } finally {
    isSaving.value = false
  }
}

onMounted(fetchSettings)
</script>

<style scoped>
.settings-container { max-width: 800px; margin: 0 auto; padding: 2rem; }

.toast {
  position: fixed; top: 1rem; right: 1rem; z-index: 1000;
  padding: 0.85rem 1.5rem; border-radius: 10px; font-weight: 600;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15); animation: slideIn 0.3s;
}
.toast-success { background: #dcfce7; color: #166534; }
.toast-error   { background: #fef2f2; color: #dc2626; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.header { margin-bottom: 2rem; }
.header h1 { font-size: 2rem; color: #1e293b; margin: 0; }

.loading { color: #1e293b; padding: 2rem; text-align: center; }
.error   { color: #dc2626; background: #fef2f2; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }

.card {
  background: white; border-radius: 16px; padding: 2rem;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1.5px solid #e2e8f0;
}
.card-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0 0 0.4rem; }
.card-hint  { font-size: 0.88rem; color: #94a3b8; margin: 0 0 1.75rem; }

.settings-form {}

.form-row {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem;
  margin-bottom: 1.25rem;
}
.form-row-2 { grid-template-columns: repeat(2, 1fr); }
.form-row-3 { grid-template-columns: repeat(3, 1fr); }

.form-group { display: flex; flex-direction: column; gap: 0.35rem; }
.form-group label { font-weight: 600; font-size: 0.88rem; color: #334155; }
.form-group input {
  padding: 0.65rem 0.85rem; border: 1.5px solid #e2e8f0; border-radius: 8px;
  font-size: 0.95rem; color: #1e293b; background: #f8fafc;
}
.form-group input:focus { border-color: #667eea; outline: none; background: white; }

.form-actions { display: flex; justify-content: flex-end; }
.btn-save {
  padding: 0.7rem 1.75rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 10px; font-weight: 700;
  font-size: 1rem; cursor: pointer;
}
.btn-save:hover:not(:disabled) { opacity: 0.9; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 600px) {
  .form-row { grid-template-columns: 1fr; }
}
</style>
