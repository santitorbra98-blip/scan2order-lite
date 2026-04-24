<template>
  <div class="legal-view">
    <div class="legal-page">
      <router-link to="/" class="back-link">← Volver al inicio</router-link>
      <h1>Política de Privacidad</h1>
      <div v-if="loading" class="loading">Cargando...</div>
      <div v-else class="legal-content">
        <section>
          <h2>1. Responsable del tratamiento</h2>
          <p><strong>{{ meta.company_name }}</strong> ({{ meta.brand_name }})</p>
          <p>Email: {{ meta.privacy_email }}</p>
        </section>
        <section>
          <h2>2. Datos que recopilamos</h2>
          <ul>
            <li>Datos de registro: nombre, email y contraseña (cifrada).</li>
            <li>Datos de restaurante: nombre, dirección, teléfono, imagen y horario.</li>
            <li>Datos de productos: nombre, descripción, precio, imagen, alérgenos.</li>
            <li>Registros de auditoría: acciones realizadas en el panel de administración.</li>
          </ul>
        </section>
        <section>
          <h2>3. Finalidad del tratamiento</h2>
          <p>Los datos se tratan para gestionar la cuenta de usuario, permitir la creación y publicación de cartas digitales, y garantizar la seguridad del sistema.</p>
        </section>
        <section>
          <h2>4. Base legal</h2>
          <p>El tratamiento se basa en el consentimiento del usuario (art. 6.1.a RGPD) y la ejecución del contrato de servicio (art. 6.1.b RGPD).</p>
        </section>
        <section>
          <h2>5. Derechos del usuario</h2>
          <p>Puede ejercer sus derechos de acceso, rectificación, supresión, portabilidad, limitación y oposición escribiendo a <strong>{{ meta.privacy_email }}</strong>.</p>
        </section>
        <p class="legal-version">Versión {{ meta.version }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useLegalMeta } from '../../composables/useLegalMeta'
const { meta, loading, load } = useLegalMeta()
onMounted(() => load())
</script>

<style scoped>
.legal-view {
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 2rem 1rem;
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
}

.legal-page { width: 100%; max-width: 800px; margin: 0 auto; padding: 3rem 2rem; background: white; border-radius: 16px; }
.legal-page h1 { color: #1e293b; margin-bottom: 2rem; font-size: 2rem; }
.legal-page h2 { color: #334155; font-size: 1.2rem; margin: 1.5rem 0 0.75rem; }
.legal-page p, .legal-page li { color: #475569; line-height: 1.7; }
.legal-page ul { padding-left: 1.5rem; }
.loading { text-align: center; padding: 2rem; color: #64748b; }
.back-link { display: inline-block; margin-bottom: 1.5rem; color: #64748b; text-decoration: none; font-size: 0.9rem; font-weight: 500; }
.back-link:hover { color: #334155; }
.legal-version { margin-top: 2rem; color: #94a3b8; font-size: 0.85rem; }

@media (max-width: 768px) {
  .legal-view {
    padding: 1rem;
  }

  .legal-page {
    padding: 2rem 1.25rem;
  }
}
</style>
