<template>
  <div class="menu-container">
    <div class="header">
      <div v-if="restaurant" class="restaurant-header">
        <div class="restaurant-title-row">
          <h1>{{ restaurant.name }}</h1>
          <span v-if="isOpen !== null" :class="['open-badge', isOpen ? 'badge-open' : 'badge-closed']">
            {{ isOpen ? '● Abierto' : '● Cerrado' }}
          </span>
        </div>
        <p v-if="todayHours" class="today-hours">🕐 {{ todayHours }}</p>
        <p v-if="restaurant.address" class="restaurant-info">📍 {{ restaurant.address }}</p>
        <p v-if="restaurant.phone" class="restaurant-info">📞 {{ restaurant.phone }}</p>
      </div>
      <div v-else><h1>Menú</h1></div>
    </div>

    <div class="menu-content">
      <!-- Filters -->
      <div class="filters">
        <div class="search-wrap">
          <span class="search-icon">🔎</span>
          <input v-model="searchText" type="text" placeholder="Buscar productos..." class="search-input" />
          <button v-if="searchText" type="button" class="btn-clear" @click="searchText = ''">✕</button>
        </div>
        <div class="filter-toggles">
          <label class="toggle-label" :class="{ active: showAllergens }">
            <input v-model="showAllergens" type="checkbox" />
            <span>Mostrar alérgenos</span>
          </label>
          <button v-if="availableDietTypes.length > 0" class="diet-btn" :class="{ active: showDietFilter || activeDiet }" @click="showDietFilter = !showDietFilter">
            🥗 Tipo de alimento
          </button>
        </div>
        <div v-if="showDietFilter" class="diet-pills">
          <button class="diet-pill" :class="{ active: !activeDiet }" @click="activeDiet = null">🍽️ Todos</button>
          <button v-for="d in availableDietTypes" :key="d.code" class="diet-pill" :class="{ active: activeDiet === d.code }" @click="activeDiet = activeDiet === d.code ? null : d.code">
            {{ d.symbol }} {{ d.label }}
          </button>
        </div>
      </div>

      <div v-if="isLoading" class="loading">Cargando menú...</div>
      <div v-else-if="error" class="error-state">{{ error }}</div>

      <!-- Menu layout -->
      <div v-else-if="allSections.length > 0" class="menu-layout">
        <nav class="sections-nav">
          <h3>📋 Secciones</h3>
          <button v-for="s in allSections" :key="s.id" @click="selectSection(s)" :class="['nav-btn', { active: selectedSection?.id === s.id }]">
            {{ s.name }}
          </button>
        </nav>

        <div class="products-area">
          <div v-if="selectedSection" class="section-title-row">
            <h2>{{ selectedSection.name }}</h2>
            <p v-if="selectedSection.catalog" class="catalog-subtitle">{{ selectedSection.catalog.name }}</p>
          </div>

          <div v-if="filteredProducts.length > 0" class="products-grid">
            <div v-for="product in filteredProducts" :key="product.id" class="product-card" :class="{ compact: !product.image }" @click="toggleExpand(product.id, !!product.description)">
              <div v-if="product.image && product.show_image !== false" class="product-image">
                <img :src="`/storage/${product.image}`" :alt="product.name" />
              </div>
              <div class="product-info">
                <div class="product-title-row">
                  <h3>{{ product.name }}</h3>
                  <span v-if="product.is_new" class="badge-new">NEW</span>
                </div>
                <p v-if="product.description" :class="['description', { expanded: expandedIds.has(product.id) }]">
                  {{ product.description }}
                </p>
                <div v-if="showAllergens && product.allergens?.length" class="allergen-chips">
                  <span v-for="code in product.allergens" :key="code" class="allergen-chip" :title="getAllergenMeta(code).label">
                    {{ getAllergenMeta(code).symbol }} {{ getAllergenMeta(code).label }}
                  </span>
                </div>
                <div class="product-footer">
                  <span class="price">{{ Number(product.price).toFixed(2) }} €</span>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="no-products">
            <div class="empty-icon">🍽️</div>
            <p>No hay productos disponibles en esta sección</p>
          </div>
        </div>
      </div>

      <div v-else-if="!isLoading && !error" class="no-products">
        No hay menús disponibles para este restaurante
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { getAllergenMeta } from '../../constants/allergens'
import { DIET_TYPE_OPTIONS } from '../../constants/dietTypes'

