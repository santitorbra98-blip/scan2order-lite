<template>
  <div class="home-container">
    <nav v-if="!auth.isAuthenticated" class="home-nav">
      <div class="home-nav-inner">
        <router-link to="/" class="home-nav-brand">
          <span class="nav-logo">🍽️</span>
          <span class="nav-name">Scan2Order</span>
        </router-link>
        <div class="home-nav-actions">
          <router-link to="/login" class="btn-nav-login">Acceder</router-link>
          <router-link to="/contacto" class="btn-nav-register">Solicitar acceso</router-link>
        </div>
        <button class="nav-burger" @click="mobileOpen = !mobileOpen" aria-label="Menú">
          <span></span><span></span><span></span>
        </button>
      </div>
      <div class="home-nav-mobile" :class="{ open: mobileOpen }">
        <router-link to="/login" @click="mobileOpen = false">Acceder</router-link>
        <router-link to="/contacto" @click="mobileOpen = false">Solicitar acceso</router-link>
      </div>
    </nav>

    <div class="hero-section">
      <div class="hero-grid">
        <div class="hero-column brand-column">
          <div class="hero-icon">🍽️</div>
          <h1 class="hero-title">Scan2Order</h1>
          <p class="hero-subtitle">Digitaliza tu carta y compártela con un QR</p>
          <div class="hero-features">
            <span class="feature-pill">⚡ Rápido</span>
            <span class="feature-pill">🔒 Seguro</span>
            <span class="feature-pill">📱 Fácil</span>
          </div>
        </div>
        <div class="hero-column auth-column">
          <div v-if="!auth.isAuthenticated" class="auth-hero-card">
            <div class="auth-icon">🔐</div>
            <h2>¿Tienes un restaurante?</h2>
            <p>Inicia sesión si ya tienes una cuenta o solicita acceso desde el formulario</p>
            <div class="auth-buttons">
              <router-link to="/login" class="btn-auth btn-login">Iniciar sesión</router-link>
              <router-link to="/contacto" class="btn-auth btn-register">Solicitar acceso</router-link>
            </div>
          </div>
          <div v-else class="welcome-hero-card">
            <div class="welcome-avatar">{{ auth.user?.name?.charAt(0) }}</div>
            <h2>¡Hola, {{ auth.user?.name }}! 👋</h2>
            <router-link to="/admin" class="btn-auth btn-login">Ir al panel</router-link>
          </div>
        </div>
      </div>
      <div class="hero-wave">
        <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
          <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z"></path>
        </svg>
      </div>
    </div>

    <div class="main-content">
      <div class="content-wrapper">

        <!-- Top 5 most visited -->
        <div class="top-section">
          <div class="top-section-header">
            <div>
              <h2 class="section-title">🏆 Locales más visitados</h2>
              <p class="section-subtitle">Los 5 restaurantes con más visitas</p>
            </div>
            <div class="top-period-tabs">
              <button
                v-for="opt in topPeriodOptions"
                :key="opt.value"
                class="top-period-tab"
                :class="{ active: topPeriod === opt.value }"
                @click="setTopPeriod(opt.value)"
              >{{ opt.label }}</button>
            </div>
          </div>

          <div v-if="topLoading" class="top-loading">
            <div class="loader-spinner"></div>
          </div>
          <div v-else-if="topError" class="top-error">No se pudo cargar el ranking.</div>
          <div v-else-if="topRestaurants.length === 0" class="top-empty">
            Aún no hay datos de visitas. ¡Sé el primero en explorar!
          </div>
          <div v-else class="top-list">
            <div
              v-for="(item, index) in topRestaurants"
              :key="item.restaurant_id"
              class="top-item"
            >
              <div class="top-rank">
                <span v-if="index < 3" class="top-medal">{{ ['🥇','🥈','🥉'][index] }}</span>
                <span v-else class="top-num">{{ index + 1 }}</span>
              </div>
              <div class="top-info">
                <span class="top-name">{{ item.restaurant_name }}</span>
              </div>
              <div class="top-stats">
                <span class="top-stat" title="Visitas totales">👁 {{ item.total_visits.toLocaleString('es-ES') }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="section-header">
          <h2 class="section-title">Restaurantes</h2>
          <p class="section-subtitle">Consulta la carta de estos establecimientos</p>
        </div>

        <div v-if="!isLoading && !error && restaurants.length > 0" class="toolbar-row">
          <div class="search-box">
            <span class="search-box-icon">🔎</span>
            <input v-model="searchTerm" type="text" class="search-box-input" placeholder="Buscar restaurantes..." />
            <button v-if="searchTerm" type="button" class="search-box-clear" @click="searchTerm = ''">✕</button>
          </div>
          <button
            type="button"
            class="open-filter-btn"
            :class="{ active: showOnlyOpen }"
            @click="showOnlyOpen = !showOnlyOpen"
          >
            <span class="open-dot"></span>
            Solo abiertos
          </button>
          <div class="results-summary">
            {{ filteredRestaurants.length }} restaurante{{ filteredRestaurants.length === 1 ? '' : 's' }}
          </div>
        </div>

        <div v-if="isLoading" class="loading-state">
          <div class="loader-spinner"></div>
          <p>Buscando restaurantes...</p>
        </div>

        <div v-else-if="error" class="error-state">
          <div class="error-icon">⚠️</div>
          <h3>Ups, algo salió mal</h3>
          <p>{{ error }}</p>
          <button @click="fetchRestaurants" class="btn-retry">Intentar de nuevo</button>
        </div>

        <div v-else-if="restaurants.length === 0" class="empty-state">
          <div class="empty-icon">🍴</div>
          <h3>No hay restaurantes disponibles</h3>
          <p>Vuelve pronto para descubrir nuevas opciones</p>
        </div>

        <div v-else-if="filteredRestaurants.length === 0" class="empty-state">
          <div class="empty-icon">🔎</div>
          <h3>Sin resultados</h3>
          <p>No encontramos restaurantes para "{{ searchTerm }}"</p>
        </div>

        <div v-else class="restaurants-grid">
          <div
            v-for="restaurant in paginatedRestaurants"
            :key="restaurant.id"
            class="restaurant-card"
            @click="viewRestaurant(restaurant)"
          >
            <div class="card-image">
              <img v-if="restaurant.image" :src="restaurant.image" :alt="restaurant.name" />
              <div v-else class="card-image-placeholder">🏪</div>
            </div>
            <div class="card-body">
              <div class="card-name-row">
                <h3>{{ restaurant.name }}</h3>
                <span
                  v-if="isOpenNow(restaurant) !== null"
                  class="open-badge"
                  :class="isOpenNow(restaurant) ? 'badge-open' : 'badge-closed'"
                >{{ isOpenNow(restaurant) ? '● Abierto' : '● Cerrado' }}</span>
              </div>
              <p v-if="restaurant.address" class="detail-item">📍 {{ restaurant.address }}</p>
              <p v-if="restaurant.phone" class="detail-item">📞 {{ restaurant.phone }}</p>
            </div>
            <div class="card-footer">
              <span>Ver menú</span>
              <span>→</span>
            </div>
          </div>
        </div>

        <div v-if="filteredRestaurants.length > pageSize" class="pagination-bar">
          <button :disabled="currentPage === 1" @click="currentPage--">← Anterior</button>
          <span>{{ currentPage }} / {{ totalPages }}</span>
          <button :disabled="currentPage === totalPages" @click="currentPage++">Siguiente →</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { analyticsService } from '../services/analyticsService'

const router = useRouter()
const auth = useAuthStore()
const restaurants = ref([])
const isLoading = ref(false)
const error = ref(null)
const searchTerm = ref('')
const showOnlyOpen = ref(false)
const currentPage = ref(1)
const pageSize = 9
const mobileOpen = ref(false)

const DAYS_MAP = { 0: 'sunday', 1: 'monday', 2: 'tuesday', 3: 'wednesday', 4: 'thursday', 5: 'friday', 6: 'saturday' }

function isOpenNow(restaurant) {
  const schedule = restaurant?.schedule
  if (!schedule || typeof schedule !== 'object') return null
  const now = new Date()
  const dayKey = DAYS_MAP[now.getDay()]
  const day = schedule[dayKey]
  if (!day?.enabled) return false
  const [oh, om] = String(day.open || '00:00').split(':').map(Number)
  const [ch, cm] = String(day.close || '00:00').split(':').map(Number)
  const cur = now.getHours() * 60 + now.getMinutes()
  return cur >= oh * 60 + om && cur < ch * 60 + cm
}

const filteredRestaurants = computed(() => {
  const term = searchTerm.value.trim().toLowerCase()
  let list = restaurants.value
  if (term) {
    list = list.filter((r) =>
      [r.name, r.address, r.phone].some((v) => String(v || '').toLowerCase().includes(term))
    )
  }
  if (showOnlyOpen.value) {
    list = list.filter((r) => isOpenNow(r) === true)
  }
  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRestaurants.value.length / pageSize)))
