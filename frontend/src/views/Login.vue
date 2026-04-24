<template>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <div class="login-icon">🔐</div>
        <h1>Iniciar Sesión</h1>
        <p>Accede a tu panel de administración</p>
      </div>

      <div class="card-nav">
        <router-link to="/" class="btn-home">← Inicio</router-link>
      </div>

      <!-- Login form -->
      <form v-if="step === 'login'" @submit.prevent="handleLogin" class="login-form">
        <div class="form-group">
          <label for="email">📧 Email</label>
          <input id="email" v-model="form.email" type="email" required placeholder="tu@email.com" autocomplete="email" />
        </div>
        <div class="form-group">
          <label for="password">🔒 Contraseña</label>
          <input id="password" v-model="form.password" type="password" required placeholder="Tu contraseña" autocomplete="current-password" />
        </div>

        <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

        <button type="submit" class="btn-submit" :disabled="loading">
          {{ loading ? 'Entrando...' : 'Entrar' }}
        </button>

        <div class="form-links">
          <button type="button" class="link-btn" @click="step = 'forgot'">¿Olvidaste tu contraseña?</button>
          <router-link to="/register" class="link-btn">Crear cuenta</router-link>
        </div>
      </form>

      <!-- Forgot password: step 1 - request code -->
      <form v-else-if="step === 'forgot'" @submit.prevent="handleForgotPassword" class="login-form">
        <p class="step-info">Introduce tu email para recibir un código de verificación.</p>
        <div class="form-group">
          <label for="forgot-email">📧 Email</label>
          <input id="forgot-email" v-model="forgotEmail" type="email" required placeholder="tu@email.com" />
        </div>
        <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>
        <div v-if="successMsg" class="success-msg">{{ successMsg }}</div>
        <button type="submit" class="btn-submit" :disabled="loading">
          {{ loading ? 'Enviando...' : 'Enviar código' }}
        </button>
        <button type="button" class="link-btn" @click="step = 'login'; errorMsg = ''; successMsg = ''">← Volver al login</button>
      </form>

      <!-- Forgot password: step 2 - verify code -->
      <form v-else-if="step === 'verify-code'" @submit.prevent="handleVerifyCode" class="login-form">
        <p class="step-info">Introduce el código de 6 dígitos que enviamos a <strong>{{ forgotEmail }}</strong></p>
        <div class="form-group">
          <label for="reset-code">🔑 Código de verificación</label>
          <input id="reset-code" v-model="resetCode" type="text" required placeholder="123456" maxlength="6" inputmode="numeric" autocomplete="one-time-code" />
        </div>
        <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>
        <button type="submit" class="btn-submit" :disabled="loading">
          {{ loading ? 'Verificando...' : 'Verificar código' }}
        </button>
        <div class="form-links">
          <button type="button" class="link-btn" @click="handleResendCode" :disabled="resendCooldown > 0">
            {{ resendCooldown > 0 ? `Reenviar en ${resendCooldown}s` : 'Reenviar código' }}
          </button>
          <button type="button" class="link-btn" @click="step = 'forgot'; errorMsg = ''">← Cambiar email</button>
        </div>
      </form>

      <!-- Forgot password: step 3 - new password -->
      <form v-else-if="step === 'new-password'" @submit.prevent="handleResetPassword" class="login-form">
        <p class="step-info">Introduce tu nueva contraseña (mínimo 12 caracteres).</p>
        <div class="form-group">
          <label for="new-password">🔒 Nueva contraseña</label>
          <input id="new-password" v-model="newPassword" type="password" required placeholder="Mínimo 12 caracteres" minlength="12" autocomplete="new-password" />
        </div>
        <div class="form-group">
          <label for="confirm-password">🔒 Confirmar contraseña</label>
          <input id="confirm-password" v-model="confirmPassword" type="password" required placeholder="Repite la contraseña" minlength="12" autocomplete="new-password" />
        </div>
        <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>
        <div v-if="successMsg" class="success-msg">{{ successMsg }}</div>
        <button type="submit" class="btn-submit" :disabled="loading">
          {{ loading ? 'Guardando...' : 'Cambiar contraseña' }}
        </button>
        <button type="button" class="link-btn" @click="step = 'login'; errorMsg = ''; successMsg = ''">← Volver al login</button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const step = ref('login')
const loading = ref(false)
const errorMsg = ref('')
const successMsg = ref('')

const form = ref({ email: '', password: '' })
const forgotEmail = ref('')
const resetCode = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const resendCooldown = ref(0)
let cooldownTimer = null

