<template>
  <footer class="legal-footer">
    <div class="footer-inner">
      <div class="footer-brand">🍽️ Scan2Order</div>
      <nav class="footer-links">
        <router-link to="/legal/aviso-legal">Aviso Legal</router-link>
        <router-link to="/legal/privacidad">Política de Privacidad</router-link>
        <router-link to="/legal/cookies">Política de Cookies</router-link>
        <router-link to="/legal/terminos">Términos y Condiciones</router-link>
      </nav>
      <p class="footer-copy">© {{ new Date().getFullYear() }} Scan2Order. Todos los derechos reservados.</p>
    </div>
  </footer>
</template>

<style scoped>
.legal-footer {
  background: #0f172a; color: rgba(255,255,255,0.7); padding: 2rem 1.5rem; margin-top: auto;
}
.footer-inner { max-width: 1200px; margin: 0 auto; text-align: center; }
.footer-brand { font-size: 1.2rem; font-weight: 800; color: white; margin-bottom: 1rem; }
.footer-links { display: flex; gap: 1.5rem; justify-content: center; flex-wrap: wrap; margin-bottom: 1rem; }
.footer-links a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.85rem; transition: color 0.2s; }
.footer-links a:hover { color: white; }
.footer-copy { margin: 0; font-size: 0.8rem; opacity: 0.5; }
</style>
