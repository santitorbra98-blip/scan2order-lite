<template>
  <div class="onboarding-wrap">
    <div class="onboarding-card">
      <div class="onboarding-header">
        <div class="onboarding-icon">🏪</div>
        <h1>¡Bienvenido a Scan2Order!</h1>
        <p>Para empezar, cuéntanos sobre tu restaurante. Podrás editar estos datos más adelante.</p>
      </div>

      <form @submit.prevent="handleSubmit" class="onboarding-form">
        <div class="form-group">
          <label for="ob-name">Nombre del restaurante <span class="required">*</span></label>
          <input
            id="ob-name"
            v-model="form.name"
            type="text"
            required
            placeholder="Ej: La Taberna de Juan"
            autofocus
          />
        </div>

        <div class="form-group">
          <label for="ob-address">Dirección</label>
          <input id="ob-address" v-model="form.address" type="text" placeholder="Ej: Calle Mayor 12, Madrid" />
        </div>

        <div class="form-group">
          <label for="ob-phone">Teléfono</label>
          <input id="ob-phone" v-model="form.phone" type="text" placeholder="Ej: +34 600 000 000" />
        </div>

        <div v-if="error" class="error-banner">{{ error }}</div>

        <div class="form-actions">
          <button type="submit" class="btn-primary" :disabled="isSaving">
            {{ isSaving ? 'Creando...' : 'Crear mi restaurante' }}
          </button>
          <router-link to="/admin" class="btn-skip">Configurar más tarde</router-link>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../../services/api'

const router = useRouter()
const isSaving = ref(false)
const error = ref(null)
const form = ref({ name: '', address: '', phone: '' })

async function handleSubmit() {
  isSaving.value = true
  error.value = null
  try {
    const formData = new FormData()
    formData.append('name', form.value.name)
    formData.append('address', form.value.address || '')
    formData.append('phone', form.value.phone || '')
    formData.append('active', '1')
    await api.upload('/restaurants', formData)
    router.push('/admin')
  } catch (err) {
    error.value = err?.data?.message || err?.message || 'Error al crear el restaurante'
  } finally {
    isSaving.value = false
  }
}
</script>

<style scoped>
.onboarding-wrap {
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
  padding: 2rem; background: linear-gradient(135deg, #667eea22 0%, #764ba222 100%);
}
.onboarding-card {
  background: white; border-radius: 20px; padding: 3rem 2.5rem;
  width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,0.12);
}
.onboarding-header { text-align: center; margin-bottom: 2.5rem; }
.onboarding-icon { font-size: 3.5rem; margin-bottom: 1rem; }
.onboarding-header h1 { font-size: 1.8rem; color: #1e293b; margin: 0 0 0.75rem; }
.onboarding-header p { color: #64748b; font-size: 0.95rem; line-height: 1.5; margin: 0; }
.onboarding-form { display: flex; flex-direction: column; gap: 1.25rem; }
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-group label { font-weight: 600; font-size: 0.9rem; color: #334155; }
.required { color: #dc2626; }
.form-group input {
  padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px;
  font-size: 1rem; transition: border-color 0.2s;
}
.form-group input:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.15); }
.error-banner { background: #fef2f2; color: #dc2626; border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.9rem; font-weight: 500; }
.form-actions { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.5rem; }
.btn-primary {
  width: 100%; padding: 0.9rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700;
  cursor: pointer; transition: opacity 0.2s;
}
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-skip { text-align: center; color: #94a3b8; font-size: 0.9rem; text-decoration: none; padding: 0.5rem; }
.btn-skip:hover { color: #64748b; }
</style>