function startResendCooldown() {
  resendCooldown.value = 60
  clearInterval(cooldownTimer)
  cooldownTimer = setInterval(() => {
    resendCooldown.value--
    if (resendCooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}

onUnmounted(() => clearInterval(cooldownTimer))

async function handleLogin() {
  loading.value = true
  errorMsg.value = ''
  try {
    await auth.login(form.value.email, form.value.password)
    router.push('/admin')
  } catch (err) {
    errorMsg.value = err?.message || auth.error || 'Error al iniciar sesión'
  } finally {
    loading.value = false
  }
}

async function handleForgotPassword() {
  loading.value = true
  errorMsg.value = ''
  successMsg.value = ''
  try {
    await auth.forgotPassword(forgotEmail.value)
    successMsg.value = ''
    step.value = 'verify-code'
    startResendCooldown()
  } catch (err) {
    errorMsg.value = err?.message || auth.error || 'Error al enviar el código'
  } finally {
    loading.value = false
  }
}

async function handleResendCode() {
  if (resendCooldown.value > 0) return
  loading.value = true
  errorMsg.value = ''
  try {
    await auth.forgotPassword(forgotEmail.value)
    startResendCooldown()
  } catch (err) {
    errorMsg.value = err?.message || 'Error al reenviar el código'
  } finally {
    loading.value = false
  }
}

async function handleVerifyCode() {
  loading.value = true
  errorMsg.value = ''
  try {
    await auth.verifyResetCode(forgotEmail.value, resetCode.value)
    step.value = 'new-password'
    errorMsg.value = ''
  } catch (err) {
    errorMsg.value = err?.message || 'Código inválido o expirado'
  } finally {
    loading.value = false
  }
}

async function handleResetPassword() {
  if (newPassword.value !== confirmPassword.value) {
    errorMsg.value = 'Las contraseñas no coinciden'
    return
  }
  if (newPassword.value.length < 12) {
    errorMsg.value = 'La contraseña debe tener al menos 12 caracteres'
    return
  }
  loading.value = true
  errorMsg.value = ''
  successMsg.value = ''
  try {
    await auth.resetPassword(forgotEmail.value, resetCode.value, newPassword.value, confirmPassword.value)
    successMsg.value = 'Contraseña cambiada correctamente. Ya puedes iniciar sesión.'
    setTimeout(() => {
      step.value = 'login'
      errorMsg.value = ''
      successMsg.value = ''
      resetCode.value = ''
      newPassword.value = ''
      confirmPassword.value = ''
    }, 2500)
  } catch (err) {
    errorMsg.value = err?.message || 'Error al cambiar la contraseña'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  background-color: #667eea;
  background-image:
    radial-gradient(circle at 12% 18%, rgba(255, 255, 255, 0.26) 0, rgba(255, 255, 255, 0) 26%),
    radial-gradient(circle at 84% 14%, rgba(255, 255, 255, 0.2) 0, rgba(255, 255, 255, 0) 30%),
    radial-gradient(circle at 78% 82%, rgba(255, 255, 255, 0.16) 0, rgba(255, 255, 255, 0) 34%),
    repeating-linear-gradient(
      -35deg,
      rgba(255, 255, 255, 0.1) 0,
      rgba(255, 255, 255, 0.1) 2px,
      rgba(255, 255, 255, 0) 2px,
      rgba(255, 255, 255, 0) 24px
    ),
    linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem;
}

.login-card {
  width: 100%; max-width: 440px;
  background: white; border-radius: 20px; padding: 2.5rem;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.card-nav { margin-bottom: 1rem; }
.btn-home { color: #64748b; font-size: 0.9rem; text-decoration: none; font-weight: 500; }
.btn-home:hover { color: #334155; }
.login-header { text-align: center; margin-bottom: 2rem; }
.login-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
.login-header h1 { margin: 0; font-size: 1.8rem; color: #1e293b; }
.login-header p { color: #64748b; margin-top: 0.5rem; }

.login-form { display: flex; flex-direction: column; gap: 1.25rem; }

.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-group label { font-weight: 600; font-size: 0.9rem; color: #334155; }
.form-group input {
  padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 1rem; transition: border-color 0.2s;
}
.form-group input:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.15); }

.btn-submit {
  padding: 0.85rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700;
  cursor: pointer; transition: opacity 0.2s;
}
.btn-submit:hover { opacity: 0.9; }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

.error-msg { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; text-align: center; }
.success-msg { background: #f0fdf4; color: #16a34a; padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; text-align: center; }

.form-links { display: flex; justify-content: space-between; }
.link-btn { background: none; border: none; color: #667eea; cursor: pointer; font-size: 0.9rem; text-decoration: none; }
.link-btn:hover { text-decoration: underline; }

.step-info { color: #64748b; font-size: 0.95rem; margin: 0; }
</style>
