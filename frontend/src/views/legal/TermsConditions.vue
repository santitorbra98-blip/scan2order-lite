<template>
  <div class="legal-view">
    <div class="legal-page">
      <router-link to="/" class="back-link">← Volver al inicio</router-link>
      <h1>Términos y Condiciones</h1>
      <div v-if="loading" class="loading">Cargando...</div>
      <div v-else class="legal-content">
        <section>
          <h2>1. Aceptación</h2>
          <p>Al registrarse y utilizar {{ meta.brand_name }}, usted acepta estos términos y condiciones en su totalidad.</p>
        </section>
        <section>
          <h2>2. Descripción del servicio</h2>
          <p>{{ meta.brand_name }} es una plataforma que permite a los administradores de restaurantes crear y gestionar cartas digitales accesibles mediante códigos QR.</p>
        </section>
        <section>
          <h2>3. Obligaciones del usuario</h2>
          <ul>
            <li>Proporcionar información veraz y actualizada.</li>
            <li>Mantener la confidencialidad de sus credenciales de acceso.</li>
            <li>No utilizar el servicio con fines ilícitos o que vulneren derechos de terceros.</li>
            <li>Asegurarse de que la información de alérgenos de los productos es correcta y actualizada.</li>
          </ul>
        </section>
        <section>
          <h2>4. Propiedad del contenido</h2>
          <p>El usuario es responsable de los contenidos que publique (textos, imágenes de productos, etc.) y garantiza que dispone de los derechos necesarios para su publicación.</p>
        </section>
        <section>
          <h2>5. Limitación de responsabilidad</h2>
          <p>{{ meta.brand_name }} no se responsabiliza de la exactitud de la información proporcionada por los restaurantes ni de posibles errores en la declaración de alérgenos.</p>
        </section>
        <section>
          <h2>6. Modificaciones</h2>
          <p>Nos reservamos el derecho de modificar estos términos. Los cambios se comunicarán a través de la plataforma y entrarán en vigor desde su publicación.</p>
        </section>
        <section>
          <h2>7. Contacto</h2>
          <p>Para cualquier consulta: <strong>{{ meta.contact_email }}</strong></p>
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
