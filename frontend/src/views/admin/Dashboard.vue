<template>
  <div class="admin-container">
    <div class="header">
      <h1>Dashboard</h1>
      <p>Gestión de tu restaurante</p>
    </div>

    <div class="dashboard-grid">
      <StatsCard icon="🍽️">
        <p class="stat-label">Restaurantes</p>
        <p class="stat-value">{{ stats.restaurants }}</p>
      </StatsCard>
      <StatsCard icon="📦">
        <p class="stat-label">Productos</p>
        <p class="stat-value">{{ stats.products }}</p>
      </StatsCard>
      <StatsCard icon="👁️">
        <p class="stat-label">Visitas</p>
        <p class="stat-value">{{ stats.totalVisits }}</p>
        <p class="stat-sub">últimos 30 días</p>
      </StatsCard>
      <StatsCard icon="📈">
        <p class="stat-label">Visitantes únicos</p>
        <p class="stat-value">{{ stats.allTimeVisits }}</p>
        <p class="stat-sub">histórico</p>
      </StatsCard>
      <StatsCard v-if="isSuperadmin" icon="👥">
        <p class="stat-label">Usuarios</p>
        <p class="stat-value">{{ stats.users }}</p>
      </StatsCard>
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
import { reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../../services/api'
import { useAuthStore } from '../../stores/auth'
import StatsCard from '../../components/StatsCard.vue'

const auth = useAuthStore()
const router = useRouter()
const isSuperadmin = computed(() => auth.hasRole('superadmin'))

const stats = reactive({ restaurants: 0, products: 0, catalogs: 0, users: 0, totalVisits: 0, allTimeVisits: 0 })

async function fetchStats() {
  try {
    const [restaurantsRes, statsRes, usersRes, visitsRes, allVisitsRes] = await Promise.allSettled([
      api.get('/restaurants'),
      api.get('/restaurants/stats'),
      isSuperadmin.value ? api.get('/users') : Promise.resolve(null),
      api.get('/analytics/my-stats?period=30d'),
      api.get('/analytics/my-stats?period=all'),
    ])

    const restaurantsResult = restaurantsRes.status === 'fulfilled' ? restaurantsRes.value : null
    const list = restaurantsResult?.meta ? restaurantsResult.data : (Array.isArray(restaurantsResult) ? restaurantsResult : [])
    stats.restaurants = restaurantsResult?.meta ? restaurantsResult.meta.total : list.length

    const statsData = statsRes.status === 'fulfilled' ? statsRes.value : []
    const statsList = Array.isArray(statsData) ? statsData : []
    stats.products = statsList.reduce((sum, r) => sum + Number(r?.total_products || 0), 0)
    stats.catalogs = statsList.reduce((sum, r) => sum + Number(r?.total_catalogs || 0), 0)

    if (!isSuperadmin.value && stats.restaurants === 0) {
      router.replace('/admin/onboarding')
      return
    }

    if (isSuperadmin.value) {
      const usersResult = usersRes.status === 'fulfilled' ? usersRes.value : null
      stats.users = usersResult?.meta
        ? usersResult.meta.total
        : (Array.isArray(usersResult) ? usersResult.length : 0)
    }

    if (visitsRes.status === 'fulfilled' && visitsRes.value) {
      stats.totalVisits = visitsRes.value.total_visits ?? 0
    }
    if (allVisitsRes.status === 'fulfilled' && allVisitsRes.value) {
      stats.allTimeVisits = allVisitsRes.value.unique_visits ?? 0
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
.header p { font-size: 1.1rem; color: #1e293b; margin-top: 0.5rem; }

.dashboard-grid {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(min(250px, 100%), 1fr));
  gap: 1.5rem; margin-bottom: 3rem;
}

/* stat-card styles moved to StatsCard.vue */
.stat-label { color: #1e293b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.3rem; }
.stat-value { color: #1e293b; font-size: 2rem; font-weight: 700; margin: 0; }
.stat-sub   { color: #94a3b8; font-size: 0.75rem; margin: 0.2rem 0 0; }

.actions-section {
  background: white; border-radius: 12px; padding: 2rem;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.actions-section h2 { color: #1e293b; font-size: 1.4rem; margin: 0 0 1.5rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; }

.action-buttons { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr)); gap: 1rem; }
.action-btn {
  display: flex; flex-direction: column; align-items: center; gap: 0.75rem; padding: 1.5rem;
  background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none;
  border-radius: 12px; font-weight: 600; font-size: 1rem; transition: transform 0.2s, opacity 0.2s;
}
.action-btn:hover { transform: translateY(-3px); opacity: 0.9; }
.btn-icon { font-size: 2rem; }

@media (max-width: 640px) {
  .admin-container { padding: 1rem; }
  .header { margin-bottom: 1.5rem; }
  .header h1 { font-size: 1.8rem; }
  .dashboard-grid { grid-template-columns: 1fr; gap: 1rem; margin-bottom: 1.5rem; }
  .actions-section { padding: 1.25rem; }
  .action-buttons { grid-template-columns: 1fr; }
}
</style>