const paginatedRestaurants = computed(() => {
  const list = filteredRestaurants.value
  if (!Array.isArray(list)) return []
  const start = (currentPage.value - 1) * pageSize
  return list.slice(start, start + pageSize)
})

watch([searchTerm, showOnlyOpen], () => { currentPage.value = 1 })

async function fetchRestaurants() {
  isLoading.value = true
  error.value = null
  try {
    // credentials: 'omit' ensures no session/auth cookies are sent so the
    // backend always returns the PUBLIC active-restaurants list, not the
    // admin's personal list.  cache: 'no-store' prevents the browser from
    // serving a stale response.
    const res = await fetch('/api/restaurants', {
      headers: { Accept: 'application/json' },
      credentials: 'omit',
      cache: 'no-store'
    })
    if (!res.ok) throw new Error('No se pudieron cargar los restaurantes')
    const json = await res.json()
    restaurants.value = Array.isArray(json) ? json : (json?.data ?? [])
  } catch (err) {
    error.value = err.message
  } finally {
    isLoading.value = false
  }
}

function viewRestaurant(restaurant) {
  router.push({ name: 'RestaurantMenu', params: { id: restaurant.id } })
}

onMounted(() => {
  fetchRestaurants()
  fetchTopRestaurants()
})

// ─── Top 5 most visited ──────────────────────────────────────────────────────
const topRestaurants = ref([])
const topLoading = ref(false)
const topError = ref(false)
const topPeriod = ref('7d')

