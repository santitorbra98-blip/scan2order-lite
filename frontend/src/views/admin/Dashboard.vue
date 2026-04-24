<template>
  <div class="admin-container">
    <div class="header">
      <h1>Dashboard</h1>
      <p>Gestión de tu restaurante</p>
    </div>

    <div class="dashboard-grid">
      <div class="stat-card">
        <div class="stat-icon">🍽️</div>
        <div class="stat-info">
          <p class="stat-label">Restaurantes</p>
          <p class="stat-value">{{ stats.restaurants }}</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-info">
          <p class="stat-label">Productos</p>
          <p class="stat-value">{{ stats.products }}</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-info">
          <p class="stat-label">Catálogos</p>
          <p class="stat-value">{{ stats.catalogs }}</p>
        </div>
      </div>
      <div v-if="isSuperadmin" class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-info">
          <p class="stat-label">Usuarios</p>
          <p class="stat-value">{{ stats.users }}</p>
        </div>
      </div>
    </div>

    <div class="actions-section">
      <h2>Acciones rápidas</h2>
      <div class="action-buttons">
        <router-link to="/admin/restaurants" class="action-btn">
          <span class="btn-icon">🍽️</span>
          Gestionar locales
        </router-link>
        <router-link to="/admin/products" class="action-btn">
          <span class="btn-icon">📦</span>
          Gestionar productos
        </router-link>
        <router-link v-if="isSuperadmin" to="/admin/users" class="action-btn">
          <span class="btn-icon">👥</span>
          Gestionar usuarios
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../../services/api'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const isSuperadmin = computed(() => auth.hasRole('superadmin'))

const stats = ref({ restaurants: 0, products: 0, catalogs: 0, users: 0 })

async function fetchStats() {
  try {
    const restaurantsResult = await api.get('/restaurants')
    const list = restaurantsResult?.meta ? restaurantsResult.data : (Array.isArray(restaurantsResult) ? restaurantsResult : [])
    stats.value.restaurants = restaurantsResult?.meta ? restaurantsResult.meta.total : list.length

    const statsData = await api.get('/restaurants/stats').catch(() => [])
    const statsList = Array.isArray(statsData) ? statsData : []
    stats.value.products = statsList.reduce((sum, r) => sum + Number(r?.total_products || 0), 0)
    stats.value.catalogs = statsList.reduce((sum, r) => sum + Number(r?.total_catalogs || 0), 0)

    if (!isSuperadmin.value && stats.value.restaurants === 0) {
      router.replace('/admin/onboarding')
      return
    }

    if (auth.hasRole('superadmin')) {
      const usersResult = await api.get('/users').catch(() => null)
      stats.value.users = usersResult?.meta
        ? usersResult.meta.total
        : (Array.isArray(usersResult) ? usersResult.length : 0)
    }
  } catch (err) {
    console.error('Error cargando estadísticas:', err)
  }
}

onMounted(() => fetchStats())
</script>

<style scoped>
.admin-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

.header { text-align: center; margin-bottom: 3rem; }
.header h1 { font-size: 2.5rem; color: #1e293b; margin: 0; }
.header p { font-size: 1.1rem; color: #64748b; margin-top: 0.5rem; }

.dashboard-grid {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem; margin-bottom: 3rem;
}

.stat-card {
  background: white; border-radius: 12px; padding: 1.5rem;
  display: flex; align-items: center; gap: 1.5rem;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06); transition: transform 0.2s;
}
.stat-card:hover { transform: translateY(-3px); }
.stat-icon { font-size: 2.5rem; }
.stat-label { color: #64748b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.3rem; }
.stat-value { color: #1e293b; font-size: 2rem; font-weight: 700; margin: 0; }

.actions-section {
  background: white; border-radius: 12px; padding: 2rem;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.actions-section h2 { color: #1e293b; font-size: 1.4rem; margin: 0 0 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; }

.action-buttons { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
.action-btn {
  display: flex; flex-direction: column; align-items: center; gap: 0.75rem; padding: 1.5rem;
  background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none;
  border-radius: 12px; font-weight: 600; font-size: 1rem; transition: transform 0.2s, opacity 0.2s;
}
.action-btn:hover { transform: translateY(-3px); opacity: 0.9; }
.btn-icon { font-size: 2rem; }
</style>
