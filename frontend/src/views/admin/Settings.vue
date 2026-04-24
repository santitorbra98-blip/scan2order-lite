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

        <div class="section-divider"></div>

        <h2 class="card-title">Configuración de correo (SMTP)</h2>
        <p class="card-hint">Credenciales usadas para enviar emails desde la aplicación.</p>

        <div class="form-row form-row-2">
          <div class="form-group">
            <label for="s-mail-host">Servidor SMTP (host):</label>
            <input id="s-mail-host" v-model="form.mail_host" type="text" placeholder="smtp.gmail.com" />
          </div>
          <div class="form-group">
            <label for="s-mail-port">Puerto:</label>
            <input id="s-mail-port" v-model.number="form.mail_port" type="number" min="1" max="65535" placeholder="587" />
          </div>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label for="s-mail-username">Usuario:</label>
            <input id="s-mail-username" v-model="form.mail_username" type="text" placeholder="tu@gmail.com" autocomplete="off" />
          </div>
          <div class="form-group">
            <label for="s-mail-password">Contraseña / App Password:</label>
            <div class="input-pw-wrap">
              <input
                id="s-mail-password"
                v-model="form.mail_password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••••••"
                autocomplete="new-password"
              />
              <button type="button" class="btn-eye" @click="showPassword = !showPassword">
                {{ showPassword ? '🙈' : '👁️' }}
              </button>
            </div>
          </div>
        </div>
        <div class="form-row form-row-3">
          <div class="form-group">
            <label for="s-mail-encryption">Cifrado:</label>
            <select id="s-mail-encryption" v-model="form.mail_encryption">
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
              <option value="">Ninguno</option>
            </select>
          </div>
          <div class="form-group">
            <label for="s-mail-from-address">Dirección remitente:</label>
            <input id="s-mail-from-address" v-model="form.mail_from_address" type="email" placeholder="noreply@tudominio.com" />
          </div>
          <div class="form-group">
            <label for="s-mail-from-name">Nombre remitente:</label>
            <input id="s-mail-from-name" v-model="form.mail_from_name" type="text" placeholder="Mi App" />
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
const toast       = ref({ show: false, type: 'success', message: '' })
const showPassword = ref(false)
let toastTimer  = null

const form = ref({
  default_max_restaurants: null,
  default_max_catalogs:    null,
  default_max_products:    null,
  mail_mailer:       'smtp',
  mail_host:         '',
  mail_port:         587,
  mail_username:     '',
  mail_password:     '',
  mail_encryption:   'tls',
  mail_from_address: '',
  mail_from_name:    '',
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
    form.value.mail_mailer       = data.mail_mailer       ?? 'smtp'
    form.value.mail_host         = data.mail_host         ?? ''
    form.value.mail_port         = data.mail_port ? parseInt(data.mail_port, 10) : 587
    form.value.mail_username     = data.mail_username     ?? ''
    form.value.mail_password     = data.mail_password     ?? ''
    form.value.mail_encryption   = data.mail_encryption   ?? 'tls'
    form.value.mail_from_address = data.mail_from_address ?? ''
    form.value.mail_from_name    = data.mail_from_name    ?? ''
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
      mail_mailer:       form.value.mail_mailer       || null,
      mail_host:         form.value.mail_host         || null,
      mail_port:         form.value.mail_port         || null,
      mail_username:     form.value.mail_username     || null,
      mail_password:     form.value.mail_password     || null,
      mail_encryption:   form.value.mail_encryption   || null,
      mail_from_address: form.value.mail_from_address || null,
      mail_from_name:    form.value.mail_from_name    || null,
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

.loading { color: #64748b; padding: 2rem; text-align: center; }
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

.section-divider {
  border: none; border-top: 1.5px solid #e2e8f0; margin: 1.75rem 0;
}

.input-pw-wrap { display: flex; gap: 0; }
.input-pw-wrap input { flex: 1; border-radius: 8px 0 0 8px; }
.btn-eye {
  padding: 0 0.75rem; background: #f1f5f9; border: 1.5px solid #e2e8f0;
  border-left: none; border-radius: 0 8px 8px 0; cursor: pointer; font-size: 1rem;
}
.btn-eye:hover { background: #e2e8f0; }

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
