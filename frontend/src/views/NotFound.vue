<template>
  <div class="not-found">
    <div class="nf-icon">🍽️</div>
    <h1>404</h1>
    <p>La página que buscas no existe</p>
    <div class="nf-links">
      <router-link to="/">🏠 Inicio</router-link>
      <router-link to="/login">🔐 Acceder</router-link>
    </div>
  </div>
</template>

<style scoped>
.not-found {
  min-height: 100vh; display: flex; flex-direction: column;
  align-items: center; justify-content: center; text-align: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem;
}
.nf-icon { font-size: 4rem; margin-bottom: 1rem; }
h1 { font-size: 6rem; margin: 0; font-weight: 900; opacity: 0.9; }
p { font-size: 1.2rem; opacity: 0.85; margin: 1rem 0 2rem; }
.nf-links { display: flex; gap: 1rem; }
.nf-links a {
  padding: 0.7rem 1.5rem; background: rgba(255,255,255,0.15); border: 1.5px solid rgba(255,255,255,0.3);
  border-radius: 50px; color: white; text-decoration: none; font-weight: 600; transition: all 0.2s;
}
.nf-links a:hover { background: rgba(255,255,255,0.25); }
</style>