const route = useRoute()
const isLoading = ref(false)
const error = ref(null)
const catalogs = ref([])
const restaurant = ref(null)
const searchText = ref('')
const showAllergens = ref(false)
const activeDiet = ref(null)
const showDietFilter = ref(false)
const selectedSection = ref(null)
const expandedIds = ref(new Set())

const DAYS_MAP = { 0: 'sunday', 1: 'monday', 2: 'tuesday', 3: 'wednesday', 4: 'thursday', 5: 'friday', 6: 'saturday' }

const isOpen = computed(() => {
  const s = restaurant.value?.schedule
  if (!s || !restaurant.value?.active) return null
  const now = new Date()
  const dayKey = DAYS_MAP[now.getDay()]
  const day = s[dayKey]
  if (!day?.enabled) return false
  const time = now.toTimeString().slice(0, 5)
  return time >= day.open && time <= day.close
})

const todayHours = computed(() => {
  const s = restaurant.value?.schedule
  if (!s) return null
  const dayKey = DAYS_MAP[new Date().getDay()]
  const day = s[dayKey]
  if (!day?.enabled) return 'Hoy: Cerrado'
  return `Hoy: ${day.open} – ${day.close}`
})

const availableDietTypes = computed(() => {
  const codes = new Set()
  catalogs.value.forEach(c => (c.sections || []).forEach(s => (s.products || []).forEach(p => {
    if (Array.isArray(p.diet_tags)) p.diet_tags.forEach(d => codes.add(d))
  })))
  return DIET_TYPE_OPTIONS.filter(d => codes.has(d.code))
})

const allSections = computed(() => {
  const sections = []
  catalogs.value.forEach(c => {
    (c.sections || []).forEach(s => {
      sections.push({ ...s, catalog: { id: c.id, name: c.name } })
    })
  })
  if (sections.length > 0 && !selectedSection.value) selectedSection.value = sections[0]
  return sections
})

const filteredProducts = computed(() => {
  if (!selectedSection.value?.products) return []
  let result = selectedSection.value.products.filter(p => p.active !== false)
  const q = searchText.value.trim().toLowerCase()
  if (q) result = result.filter(p => p.name.toLowerCase().includes(q) || (p.description || '').toLowerCase().includes(q))
  if (activeDiet.value) result = result.filter(p => Array.isArray(p.diet_tags) && p.diet_tags.includes(activeDiet.value))
  return result
})

function selectSection(s) {
  selectedSection.value = s
  expandedIds.value = new Set()
}

function toggleExpand(id, hasDesc) {
  if (!hasDesc) return
  const next = new Set(expandedIds.value)
  next.has(id) ? next.delete(id) : next.add(id)
  expandedIds.value = next
}

async function fetchMenu() {
  isLoading.value = true
  error.value = null
  const restaurantId = route.params.id
  try {
    const [rRes, cRes] = await Promise.all([
      fetch(`/api/restaurants/${restaurantId}`, { headers: { Accept: 'application/json' } }),
      fetch(`/api/restaurants/${restaurantId}/catalogs`, { headers: { Accept: 'application/json' } }),
    ])
    if (!rRes.ok) throw new Error('Restaurante no encontrado')
    const rJson = await rRes.json()
    restaurant.value = rJson?.data ?? rJson
    const cJson = cRes.ok ? await cRes.json() : []
    catalogs.value = Array.isArray(cJson) ? cJson : (cJson?.data ?? [])
  } catch (err) {
    error.value = err.message
  } finally {
    isLoading.value = false
  }
}

onMounted(() => fetchMenu())
</script>