const topPeriodOptions = [
  { value: '7d',  label: '7 días' },
  { value: '30d', label: '30 días' },
  { value: 'all', label: 'Total' },
]

async function fetchTopRestaurants() {
  topLoading.value = true
  topError.value = false
  try {
    const data = await analyticsService.getTopRestaurants(topPeriod.value)
    topRestaurants.value = Array.isArray(data) ? data : []
  } catch {
    topError.value = true
  } finally {
    topLoading.value = false
  }
}

function setTopPeriod(period) {
  topPeriod.value = period
  fetchTopRestaurants()
}
</script>

<style scoped>
* { box-sizing: border-box; }

.home-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  width: 100vw;
  max-width: none;
  margin-left: calc(50% - 50vw);
  margin-right: calc(50% - 50vw);
}

.home-nav {
  position: sticky; top: 0; z-index: 100;
  background: rgba(15, 23, 42, 0.92);
  backdrop-filter: blur(14px);
  border-bottom: 1px solid rgba(255,255,255,0.08);
}

.home-nav-inner {
  max-width: 1200px; margin: 0 auto; padding: 0.85rem 1.5rem;
  display: flex; align-items: center; gap: 1rem;
}

.home-nav-brand {
  display: flex; align-items: center; gap: 0.5rem; text-decoration: none;
}
.nav-logo { font-size: 1.4rem; }
.nav-name { font-size: 1.1rem; font-weight: 800; color: white; }

.home-nav-actions { display: flex; gap: 0.6rem; margin-left: auto; }

