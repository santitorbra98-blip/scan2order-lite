<template>
  <div id="app" class="app-container">
    <nav v-if="auth.isAuthenticated" class="navbar">
      <div class="nav-brand">
        <router-link to="/" class="brand-link">
          <h1>Scan2Order</h1>
        </router-link>
        <span class="role-badge">{{ auth.userRole }}</span>
      </div>
      <ul class="nav-links">
        <li v-if="canAccessAdmin"><router-link to="/admin">Panel</router-link></li>
        <li v-if="canAccessAdmin"><router-link to="/admin/restaurants">Restaurantes</router-link></li>
        <li v-if="canAccessAdmin"><router-link to="/admin/products">Productos</router-link></li>
        <li v-if="isSuperadmin"><router-link to="/admin/users">Usuarios</router-link></li>
        <li class="user-menu">
          <button @click="showUserMenu = !showUserMenu" class="user-btn">
            {{ auth.user?.name }} ▼
          </button>
          <div v-if="showUserMenu" class="dropdown-menu">
            <button @click="logout" class="logout-btn">Cerrar sesión</button>
          </div>
        </li>
      </ul>
    </nav>

    <main class="main-content">
      <router-view />
    </main>

    <LegalFooter />
  </div>
</template>

<script setup>
import { useAuthStore } from './stores/auth'
import LegalFooter from '@/components/legal/LegalFooter.vue'
import { ref, onMounted, watch, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const showUserMenu = ref(false)

const canAccessAdmin = computed(() => auth.hasAnyRole(['admin', 'superadmin']))
const isSuperadmin = computed(() => auth.hasRole('superadmin'))

onMounted(() => {
  // Auth is initialized in main.js before mount — nothing to do here.
})

watch(() => router.currentRoute.value.name, () => {
  showUserMenu.value = false
})

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
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
  background-attachment: fixed;
  min-height: 100vh;
  overflow-x: hidden;
  max-width: 100vw;
}

#app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  overflow-x: hidden;
  max-width: 100vw;
}
</style>

<style scoped>
.app-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.navbar {
  background-color: #2c3e50;
  padding: 1.5rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  flex-shrink: 0;
}

.nav-brand {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.nav-brand h1 {
  color: #fff;
  font-size: 1.8rem;
  font-weight: 700;
}

.brand-link {
  text-decoration: none;
}

.role-badge {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}

.nav-links {
  display: flex;
  list-style: none;
  gap: 1.5rem;
  align-items: center;
}

.nav-links a {
  color: #ecf0f1;
  text-decoration: none;
  font-size: 1rem;
  padding: 0.5rem 0;
  border-bottom: 2px solid transparent;
  transition: all 0.3s ease;
  font-weight: 500;
}

.nav-links a:hover,
.nav-links a.router-link-active {
  color: #667eea;
  border-bottom-color: #667eea;
}

.user-menu {
  position: relative;
  margin-left: 1rem;
}

.user-btn {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 5px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 600;
  transition: opacity 0.3s ease;
}

.user-btn:hover {
  opacity: 0.8;
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  background: rgba(44, 62, 80, 0.92);
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  margin-top: 0.5rem;
  min-width: 180px;
  z-index: 9999;
  overflow: hidden;
  border: 1px solid #d4dce4;
}

.logout-btn {
  width: 100%;
  padding: 0.75rem 1rem;
  background: none;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  cursor: pointer;
  color: #e74c3c;
  font-weight: 600;
  transition: background 0.3s ease;
}

.logout-btn:hover {
  background: #f5f5f5;
}

.main-content {
  flex: 1;
  padding: 2rem;
  background-color: #667eea;
  background-image:
    radial-gradient(circle at 16% 12%, rgba(255, 255, 255, 0.2) 0, rgba(255, 255, 255, 0) 24%),
    radial-gradient(circle at 88% 10%, rgba(255, 255, 255, 0.16) 0, rgba(255, 255, 255, 0) 28%),
    radial-gradient(circle at 76% 84%, rgba(255, 255, 255, 0.12) 0, rgba(255, 255, 255, 0) 32%),
    repeating-linear-gradient(
      -35deg,
      rgba(255, 255, 255, 0.08) 0,
      rgba(255, 255, 255, 0.08) 2px,
      rgba(255, 255, 255, 0) 2px,
      rgba(255, 255, 255, 0) 24px
    ),
    linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  overflow-y: auto;
}

@media (max-width: 768px) {
  .navbar {
    flex-direction: column;
    gap: 1rem;
  }

  .nav-links {
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: center;
    width: 100%;
  }

  .nav-brand h1 {
    font-size: 1.4rem;
  }
}
</style>
