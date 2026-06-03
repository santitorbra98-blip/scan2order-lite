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
          <ul>
            <li><strong>NIF:</strong> {{ meta.tax_id }}</li>
            <li><strong>Domicilio:</strong> {{ meta.address }}, {{ meta.postal_code }} {{ meta.city }} ({{ meta.province }}), {{ meta.country }}</li>
            <li><strong>Email de privacidad:</strong> <a :href="'mailto:' + meta.privacy_email">{{ meta.privacy_email }}</a></li>
          </ul>
        </section>
        <section>
          <h2>2. Datos que recopilamos</h2>
          <ul>
            <li>Datos de registro: nombre, email y contraseña (cifrada con bcrypt).</li>
            <li>Datos de restaurante: nombre, dirección, teléfono, imagen y horario.</li>
            <li>Datos de productos: nombre, descripción, precio, imagen, alérgenos.</li>
            <li>Registros de auditoría: acciones realizadas en el panel de administración.</li>
            <li>Datos técnicos de sesión: dirección IP y agente de navegador en el momento de aceptación legal.</li>
          </ul>
        </section>
        <section>
          <h2>3. Finalidad del tratamiento</h2>
          <p>Los datos se tratan para gestionar la cuenta de usuario, permitir la creación y publicación de cartas digitales, garantizar la seguridad del sistema y cumplir las obligaciones legales aplicables.</p>
        </section>
        <section>
          <h2>4. Base legal</h2>
          <ul>
            <li>Ejecución del contrato de servicio (art. 6.1.b RGPD).</li>
            <li>Consentimiento del usuario para tratamientos opcionales como comunicaciones comerciales (art. 6.1.a RGPD).</li>
            <li>Cumplimiento de obligaciones legales (art. 6.1.c RGPD).</li>
          </ul>
        </section>
        <section>
          <h2>5. Plazo de conservación</h2>
          <p>Los datos se conservarán mientras la cuenta permanezca activa y, tras su cancelación, durante los plazos legalmente exigidos (hasta 5 años para obligaciones fiscales y mercantiles en España).</p>
        </section>
        <section>
          <h2>6. Comunicación de datos a terceros</h2>
          <p>Los datos no se ceden a terceros salvo obligación legal. El análisis de uso del servicio se realiza con herramientas propias que no transfieren datos fuera del sistema.</p>
        </section>
        <section>
          <h2>7. Derechos del usuario</h2>
          <p>Puede ejercer sus derechos de acceso, rectificación, supresión, portabilidad, limitación y oposición escribiendo a <a :href="'mailto:' + meta.privacy_email"><strong>{{ meta.privacy_email }}</strong></a>. Debe acompañar copia de documento identificativo.</p>
          <p>Tiene también derecho a presentar una reclamación ante la <strong>Agencia Española de Protección de Datos (AEPD)</strong> en <a href="https://www.aepd.es" target="_blank" rel="noopener noreferrer">www.aepd.es</a>.</p>
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
.loading { text-align: center; padding: 2rem; color: #1e293b; }
.back-link { display: inline-block; margin-bottom: 1.5rem; color: #1e293b; text-decoration: none; font-size: 0.9rem; font-weight: 500; }
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