.btn-nav-login {
  padding: 0.5rem 1rem; border-radius: 50px;
  border: 1.5px solid rgba(255,255,255,0.3); color: rgba(255,255,255,0.9);
  font-weight: 600; font-size: 0.88rem; text-decoration: none;
}
.btn-nav-login:hover { border-color: white; color: white; }

.btn-nav-register {
  padding: 0.5rem 1rem; border-radius: 50px; background: white;
  color: #667eea; font-weight: 700; font-size: 0.88rem; text-decoration: none;
}

.nav-burger { display: none; flex-direction: column; gap: 5px; background: none; border: none; cursor: pointer; }
.nav-burger span { display: block; width: 22px; height: 2px; background: white; border-radius: 2px; }

.home-nav-mobile { display: none; flex-direction: column; background: rgba(15,23,42,0.97); max-height: 0; overflow: hidden; transition: max-height 0.3s; }
.home-nav-mobile.open { max-height: 200px; }
.home-nav-mobile a { padding: 0.8rem 1.5rem; color: white; text-decoration: none; border-top: 1px solid rgba(255,255,255,0.06); }

@media (max-width: 768px) {
  .home-nav-actions { display: none; }
  .nav-burger { display: flex; }
  .home-nav-mobile { display: flex; }
}

.hero-section { padding: 3rem 1.5rem 0; position: relative; }
.hero-grid { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; }
@media (max-width: 768px) { .hero-grid { grid-template-columns: 1fr; text-align: center; } }

.hero-icon { font-size: 3rem; margin-bottom: 1rem; }
.hero-title { color: white; font-size: 3rem; font-weight: 900; margin: 0; }
.hero-subtitle { color: rgba(255,255,255,0.85); font-size: 1.2rem; margin: 1rem 0; }
.hero-features { display: flex; gap: 0.75rem; flex-wrap: wrap; }
@media (max-width: 768px) { .hero-features { justify-content: center; } }
.feature-pill { background: rgba(255,255,255,0.15); color: white; padding: 0.4rem 1rem; border-radius: 50px; font-size: 0.9rem; }