<style scoped>
.menu-container { max-width: 1200px; margin: 0 auto; padding: 2rem; min-height: 100vh; background: #f8fafc; border-radius: 16px; }

.header { margin-bottom: 2rem; }
.restaurant-header { }
.restaurant-title-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.restaurant-title-row h1 { margin: 0; font-size: 2rem; color: #1e293b; }
.open-badge { padding: 0.3rem 0.8rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
.badge-open { background: #dcfce7; color: #166534; }
.badge-closed { background: #fef2f2; color: #dc2626; }
.today-hours { margin: 0.5rem 0 0; color: #64748b; font-size: 0.95rem; }
.restaurant-info { margin: 0.25rem 0 0; color: #64748b; font-size: 0.9rem; }

.filters { margin-bottom: 2rem; }
.search-wrap { display: flex; align-items: center; background: white; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 0 1rem; margin-bottom: 0.75rem; }
.search-icon { margin-right: 0.5rem; }
.search-input { flex: 1; border: none; outline: none; padding: 0.75rem 0; font-size: 0.95rem; background: transparent; }
.btn-clear { background: none; border: none; cursor: pointer; font-size: 1rem; color: #94a3b8; }

.filter-toggles { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
.toggle-label { display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.9rem; color: #475569; padding: 0.4rem 0.8rem; border-radius: 8px; border: 1px solid #e2e8f0; }
.toggle-label.active { background: #f0f4ff; border-color: #667eea; color: #667eea; }
.toggle-label input { display: none; }
.diet-btn { padding: 0.4rem 0.8rem; border: 1px solid #e2e8f0; border-radius: 8px; background: white; cursor: pointer; font-size: 0.9rem; }
.diet-btn.active { background: #f0fdf4; border-color: #22c55e; }

.diet-pills { display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap; }
.diet-pill { padding: 0.4rem 0.9rem; border: 1px solid #e2e8f0; border-radius: 50px; background: white; cursor: pointer; font-size: 0.85rem; transition: all 0.15s; }
.diet-pill.active { background: #f0fdf4; border-color: #22c55e; color: #166534; font-weight: 600; }

.loading, .error-state { text-align: center; padding: 3rem; color: #64748b; }
.error-state { color: #dc2626; }

.menu-layout { display: grid; grid-template-columns: 220px 1fr; gap: 2rem; }
@media (max-width: 768px) { .menu-layout { grid-template-columns: 1fr; } }

.sections-nav { position: sticky; top: 1rem; align-self: start; }
.sections-nav h3 { margin: 0 0 1rem; font-size: 1rem; color: #475569; }
.nav-btn { display: block; width: 100%; text-align: left; padding: 0.6rem 1rem; border: none; background: none; cursor: pointer; border-radius: 8px; font-size: 0.9rem; color: #475569; transition: all 0.15s; margin-bottom: 0.25rem; }
.nav-btn:hover { background: #f1f5f9; }
.nav-btn.active { background: #667eea; color: white; font-weight: 600; }

.products-area { min-height: 300px; }
.section-title-row { margin-bottom: 1.5rem; }
.section-title-row h2 { margin: 0; color: #1e293b; font-size: 1.5rem; }
.catalog-subtitle { margin: 0.25rem 0 0; color: #94a3b8; font-size: 0.85rem; }

.products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }

.product-card {
  background: white; border-radius: 14px; overflow: hidden; cursor: pointer;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s;
}
.product-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
.product-card.compact { display: flex; }

.product-image img { width: 100%; height: 180px; object-fit: cover; }
.product-card.compact .product-image { width: 100px; flex-shrink: 0; }
.product-card.compact .product-image img { height: 100%; }

.product-info { padding: 1rem; flex: 1; }
.product-title-row { display: flex; align-items: center; gap: 0.5rem; }
.product-title-row h3 { margin: 0; font-size: 1.05rem; color: #1e293b; }
.badge-new { background: #f59e0b; color: white; font-size: 0.65rem; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 700; }

.description { color: #64748b; font-size: 0.88rem; margin: 0.5rem 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.description.expanded { -webkit-line-clamp: unset; }

.allergen-chips { display: flex; gap: 0.3rem; flex-wrap: wrap; margin: 0.5rem 0; }
.allergen-chip { background: #fef3c7; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; }

.product-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; }
.price { font-size: 1.2rem; font-weight: 800; color: #667eea; }

.no-products { text-align: center; padding: 4rem 2rem; color: #94a3b8; }
.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
</style>
