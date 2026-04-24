<template>
  <div class="register-container">
    <div class="register-card">
      <div class="register-header">
        <div class="register-icon">📝</div>
        <h1>Crear Cuenta</h1>
        <p>Registra tu restaurante en Scan2Order</p>
      </div>

      <div class="card-nav">
        <router-link to="/" class="btn-home">← Inicio</router-link>
      </div>

      <!-- Step 1: Form -->
      <form v-if="step === 'form'" @submit.prevent="handleRegister" class="register-form">
        <div class="form-group">
          <label for="name">👤 Nombre completo</label>
          <input id="name" v-model="form.name" type="text" required placeholder="Tu nombre" autocomplete="name" />
        </div>
        <div class="form-group">
          <label for="email">📧 Email</label>
          <input id="email" v-model="form.email" type="email" required placeholder="tu@email.com" autocomplete="email" />
        </div>
        <div class="form-group">
          <label for="password">🔒 Contraseña</label>
          <input id="password" v-model="form.password" type="password" required minlength="12" placeholder="Mínimo 12 caracteres" autocomplete="new-password" />
        </div>
        <div class="form-group">
          <label for="password_confirmation">🔒 Confirmar contraseña</label>
          <input id="password_confirmation" v-model="form.password_confirmation" type="password" required placeholder="Repite la contraseña" autocomplete="new-password" />
        </div>

        <div class="legal-consents">
          <label class="consent-label">
            <input v-model="form.legal_accepted" type="checkbox" required />
            <span>Acepto el <router-link to="/legal/aviso-legal" target="_blank">Aviso Legal</router-link> y la <router-link to="/legal/privacidad" target="_blank">Política de Privacidad</router-link></span>
          </label>
          <label class="consent-label">
            <input v-model="form.cookies_accepted" type="checkbox" required />
            <span>Acepto la <router-link to="/legal/cookies" target="_blank">Política de Cookies</router-link></span>
          </label>
        </div>

        <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

        <button type="submit" class="btn-submit" :disabled="loading">
          {{ loading ? 'Registrando...' : 'Crear cuenta' }}
        </button>

        <div class="form-links">
          <router-link to="/login" class="link-btn">¿Ya tienes cuenta? Inicia sesión</router-link>
        </div>
      </form>

      <!-- Step 2: Email verification -->
      <div v-else-if="step === 'verify'" class="verify-step">
        <div class="verify-icon">📬</div>
        <h2>Verifica tu email</h2>
        <p>Hemos enviado un código de verificación a <strong>{{ form.email }}</strong></p>

        <form @submit.prevent="handleVerify" class="register-form">
          <div class="form-group">
            <label for="code">Código de verificación</label>
            <input id="code" v-model="verifyCode" type="text" required placeholder="123456" maxlength="6" class="code-input" />
          </div>
          <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>
          <button type="submit" class="btn-submit" :disabled="loading">
            {{ loading ? 'Verificando...' : 'Verificar' }}
          </button>
        </form>

        <button type="button" class="link-btn" :disabled="resendCooldown > 0" @click="handleResend">
          {{ resendCooldown > 0 ? `Reenviar en ${resendCooldown}s` : 'Reenviar código' }}
        </button>
        <button type="button" class="link-btn" @click="step = 'form'; errorMsg = ''">← Editar datos</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
const router = useRouter()
const auth = useAuthStore()

const step = ref('form')
const loading = ref(false)
const errorMsg = ref('')
const verifyCode = ref('')
const resendCooldown = ref(0)
let cooldownTimer = null

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  legal_accepted: false,
  cookies_accepted: false,
})

async function handleRegister() {
  loading.value = true
  errorMsg.value = ''
  try {
    await auth.register(
      form.value.name,
      null,
      form.value.email,
      form.value.password,
      form.value.password_confirmation,
      { acceptTerms: form.value.legal_accepted, acceptPrivacy: form.value.legal_accepted, acceptMarketing: false },
    )
    step.value = 'verify'
    startResendCooldown()
  } catch (err) {
    const errors = err?.data?.errors
    if (errors) {
      errorMsg.value = Object.values(errors).flat().join('. ')
    } else {
      errorMsg.value = err?.message || auth.error || 'Error al registrarse'
    }
  } finally {
    loading.value = false
  }
}

async function handleVerify() {
  loading.value = true
  errorMsg.value = ''
  try {
    await auth.verifyRegister(
      form.value.email,
      verifyCode.value,
      form.value.password,
      form.value.password_confirmation,
    )
    router.push('/admin')
  } catch (err) {
    errorMsg.value = auth.error || err?.message || 'Código inválido o expirado'
  } finally {
    loading.value = false
  }
}

async function handleResend() {
  if (resendCooldown.value > 0) return
  try {
    await auth.register(
      form.value.name,
      null,
      form.value.email,
      form.value.password,
      form.value.password_confirmation,
      { acceptTerms: form.value.legal_accepted, acceptPrivacy: form.value.legal_accepted, acceptMarketing: false },
    )
    startResendCooldown()
  } catch {
    errorMsg.value = 'Error al reenviar el código'
  }
}

function startResendCooldown() {
  resendCooldown.value = 60
  cooldownTimer = setInterval(() => {
    resendCooldown.value--
    if (resendCooldown.value <= 0) clearInterval(cooldownTimer)
  }, 1000)
}

onUnmounted(() => { if (cooldownTimer) clearInterval(cooldownTimer) })
</script>

<style scoped>
.register-container {
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

.register-card {
  width: 100%; max-width: 480px;
  background: white; border-radius: 20px; padding: 2.5rem;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.card-nav { margin-bottom: 1rem; }
.btn-home { color: #64748b; font-size: 0.9rem; text-decoration: none; font-weight: 500; }
.btn-home:hover { color: #334155; }
.register-header { text-align: center; margin-bottom: 2rem; }
.register-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
.register-header h1 { margin: 0; font-size: 1.8rem; color: #1e293b; }
.register-header p { color: #64748b; margin-top: 0.5rem; }

.register-form { display: flex; flex-direction: column; gap: 1.25rem; }

.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-group label { font-weight: 600; font-size: 0.9rem; color: #334155; }
.form-group input {
  padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 1rem; transition: border-color 0.2s;
}
.form-group input:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.15); }

.legal-consents { display: flex; flex-direction: column; gap: 0.75rem; }
.consent-label { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.88rem; color: #475569; }
.consent-label input { margin-top: 0.2rem; }
.consent-label a { color: #667eea; }

.btn-submit {
  padding: 0.85rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700;
  cursor: pointer;
}
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

.error-msg { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; text-align: center; }

.form-links { text-align: center; }
.link-btn { background: none; border: none; color: #667eea; cursor: pointer; font-size: 0.9rem; text-decoration: none; }
.link-btn:hover { text-decoration: underline; }

.verify-step { text-align: center; }
.verify-icon { font-size: 3rem; margin-bottom: 1rem; }
.verify-step h2 { color: #1e293b; margin: 0 0 0.5rem; }
.verify-step p { color: #64748b; margin-bottom: 1.5rem; }

.code-input { text-align: center; font-size: 1.5rem !important; letter-spacing: 0.5rem; font-weight: 700; }
</style>
