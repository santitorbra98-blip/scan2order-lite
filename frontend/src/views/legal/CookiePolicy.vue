<template>
  <div class="legal-view">
    <div class="legal-page">
      <router-link to="/" class="back-link">← Volver al inicio</router-link>
      <h1>Política de Cookies</h1>
      <div v-if="loading" class="loading">Cargando...</div>
      <div v-else class="legal-content">
        <section>
          <h2>1. ¿Qué son las cookies?</h2>
          <p>Las cookies son pequeños archivos de texto que se almacenan en su navegador al visitar un sitio web. Se utilizan para recordar preferencias y facilitar la navegación.</p>
        </section>
        <section>
          <h2>2. Cookies que utilizamos</h2>
          <ul>
            <li><strong>Cookies técnicas (necesarias):</strong> cookies de sesión y CSRF para el correcto funcionamiento de la autenticación.</li>
            <li><strong>Cookies de preferencias:</strong> almacenan su consentimiento de cookies.</li>
          </ul>
        </section>
        <section>
          <h2>3. Gestión de cookies</h2>
          <p>Puede configurar su navegador para rechazar cookies o eliminar las existentes. Tenga en cuenta que algunas funcionalidades del sitio podrían verse afectadas.</p>
        </section>
        <section>
          <h2>4. Más información</h2>
          <p>Para cualquier consulta sobre nuestra política de cookies, contacte con <strong>{{ meta.contact_email }}</strong>.</p>
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
