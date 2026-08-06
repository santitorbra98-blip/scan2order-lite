<template>
  <div class="contact-page">
    <div class="contact-shell">
      <section class="contact-intro">
        <router-link to="/" class="back-link">← Inicio</router-link>
        <div class="intro-badge">Solicitud de acceso</div>
        <h1>Cuéntanos quién eres y te damos acceso manualmente</h1>
        <p>
          Hemos eliminado el registro autónomo para mantener el control desde superadmin.
          Si quieres abrir una cuenta, deja tus datos y revisaremos la solicitud.
        </p>

        <ul class="benefits-list">
          <li>Alta revisada por el equipo</li>
          <li>Sin cuentas automáticas ni acceso anónimo</li>
          <li>Contacto directo por email</li>
        </ul>
      </section>

      <section class="contact-card">
        <form class="contact-form" @submit.prevent="submitForm">
          <div class="form-row">
            <div class="form-group">
              <label for="name">Nombre completo</label>
              <input id="name" v-model="form.name" type="text" required maxlength="255" autocomplete="name" placeholder="Tu nombre" />
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input id="email" v-model="form.email" type="email" required maxlength="255" autocomplete="email" placeholder="tu@email.com" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="phone">Teléfono</label>
              <input id="phone" v-model="form.phone" type="text" maxlength="30" autocomplete="tel" placeholder="Opcional" />
            </div>
            <div class="form-group">
              <label for="restaurant_name">Nombre del restaurante</label>
              <input id="restaurant_name" v-model="form.restaurant_name" type="text" maxlength="255" autocomplete="organization" placeholder="Opcional" />
            </div>
          </div>

          <div class="form-group honeypot-field" aria-hidden="true">
            <label for="website">Website</label>
            <input id="website" v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
          </div>

          <div class="form-group">
            <label for="message">Mensaje</label>
            <textarea id="message" v-model="form.message" required minlength="20" maxlength="3000" rows="7" placeholder="Cuéntanos qué necesitas y cómo te gustaría trabajar con Scan2Order"></textarea>
          </div>

          <p class="hint-text">No incluyas contraseñas ni datos sensibles. Te responderemos a la dirección indicada.</p>

          <div v-if="errorMsg" class="feedback feedback-error">{{ errorMsg }}</div>
          <div v-if="successMsg" class="feedback feedback-success">{{ successMsg }}</div>

          <button type="submit" class="submit-btn" :disabled="loading">
            {{ loading ? 'Enviando...' : 'Enviar solicitud' }}
          </button>
        </form>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import apiClient from '../services/api'

const loading = ref(false)
const errorMsg = ref('')
const successMsg = ref('')

const form = ref({
  name: '',
  email: '',
  phone: '',
  restaurant_name: '',
  website: '',
  message: '',
})

async function submitForm() {
  loading.value = true
  errorMsg.value = ''
  successMsg.value = ''

  try {
    await apiClient.getCsrfCookie()
    const response = await apiClient.post('/contact', form.value)
    successMsg.value = response?.message || 'Gracias. Hemos recibido tu solicitud y te responderemos por email.'
    form.value = { name: '', email: '', phone: '', restaurant_name: '', website: '', message: '' }
  } catch (error) {
    errorMsg.value = error?.message || 'No se pudo enviar la solicitud'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.contact-page {
  min-height: 100vh;
  padding: 2rem;
  background:
    radial-gradient(circle at top left, rgba(255, 255, 255, 0.15), transparent 28%),
    radial-gradient(circle at 80% 20%, rgba(34, 197, 94, 0.14), transparent 26%),
    linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #0f766e 100%);
  color: #e2e8f0;
}

.contact-shell {
  max-width: 1120px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1.05fr 0.95fr;
  gap: 1.5rem;
  align-items: stretch;
}

.contact-intro,
.contact-card {
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 28px;
  backdrop-filter: blur(18px);
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.32);
}

.contact-intro {
  padding: 2rem;
  background: rgba(15, 23, 42, 0.68);
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.back-link {
  color: #cbd5e1;
  text-decoration: none;
  width: fit-content;
  margin-bottom: 1.2rem;
}

.intro-badge {
  width: fit-content;
  padding: 0.45rem 0.8rem;
  border-radius: 999px;
  background: rgba(34, 197, 94, 0.14);
  color: #86efac;
  font-size: 0.85rem;
  font-weight: 700;
  margin-bottom: 1rem;
}

.contact-intro h1 {
  margin: 0;
  font-size: clamp(2rem, 4vw, 3.4rem);
  line-height: 1.05;
  max-width: 12ch;
}

.contact-intro p {
  margin: 1rem 0 0;
  max-width: 54ch;
  font-size: 1.05rem;
  color: #cbd5e1;
  line-height: 1.7;
}

.benefits-list {
  margin: 1.5rem 0 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.75rem;
}

.benefits-list li {
  padding: 0.8rem 1rem;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.06);
  color: #f8fafc;
}

.contact-card {
  background: rgba(255, 255, 255, 0.96);
  color: #0f172a;
  padding: 1.75rem;
}

.contact-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.form-group label {
  font-size: 0.9rem;
  font-weight: 700;
  color: #334155;
}

.form-group input,
.form-group textarea {
  width: 100%;
  border: 1.5px solid #cbd5e1;
  border-radius: 14px;
  padding: 0.9rem 1rem;
  font: inherit;
  color: #0f172a;
  background: #fff;
  transition: border-color .2s, box-shadow .2s;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #0f766e;
  box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.14);
}

.form-group textarea {
  resize: vertical;
  min-height: 180px;
}

.honeypot-field {
  position: absolute;
  left: -9999px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}

.hint-text {
  margin: 0;
  color: #64748b;
  font-size: 0.92rem;
}

.feedback {
  padding: 0.85rem 1rem;
  border-radius: 12px;
  font-weight: 600;
}

.feedback-error {
  background: #fef2f2;
  color: #b91c1c;
}

.feedback-success {
  background: #ecfdf5;
  color: #047857;
}

.submit-btn {
  border: none;
  border-radius: 14px;
  padding: 0.95rem 1.1rem;
  background: linear-gradient(135deg, #0f766e, #22c55e);
  color: white;
  font-weight: 800;
  font-size: 1rem;
  cursor: pointer;
}

.submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

@media (max-width: 900px) {
  .contact-shell {
    grid-template-columns: 1fr;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .contact-intro h1 {
    max-width: none;
  }
}
</style>