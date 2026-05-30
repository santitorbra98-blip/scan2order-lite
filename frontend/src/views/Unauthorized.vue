<template>
  <div class="unauthorized">
    <div class="ua-icon">🚫</div>
    <h1>403</h1>
    <p>No tienes permisos para acceder a esta página</p>
    <div class="ua-links">
      <router-link to="/admin">⬅ Volver al panel</router-link>
    </div>
  </div>
</template>

<style scoped>
.unauthorized {
  min-height: 100vh; display: flex; flex-direction: column;
  align-items: center; justify-content: center; text-align: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem;
}
.ua-icon { font-size: 4rem; margin-bottom: 1rem; }
h1 { font-size: 6rem; margin: 0; font-weight: 900; opacity: 0.9; }
p { font-size: 1.2rem; opacity: 0.85; margin: 1rem 0 2rem; }
.ua-links a {
  padding: 0.7rem 1.5rem; background: rgba(255,255,255,0.15); border: 1.5px solid rgba(255,255,255,0.3);
  border-radius: 50px; color: white; text-decoration: none; font-weight: 600; transition: all 0.2s;
}
.ua-links a:hover { background: rgba(255,255,255,0.25); }
</style>
