<template>
  <div class="profile-container">
    <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">{{ toast.message }}</div>

    <div class="header">
      <h1>👤 Mi perfil</h1>
      <p class="header-sub">Gestiona tus datos personales y seguridad</p>
    </div>

    <!-- Personal data -->
    <div class="card">
      <h2 class="card-title">Datos personales</h2>
      <form @submit.prevent="saveProfile" class="profile-form">
        <div class="form-row">
          <div class="form-group">
            <label for="p-name">Nombre</label>
            <input id="p-name" v-model="profileForm.name" type="text" required maxlength="255" />
          </div>
          <div class="form-group">
            <label for="p-phone">Teléfono</label>
            <input id="p-phone" v-model="profileForm.phone" type="tel" maxlength="30" placeholder="Opcional" />
          </div>
        </div>
        <div class="form-group">
          <label>Email actual</label>
          <input :value="auth.user?.email" type="email" disabled class="input-disabled" />
          <span class="hint">Para cambiar el email usa la sección "Cambiar email".</span>
        </div>
        <div v-if="profileError" class="error">{{ profileError }}</div>
        <div class="form-actions">
          <button type="submit" class="btn-primary" :disabled="profileSaving">
            {{ profileSaving ? 'Guardando...' : 'Guardar cambios' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Change password -->
    <div class="card">
      <h2 class="card-title">Cambiar contraseña</h2>
      <p class="card-hint">Recibirás un código de verificación en tu correo actual para confirmar el cambio.</p>

      <div v-if="!pwCodeSent">
        <div v-if="pwRequestError" class="error">{{ pwRequestError }}</div>
        <div class="form-actions">
          <button class="btn-secondary" :disabled="pwRequesting" @click="requestPasswordChange">
            {{ pwRequesting ? 'Enviando código...' : 'Enviar código de verificación' }}
          </button>
        </div>
      </div>

      <form v-else @submit.prevent="confirmPasswordChange" class="profile-form">
        <div class="form-group">
          <label for="pw-code">Código recibido por email</label>
          <input
            id="pw-code"
            v-model="pwForm.code"
            type="text"
            inputmode="numeric"
            maxlength="6"
            placeholder="123456"
            required
            autocomplete="one-time-code"
          />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="pw-new">Nueva contraseña</label>
            <input
              id="pw-new"
              v-model="pwForm.password"
              type="password"
              required
              minlength="12"
              placeholder="Mínimo 12 caracteres"
              autocomplete="new-password"
            />
          </div>
          <div class="form-group">
            <label for="pw-confirm">Confirmar contraseña</label>
            <input
              id="pw-confirm"
              v-model="pwForm.password_confirmation"
              type="password"
              required
              placeholder="Repite la contraseña"
              autocomplete="new-password"
            />
          </div>
        </div>
        <div v-if="pwError" class="error">{{ pwError }}</div>
        <div class="form-actions">
          <button type="button" class="btn-text" @click="pwCodeSent = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="pwSaving">
            {{ pwSaving ? 'Cambiando...' : 'Cambiar contraseña' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Change email -->
    <div class="card">
      <h2 class="card-title">Cambiar email</h2>
      <p class="card-hint">Se enviará un código al nuevo correo para verificar que te pertenece.</p>

      <div v-if="!emailCodeSent">
        <form @submit.prevent="requestEmailChange" class="profile-form">
          <div class="form-group">
            <label for="email-new">Nuevo email</label>
            <input
              id="email-new"
              v-model="emailForm.new_email"
              type="email"
              required
              maxlength="255"
              placeholder="nuevo@correo.com"
              autocomplete="email"
            />
          </div>
          <div v-if="emailRequestError" class="error">{{ emailRequestError }}</div>
          <div class="form-actions">
            <button type="submit" class="btn-secondary" :disabled="emailRequesting">
              {{ emailRequesting ? 'Enviando código...' : 'Enviar código al nuevo correo' }}
            </button>
          </div>
        </form>
      </div>

      <form v-else @submit.prevent="confirmEmailChange" class="profile-form">
        <p class="info-notice">
          Se ha enviado un código a <strong>{{ emailForm.new_email }}</strong>.
        </p>
        <div class="form-group">
          <label for="email-code">Código recibido</label>
          <input
            id="email-code"
            v-model="emailForm.code"
            type="text"
            inputmode="numeric"
            maxlength="6"
            placeholder="123456"
            required
            autocomplete="one-time-code"
          />
        </div>
        <div v-if="emailError" class="error">{{ emailError }}</div>
        <div class="form-actions">
          <button type="button" class="btn-text" @click="emailCodeSent = false">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="emailSaving">
            {{ emailSaving ? 'Verificando...' : 'Confirmar cambio de email' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Delete account -->
    <div class="card card-danger">
      <h2 class="card-title danger-title">Eliminar cuenta</h2>
      <p class="card-hint">
        Esta acción es <strong>irreversible</strong>. Tu cuenta, restaurantes y todos los datos
        asociados serán eliminados permanentemente.
      </p>

      <div v-if="!showDeleteForm">
        <button class="btn-danger" @click="showDeleteForm = true">Eliminar mi cuenta</button>
      </div>

      <form v-else @submit.prevent="deleteAccount" class="profile-form">
        <div class="form-group">
          <label for="del-password">Introduce tu contraseña para confirmar</label>
          <input
            id="del-password"
            v-model="deletePassword"
            type="password"
            required
            placeholder="Tu contraseña actual"
            autocomplete="current-password"
          />
        </div>
        <div v-if="deleteError" class="error">{{ deleteError }}</div>
        <div class="form-actions">
          <button type="button" class="btn-text" @click="showDeleteForm = false; deletePassword = ''">
            Cancelar
          </button>
          <button type="submit" class="btn-danger" :disabled="deleteLoading">
            {{ deleteLoading ? 'Eliminando...' : 'Confirmar eliminación' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../../services/api'
import { useAuthStore } from '../../stores/auth'

const auth   = useAuthStore()
const router = useRouter()

// ─── Toast ────────────────────────────────────────────────────

const toast    = ref({ show: false, type: 'success', message: '' })
let toastTimer = null

function showToast(msg, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { show: true, type, message: msg }
  toastTimer  = setTimeout(() => { toast.value.show = false }, 3000)
}

// ─── Personal data ────────────────────────────────────────────

const profileForm   = ref({ name: '', phone: '' })
const profileSaving = ref(false)
const profileError  = ref(null)

onMounted(() => {
  profileForm.value.name  = auth.user?.name  ?? ''
  profileForm.value.phone = auth.user?.phone ?? ''
})

async function saveProfile() {
  profileSaving.value = true
  profileError.value  = null
  try {
    const data = await api.put('/profile', profileForm.value)
    if (auth.user) {
      auth.user.name  = data.user.name
      auth.user.phone = data.user.phone
    }
    showToast('Perfil actualizado correctamente')
  } catch (err) {
    profileError.value = err.message || 'Error al guardar el perfil'
  } finally {
    profileSaving.value = false
  }
}

// ─── Password change ──────────────────────────────────────────

const pwCodeSent    = ref(false)
const pwRequesting  = ref(false)
const pwSaving      = ref(false)
const pwRequestError = ref(null)
const pwError       = ref(null)
const pwForm        = ref({ code: '', password: '', password_confirmation: '' })

async function requestPasswordChange() {
  pwRequesting.value  = true
  pwRequestError.value = null
  try {
    await api.post('/profile/request-password-change', {})
    pwCodeSent.value = true
    pwForm.value     = { code: '', password: '', password_confirmation: '' }
  } catch (err) {
    pwRequestError.value = err.message || 'Error al enviar el código'
  } finally {
    pwRequesting.value = false
  }
}

async function confirmPasswordChange() {
  pwSaving.value = true
  pwError.value  = null
  try {
    await api.post('/profile/confirm-password-change', pwForm.value)
    pwCodeSent.value = false
    pwForm.value     = { code: '', password: '', password_confirmation: '' }
    showToast('Contraseña actualizada correctamente')
  } catch (err) {
    pwError.value = err.message || 'Error al cambiar la contraseña'
  } finally {
    pwSaving.value = false
  }
}

// ─── Email change ─────────────────────────────────────────────

const emailCodeSent    = ref(false)
const emailRequesting  = ref(false)
const emailSaving      = ref(false)
const emailRequestError = ref(null)
const emailError       = ref(null)
const emailForm        = ref({ new_email: '', code: '' })

async function requestEmailChange() {
  emailRequesting.value  = true
  emailRequestError.value = null
  try {
    await api.post('/profile/request-email-change', { new_email: emailForm.value.new_email })
    emailCodeSent.value  = true
    emailForm.value.code = ''
  } catch (err) {
    emailRequestError.value = err.message || 'Error al enviar el código'
  } finally {
    emailRequesting.value = false
  }
}

async function confirmEmailChange() {
  emailSaving.value = true
  emailError.value  = null
  try {
    const data = await api.post('/profile/confirm-email-change', { code: emailForm.value.code })
    if (auth.user) {
      auth.user.email = data.email
    }
    emailCodeSent.value = false
    emailForm.value     = { new_email: '', code: '' }
    showToast('Email actualizado correctamente')
  } catch (err) {
    emailError.value = err.message || 'Error al verificar el código'
  } finally {
    emailSaving.value = false
  }
}

// ─── Delete account ───────────────────────────────────────────

const showDeleteForm = ref(false)
const deletePassword = ref('')
const deleteLoading  = ref(false)
const deleteError    = ref(null)

async function deleteAccount() {
  deleteLoading.value = true
  deleteError.value   = null
  try {
    await api.delete('/profile', { password: deletePassword.value })
    await auth.logout()
    router.push('/login')
  } catch (err) {
    deleteError.value = err.message || 'Error al eliminar la cuenta'
  } finally {
    deleteLoading.value = false
  }
}
</script>

<style scoped>
.profile-container { max-width: 760px; margin: 0 auto; padding: 2rem; }

/* Toast */
.toast {
  position: fixed; top: 1rem; right: 1rem; z-index: 1000;
  padding: 0.85rem 1.5rem; border-radius: 10px; font-weight: 600;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15); animation: slideIn 0.3s;
}
.toast-success { background: #dcfce7; color: #166534; }
.toast-error   { background: #fef2f2; color: #dc2626; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

/* Header */
.header { margin-bottom: 2rem; }
.header h1  { font-size: 2rem; color: #1e293b; margin: 0 0 0.25rem; }
.header-sub { color: #64748b; margin: 0; font-size: 0.95rem; }

/* Card */
.card {
  background: white; border-radius: 16px; padding: 2rem;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1.5px solid #e2e8f0;
  margin-bottom: 1.5rem;
}
.card-danger { border-color: #fca5a5; }
.card-title  { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0 0 0.5rem; }
.danger-title { color: #dc2626; }
.card-hint   { font-size: 0.88rem; color: #94a3b8; margin: 0 0 1.5rem; }

/* Form */
.profile-form {}
.form-row {
  display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;
}
.form-group { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 1.25rem; }
.form-group:last-of-type { margin-bottom: 0; }
.form-group label { font-weight: 600; font-size: 0.88rem; color: #334155; }
.form-group input {
  padding: 0.65rem 0.85rem; border: 1.5px solid #e2e8f0; border-radius: 8px;
  font-size: 0.95rem; color: #1e293b; background: #f8fafc;
}
.form-group input:focus { border-color: #667eea; outline: none; background: white; }
.input-disabled { background: #f1f5f9 !important; color: #94a3b8 !important; cursor: not-allowed; }
.hint { font-size: 0.8rem; color: #94a3b8; }

/* Actions */
.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem; align-items: center; }
.info-notice { font-size: 0.9rem; color: #475569; background: #f0f9ff; border-radius: 8px; padding: 0.6rem 0.9rem; margin-bottom: 1rem; }

/* Errors */
.error {
  color: #dc2626; background: #fef2f2; padding: 0.65rem 0.9rem;
  border-radius: 8px; margin-bottom: 1rem; font-size: 0.88rem;
}

/* Buttons */
.btn-primary {
  padding: 0.65rem 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 10px; font-weight: 700;
  font-size: 0.95rem; cursor: pointer;
}
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-secondary {
  padding: 0.65rem 1.5rem; background: #f1f5f9;
  color: #1e293b; border: 1.5px solid #e2e8f0; border-radius: 10px; font-weight: 600;
  font-size: 0.95rem; cursor: pointer;
}
.btn-secondary:hover:not(:disabled) { background: #e2e8f0; }
.btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-text {
  padding: 0.65rem 1rem; background: transparent; color: #64748b;
  border: none; border-radius: 8px; font-size: 0.95rem; cursor: pointer;
}
.btn-text:hover { color: #1e293b; }

.btn-danger {
  padding: 0.65rem 1.5rem; background: #dc2626;
  color: white; border: none; border-radius: 10px; font-weight: 700;
  font-size: 0.95rem; cursor: pointer;
}
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 600px) {
  .form-row { grid-template-columns: 1fr; }
  .profile-container { padding: 1rem; }
}
</style>