.auth-hero-card, .welcome-hero-card {
  background: rgba(255,255,255,0.12); backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,0.2); border-radius: 20px; padding: 2.5rem; text-align: center; color: white;
}
.auth-icon { font-size: 2.5rem; margin-bottom: 1rem; }
.auth-buttons { display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; }
.btn-auth { padding: 0.7rem 1.5rem; border-radius: 50px; font-weight: 700; text-decoration: none; font-size: 0.95rem; }
.btn-login { background: white; color: #667eea; }
.btn-register { border: 2px solid white; color: white; }

.welcome-avatar {
  width: 60px; height: 60px; border-radius: 50%;
  background: linear-gradient(135deg, #667eea, #764ba2); color: white;
  font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;
}

.hero-wave { margin-top: 2rem; }
.hero-wave svg { display: block; width: 100%; height: 60px; }
.hero-wave path { fill: #f8fafc; }

.main-content { background: #f8fafc; padding: 3rem 1.5rem; }
.content-wrapper { max-width: 1200px; margin: 0 auto; }

/* ─── Top 5 section ─────────────────────────────────────────────────────── */
.top-section {
  background: white; border-radius: 16px; padding: 1.75rem 2rem;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 2.5rem;
}
.top-section-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;
}
.top-section-header .section-title { font-size: 1.4rem; margin: 0; }
.top-section-header .section-subtitle { margin: 0.25rem 0 0; font-size: 0.9rem; }
.top-period-tabs { display: flex; gap: 0.4rem; flex-shrink: 0; }
.top-period-tab {
  padding: 0.4rem 0.9rem; border: 1.5px solid #e2e8f0; border-radius: 50px;
  background: white; color: #1e293b; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
}
.top-period-tab:hover { border-color: #667eea; color: #667eea; }
.top-period-tab.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-color: transparent; }
.top-loading { display: flex; justify-content: center; padding: 1.5rem; }
.top-error, .top-empty { text-align: center; padding: 1.5rem; color: #94a3b8; font-size: 0.9rem; }
.top-list { display: flex; flex-direction: column; gap: 0.6rem; }
.top-item {
  display: flex; align-items: center; gap: 1rem; padding: 0.7rem 0.9rem;
  border: 1px solid #f1f5f9; border-radius: 10px; transition: background 0.15s;
}
.top-item:hover { background: #f8fafc; }
.top-rank { width: 36px; text-align: center; flex-shrink: 0; }
.top-medal { font-size: 1.3rem; }
.top-num { font-size: 1rem; font-weight: 700; color: #94a3b8; }
.top-info { flex: 1; min-width: 0; }
.top-name { font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
.top-stats { display: flex; gap: 0.75rem; flex-shrink: 0; }
.top-stat { font-size: 0.85rem; color: #475569; font-weight: 600; white-space: nowrap; }
/* ────────────────────────────────────────────────────────────────────────── */

.section-header { text-align: center; margin-bottom: 2rem; }
.section-title { font-size: 1.8rem; color: #1e293b; margin: 0; }
.section-subtitle { color: #1e293b; margin-top: 0.5rem; }

.toolbar-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
.search-box { flex: 1; min-width: 250px; display: flex; align-items: center; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0 1rem; }
.search-box-icon { font-size: 1.1rem; margin-right: 0.5rem; }
.search-box-input { flex: 1; border: none; outline: none; padding: 0.75rem 0; font-size: 0.95rem; background: transparent; }
.search-box-clear { background: none; border: none; cursor: pointer; font-size: 1rem; color: #94a3b8; }
.results-summary { color: #1e293b; font-size: 0.9rem; white-space: nowrap; }

.open-filter-btn {
  display: flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1rem;
  border: 1.5px solid #e2e8f0; border-radius: 10px; background: white;
  cursor: pointer; font-size: 0.9rem; font-weight: 600; color: #475569;
  white-space: nowrap; transition: all 0.2s;
}
.open-filter-btn:hover { border-color: #22c55e; color: #166534; }
.open-filter-btn.active { background: #dcfce7; border-color: #22c55e; color: #166534; }
.open-dot { width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; transition: background 0.2s; }
.open-filter-btn.active .open-dot { background: #22c55e; }

.card-name-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.25rem; }
.card-name-row h3 { margin: 0; font-size: 1.15rem; color: #1e293b; flex: 1; }
.open-badge { font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 50px; white-space: nowrap; flex-shrink: 0; }
.badge-open { background: #dcfce7; color: #166534; }
.badge-closed { background: #fee2e2; color: #dc2626; }

.loading-state, .error-state, .empty-state { text-align: center; padding: 4rem 1rem; }
.loader-spinner { width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #667eea; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-icon, .empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.btn-retry { margin-top: 1rem; padding: 0.6rem 1.5rem; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }

.restaurants-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr)); gap: 1.5rem; }
@media (max-width: 640px) { .restaurants-grid { grid-template-columns: 1fr; } }

.restaurant-card {
  background: white; border-radius: 16px; overflow: hidden; cursor: pointer;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06); transition: transform 0.2s, box-shadow 0.2s;
}
.restaurant-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }

.card-image { height: 200px; overflow: hidden; background: #f1f5f9; }
.card-image img { display: block; width: 100%; height: 100%; object-fit: cover; }
.card-image-placeholder { height: 100%; display: flex; align-items: center; justify-content: center; font-size: 4rem; }
.card-body { padding: 1.25rem; }
.card-body h3 { margin: 0 0 0.5rem; font-size: 1.2rem; color: #1e293b; }
.detail-item { margin: 0.25rem 0; color: #1e293b; font-size: 0.9rem; }
.card-footer { display: flex; justify-content: space-between; padding: 1rem 1.25rem; border-top: 1px solid #f1f5f9; color: #667eea; font-weight: 600; }

.pagination-bar { display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 2rem; }
.pagination-bar button { padding: 0.5rem 1rem; background: white; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-weight: 600; }
.pagination-bar button:disabled { opacity: 0.5; cursor: not-allowed; }
.pagination-bar span { color: #1e293b; }
</style>
