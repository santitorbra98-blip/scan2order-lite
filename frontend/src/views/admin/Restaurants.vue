<template>
  <div class="restaurants-container">
    <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">{{ toast.message }}</div>

    <div class="header">
      <h1>Gestión de Restaurantes</h1>
      <button class="btn-create" @click="openCreateModal">+ Crear Restaurante</button>
    </div>

    <div class="content">
      <div v-if="isLoading" class="loading">Cargando restaurantes...</div>
      <div v-else-if="error" class="error">{{ error }}</div>

      <div v-else-if="restaurants.length === 0" class="empty-state">
        <h2>📭 No hay restaurantes</h2>
        <p>Crea el primero para comenzar.</p>
      </div>

      <template v-else>
        <div class="restaurants-grid">
          <div v-for="restaurant in restaurants" :key="restaurant.id" class="restaurant-card">
            <div v-if="isSuperAdmin && restaurant.creator" class="owner-badge">
              <span class="owner-label">👤 {{ restaurant.creator.name }}</span>
              <span class="owner-email">{{ restaurant.creator.email }}</span>
            </div>
            <div v-if="canAssignOwner(restaurant)" class="owner-actions">
              <button class="btn-assign-owner" @click="openAssignOwnerModal(restaurant)">👥 Asignar a admin</button>
            </div>
            <RestaurantCardContent
              :restaurant="restaurant"
              :get-image-url="getImageUrl"
              :format-date="formatDate"
              :days="DAYS"
              @edit="openEditModal(restaurant)"
              @edit-schedule="openEditModal(restaurant, true)"
              @insights="openInsightsModal(restaurant)"
              @qr="qrRestaurant = restaurant"
              @delete="openDeleteModal(restaurant)"
            />
          </div>
        </div>
      </template>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination">
        <button class="page-btn" :disabled="pagination.current_page <= 1" @click="fetchRestaurants(pagination.current_page - 1)">‹ Anterior</button>
        <span class="page-info">Página {{ pagination.current_page }} de {{ pagination.last_page }} ({{ pagination.total }} restaurantes)</span>
        <button class="page-btn" :disabled="pagination.current_page >= pagination.last_page" @click="fetchRestaurants(pagination.current_page + 1)">Siguiente ›</button>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <RestaurantFormModal
      v-model="showFormModal"
      :is-editing="isEditing"
      :initial="formInitial"
      :days="DAYS"
      :default-schedule="defaultSchedule"
      :saving="isSaving"
      :error="formError"
      @close="closeFormModal"
      @save="saveRestaurant"
    />

    <!-- Delete Modal -->
    <RestaurantDeleteModal
      v-model="showDeleteModal"
      :restaurant-name="restaurantToDelete?.name"
      :deleting="isDeleting"
      @close="closeDeleteModal"
      @confirm="confirmDelete"
    />

    <!-- Assign Owner Modal -->
    <div v-if="showAssignOwnerModal" class="modal-overlay" @click.self="closeAssignOwnerModal">
      <div class="modal modal-assign-owner">
        <div class="modal-header">
          <h2>👥 Asignar local a admin</h2>
          <button class="btn-close" @click="closeAssignOwnerModal">×</button>
        </div>
        <div class="modal-body">
          <p class="assign-owner-text">
            Local: <strong>{{ ownerAssignmentRestaurant?.name }}</strong>
          </p>

          <div v-if="ownerAssignmentLoadingAdmins" class="loading">Cargando administradores...</div>
          <template v-else>
            <div v-if="ownerAssignmentAdmins.length === 0" class="error">No hay admins activos disponibles.</div>
            <div v-else class="form-group">
              <label for="owner-assignment-admin">Administrador destino</label>
              <select id="owner-assignment-admin" v-model.number="ownerAssignmentSelectedUserId">
                <option :value="null" disabled>Selecciona un admin</option>
                <option v-for="admin in ownerAssignmentAdmins" :key="admin.id" :value="admin.id">
                  {{ admin.name }} · {{ admin.email }}
                </option>
              </select>
            </div>

            <div class="form-actions">
              <button type="button" class="btn-cancel" :disabled="ownerAssignmentSaving" @click="closeAssignOwnerModal">Cancelar</button>
              <button
                type="button"
                class="btn-save"
                :disabled="ownerAssignmentSaving || !ownerAssignmentSelectedUserId"
                @click="confirmAssignOwner"
              >{{ ownerAssignmentSaving ? 'Asignando...' : 'Asignar' }}</button>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- QR Modal -->
    <QrPrintModal v-if="qrRestaurant" :restaurant-id="qrRestaurant.id" :restaurant-name="qrRestaurant.name" @close="qrRestaurant = null" />

    <!-- Insights Modal -->
    <div v-if="showInsightsModal" class="modal-overlay" @click.self="closeInsightsModal">
      <div class="modal modal-insights">
        <div class="modal-header">
          <h2>📊 Datos de {{ insightsRestaurant?.name || 'restaurante' }}</h2>
          <button class="btn-close" @click="closeInsightsModal">×</button>
        </div>
        <div class="modal-body">
          <div class="period-tabs">
            <button
              v-for="opt in insightsPeriodOptions"
              :key="opt.value"
              class="period-tab"
              :class="{ active: insightsPeriod === opt.value }"
              :disabled="insightsLoading"
              @click="setInsightsPeriod(opt.value)"
            >{{ opt.label }}</button>
            <button class="period-tab export-tab" :disabled="insightsLoading || !insightsData" @click="exportInsightsCsv">Exportar CSV</button>
          </div>

          <div v-if="insightsLoading" class="loading">Cargando métricas...</div>
          <div v-else-if="insightsError" class="error">{{ insightsError }}</div>
          <div v-else-if="insightsData" class="insights-content">
            <div class="insights-grid">
              <div class="insight-card">
                <p class="insight-label">Visitantes</p>
                <p class="insight-value">{{ formatNumber(insightsData.total_visits) }}</p>
              </div>
              <div class="insight-card">
                <p class="insight-label">Visitantes únicos</p>
                <p class="insight-value">{{ formatNumber(insightsData.unique_visits) }}</p>
              </div>
              <div class="insight-card">
                <p class="insight-label">Únicos nuevos</p>
                <p class="insight-value">{{ formatNumber(insightsData.new_unique_visits) }}</p>
              </div>
              <div class="insight-card">
                <p class="insight-label">Cambio vs mes anterior</p>
                <p class="insight-value" :class="changeClass(insightsData.month_over_month?.change_percent)">
                  {{ formatPercent(insightsData.month_over_month?.change_percent) }}
                </p>
              </div>
            </div>

            <div class="insight-summary-row">
              <div class="insight-summary-box">
                <strong>📅 Día con más visitas:</strong>
                <span>
                  {{ insightsData.best_weekday?.day || 'Sin datos' }}
                  <template v-if="insightsData.best_weekday?.visits">({{ formatNumber(insightsData.best_weekday.visits) }})</template>
                </span>
              </div>
              <div class="insight-summary-box">
                <strong>🕐 Hora punta:</strong>
                <span>{{ insightsData.peak_hour || 'Sin datos' }}</span>
              </div>
            </div>

            <div class="trend-section">
              <h3>Tendencia últimos 7 días</h3>
              <div v-if="!insightsData.trend_7d || insightsData.trend_7d.length === 0" class="trend-empty">Sin datos de tendencia todavía.</div>
              <div v-else class="trend-list">
                <div v-for="day in insightsData.trend_7d" :key="day.day" class="trend-item">
                  <span>{{ formatDayLabel(day.day) }}</span>
                  <strong>{{ formatNumber(day.visits) }}</strong>
                </div>
              </div>
            </div>

            <p class="insights-note">Métricas estimadas por sesión (IP + navegador).</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../composables/useToast'
import { restaurantService } from '../../services/restaurantService'
import QrPrintModal from '../../components/QrPrintModal.vue'
import RestaurantCardContent from '../../components/RestaurantCardContent.vue'
import RestaurantFormModal from '../../components/RestaurantFormModal.vue'
import RestaurantDeleteModal from '../../components/RestaurantDeleteModal.vue'

const DAYS = [
  { key: 'monday', label: 'Lunes' },
  { key: 'tuesday', label: 'Martes' },
  { key: 'wednesday', label: 'Miércoles' },
  { key: 'thursday', label: 'Jueves' },
  { key: 'friday', label: 'Viernes' },
  { key: 'saturday', label: 'Sábado' },
  { key: 'sunday', label: 'Domingo' },
]

function defaultSchedule() {
  return {
    monday:    { enabled: true,  open: '09:00', close: '22:00' },
    tuesday:   { enabled: true,  open: '09:00', close: '22:00' },
    wednesday: { enabled: true,  open: '09:00', close: '22:00' },
    thursday:  { enabled: true,  open: '09:00', close: '22:00' },
    friday:    { enabled: true,  open: '09:00', close: '23:00' },
    saturday:  { enabled: true,  open: '10:00', close: '23:00' },
    sunday:    { enabled: false, open: '10:00', close: '20:00' },
  }
}

const authStore = useAuthStore()
const isSuperAdmin = computed(() => authStore.hasRole('superadmin'))

const restaurants = ref([])
const pagination = ref(null)

const isLoading = ref(false)
const error = ref(null)
const qrRestaurant = ref(null)

const showFormModal = ref(false)
const showDeleteModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const formError = ref(null)
const formInitial = ref(null)
const restaurantToDelete = ref(null)

const showInsightsModal = ref(false)
const insightsRestaurant = ref(null)
const insightsPeriod = ref('30d')
const insightsLoading = ref(false)
const insightsError = ref(null)
const insightsData = ref(null)

const showAssignOwnerModal = ref(false)
const ownerAssignmentRestaurant = ref(null)
const ownerAssignmentAdmins = ref([])
const ownerAssignmentSelectedUserId = ref(null)
const ownerAssignmentLoadingAdmins = ref(false)
const ownerAssignmentSaving = ref(false)

const insightsPeriodOptions = [
  { value: '7d', label: '7 días' },
  { value: '30d', label: '30 días' },
  { value: 'month', label: 'Este mes' },
]

const { toast, showToast } = useToast()

function formatDate(d) {
  if (!d) return 'N/A'
  return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function getImageUrl(r) {
  return r?.image || ''
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString('es-ES')
}

function formatPercent(value) {
  const num = Number(value || 0)
  const sign = num > 0 ? '+' : ''
  return `${sign}${num.toFixed(2)}%`
}

function changeClass(value) {
  const num = Number(value || 0)
  if (num > 0) return 'change-up'
  if (num < 0) return 'change-down'
  return 'change-flat'
}

function formatDayLabel(day) {
  if (!day) return '-'
  return new Date(day).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' })
}

function canAssignOwner(restaurant) {
  return isSuperAdmin.value && restaurant?.creator?.role === 'superadmin'
}

function csvCell(value) {
  const text = String(value ?? '')
  return `"${text.replaceAll('"', '""')}"`
}

function csvLine(values) {
  return values.map(csvCell).join(',')
}

function exportInsightsCsv() {
  if (!insightsData.value || !insightsRestaurant.value) return

  const data = insightsData.value
  const rows = [
    csvLine(['Restaurante', insightsRestaurant.value.name]),
    csvLine(['Periodo', insightsPeriod.value]),
    csvLine(['Desde', data.range?.from || '']),
    csvLine(['Hasta', data.range?.to || '']),
    csvLine([]),
    csvLine(['Metrica', 'Valor']),
    csvLine(['Visitantes', data.total_visits]),
    csvLine(['Visitantes unicos', data.unique_visits]),
    csvLine(['Visitantes unicos nuevos', data.new_unique_visits]),
    csvLine(['Visitantes unicos recurrentes', data.returning_unique_visits]),
    csvLine(['Cambio vs mes anterior (%)', data.month_over_month?.change_percent]),
    csvLine(['Dia con mas visitas', data.best_weekday?.day || '']),
    csvLine(['Visitas en mejor dia', data.best_weekday?.visits || 0]),
    csvLine(['Hora punta', data.peak_hour || '']),
    csvLine([]),
    csvLine(['Tendencia 7d', 'Visitas']),
    ...(Array.isArray(data.trend_7d)
      ? data.trend_7d.map((row) => csvLine([row.day, row.visits]))
      : []),
  ]

  const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  const safeName = (insightsRestaurant.value.name || 'restaurante').replace(/[^a-z0-9_-]+/gi, '-').toLowerCase()
  const stamp = new Date().toISOString().slice(0, 10)
  link.href = url
  link.download = `${safeName}-datos-${stamp}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

async function fetchRestaurants(page = 1) {
  isLoading.value = true
  error.value = null
  try {
    const result = await restaurantService.getAll(page)
    if (result && result.meta) {
      restaurants.value = result.data
      pagination.value = result.meta
    } else {
      restaurants.value = Array.isArray(result) ? result : []
      pagination.value = null
    }
  } catch (err) {
    error.value = err.message || 'Error al cargar restaurantes'
  } finally {
    isLoading.value = false
  }
}

function openCreateModal() {
  isEditing.value = false
  formError.value = null
  formInitial.value = null
  showFormModal.value = true
}

function openEditModal(restaurant) {
  isEditing.value = true
  formError.value = null
  formInitial.value = {
    id: restaurant.id,
    name: restaurant.name || '',
    address: restaurant.address || '',
    phone: restaurant.phone || '',
    active: Boolean(restaurant.active),
    schedule: (restaurant.schedule && Object.keys(restaurant.schedule).length) ? restaurant.schedule : defaultSchedule(),
    _imagePreview: restaurant.image ? getImageUrl(restaurant) : null,
  }
  showFormModal.value = true
}

function closeFormModal() { showFormModal.value = false; formInitial.value = null }

async function saveRestaurant({ form, imageFile, removeImage }) {
  isSaving.value = true
  formError.value = null

  try {
    const formData = new FormData()
    formData.append('name', form.name)
    formData.append('address', form.address || '')
    formData.append('phone', form.phone || '')
    formData.append('active', form.active ? '1' : '0')
    if (form.useSchedule) {
      formData.append('schedule', JSON.stringify(form.schedule))
    } else if (isEditing.value) {
      formData.append('remove_schedule', '1')
    }

    if (imageFile) {
      formData.append('image', imageFile)
    } else if (removeImage) {
      formData.append('remove_image', '1')
    }

    if (isEditing.value) {
      formData.append('_method', 'PUT')
      await restaurantService.update(form.id, formData)
      showToast('Restaurante actualizado')
    } else {
      await restaurantService.create(formData)
      showToast('Restaurante creado')
    }

    closeFormModal()
    await fetchRestaurants()
  } catch (err) {
    formError.value = err?.data?.message || err.message || 'Error al guardar'
  } finally {
    isSaving.value = false
  }
}

function openDeleteModal(restaurant) {
  restaurantToDelete.value = restaurant
  showDeleteModal.value = true
}

function closeDeleteModal() {
  showDeleteModal.value = false
  restaurantToDelete.value = null
}

function closeInsightsModal() {
  showInsightsModal.value = false
  insightsRestaurant.value = null
  insightsData.value = null
  insightsError.value = null
  insightsPeriod.value = '30d'
}

function closeAssignOwnerModal() {
  showAssignOwnerModal.value = false
  ownerAssignmentRestaurant.value = null
  ownerAssignmentSelectedUserId.value = null
  ownerAssignmentAdmins.value = []
}

async function loadAssignableAdmins() {
  ownerAssignmentLoadingAdmins.value = true

  try {
    let page = 1
    let lastPage = 1
    const allUsers = []

    while (page <= lastPage) {
      const result = await restaurantService.getUsers(page)
      const data = Array.isArray(result?.data) ? result.data : (Array.isArray(result) ? result : [])
      allUsers.push(...data)

      if (result?.meta?.last_page) {
        lastPage = Number(result.meta.last_page)
      }

      page += 1
    }

    ownerAssignmentAdmins.value = allUsers.filter((u) => u?.role?.name === 'admin' && u?.status === 'active')
  } catch {
    ownerAssignmentAdmins.value = []
    showToast('No se pudieron cargar admins disponibles', 'error')
  } finally {
    ownerAssignmentLoadingAdmins.value = false
  }
}

async function openAssignOwnerModal(restaurant) {
  ownerAssignmentRestaurant.value = restaurant
  ownerAssignmentSelectedUserId.value = null
  ownerAssignmentAdmins.value = []
  showAssignOwnerModal.value = true
  await loadAssignableAdmins()
}

async function confirmAssignOwner() {
  if (!ownerAssignmentRestaurant.value?.id || !ownerAssignmentSelectedUserId.value) return

  ownerAssignmentSaving.value = true
  try {
    await restaurantService.assignOwner(ownerAssignmentRestaurant.value.id, ownerAssignmentSelectedUserId.value)
    showToast('Restaurante asignado correctamente')
    closeAssignOwnerModal()
    await fetchRestaurants(pagination.value?.current_page || 1)
  } catch (err) {
    showToast(err?.message || 'No se pudo asignar el restaurante', 'error')
  } finally {
    ownerAssignmentSaving.value = false
  }
}

async function loadInsights() {
  if (!insightsRestaurant.value?.id) return

  insightsLoading.value = true
  insightsError.value = null

  try {
    insightsData.value = await restaurantService.getInsights(insightsRestaurant.value.id, insightsPeriod.value)
  } catch (err) {
    insightsData.value = null
    insightsError.value = err?.message || 'No se pudieron cargar las métricas del restaurante'
  } finally {
    insightsLoading.value = false
  }
}

async function setInsightsPeriod(period) {
  if (insightsPeriod.value === period) return
  insightsPeriod.value = period
  await loadInsights()
}

async function openInsightsModal(restaurant) {
  insightsRestaurant.value = restaurant
  showInsightsModal.value = true
  insightsPeriod.value = '30d'
  await loadInsights()
}

async function confirmDelete() {
  if (!restaurantToDelete.value) return
  isDeleting.value = true
  const targetId = restaurantToDelete.value.id
  try {
    await restaurantService.remove(targetId)
    // Remove immediately from local list for instant feedback
    restaurants.value = restaurants.value.filter(r => r.id !== targetId)
    showToast('Restaurante eliminado')
    closeDeleteModal()
  } catch (err) {
    showToast(err.response?.data?.message || err.message || 'Error al eliminar', 'error')
  } finally {
    isDeleting.value = false
    // Refresh in background to sync any server-side changes
    fetchRestaurants(pagination.value?.current_page || 1)
  }
}

onMounted(() => fetchRestaurants())
</script>

<style scoped>
.restaurants-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

.toast {
  position: fixed; top: 1rem; right: 1rem; z-index: 1000;
  padding: 0.85rem 1.5rem; border-radius: 10px; font-weight: 600;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15); animation: slideIn 0.3s;
}
.toast-success { background: #dcfce7; color: #166534; }
.toast-error { background: #fef2f2; color: #dc2626; }

.pagination {
  display: flex; align-items: center; justify-content: center;
  gap: 1rem; padding: 1.5rem 0 0.5rem;
}
.page-btn {
  padding: 0.5rem 1rem; background: white; border: 1.5px solid #e2e8f0;
  border-radius: 8px; cursor: pointer; font-weight: 600; color: #475569;
  transition: all 0.2s;
}
.page-btn:hover:not(:disabled) { background: #f8fafc; border-color: #667eea; color: #667eea; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.9rem; color: #1e293b; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.header h1 { font-size: 2rem; color: #1e293b; margin: 0; }

.btn-create {
  padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1rem;
}
.btn-create:hover { opacity: 0.9; }

.loading, .error { text-align: center; padding: 3rem; color: #1e293b; font-size: 1.1rem; }
.error { color: #dc2626; }

.empty-state { text-align: center; padding: 4rem 2rem; color: #1e293b; }
.empty-state h2 { font-size: 1.5rem; margin-bottom: 0.5rem; }

.restaurants-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 1.5rem; }

.restaurant-card {
  background: white; border-radius: 14px; overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06); transition: transform 0.2s;
}
.restaurant-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }

/* Modals */
.modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; padding: 1rem;
}

.modal {
  background: white; border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}

.modal-insights { max-width: 760px; }

.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; }
.modal-header h2 { margin: 0; font-size: 1.3rem; color: #1e293b; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }

.modal-body { padding: 1.5rem; }

.form-section { margin-bottom: 1.5rem; }
.form-section h3 { font-size: 1rem; color: #475569; margin: 0 0 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f1f5f9; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 500px) { .form-grid { grid-template-columns: 1fr; } }

.form-group { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 0.75rem; }
.form-group label { font-weight: 600; font-size: 0.9rem; color: #334155; }
.form-group input[type="text"] { padding: 0.6rem 0.8rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; }
.form-group select { padding: 0.6rem 0.8rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; }
.form-group input:focus { border-color: #667eea; outline: none; }
.form-group select:focus { border-color: #667eea; outline: none; }
.form-group small { color: #94a3b8; font-size: 0.8rem; }

.file-input { font-size: 0.9rem; }

.image-preview { margin-top: 0.5rem; }
.image-preview img { max-width: 200px; border-radius: 8px; }
.btn-remove-image { display: block; margin-top: 0.5rem; background: none; border: none; color: #dc2626; cursor: pointer; font-size: 0.85rem; }

.checkbox-group label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }

.schedule-grid { display: flex; flex-direction: column; gap: 0.5rem; }
.schedule-row { display: flex; align-items: center; gap: 0.75rem; }
.schedule-day-toggle { display: flex; align-items: center; gap: 0.4rem; min-width: 130px; font-size: 0.9rem; cursor: pointer; }
.time-input { padding: 0.4rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; }
.schedule-closed { color: #94a3b8; font-size: 0.85rem; }

.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.btn-cancel { padding: 0.6rem 1.2rem; background: #f1f5f9; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-save { padding: 0.6rem 1.2rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-save:disabled, .btn-cancel:disabled { opacity: 0.6; cursor: not-allowed; }

.modal-delete .modal-body p { margin: 0 0 1rem; color: #475569; }
.delete-confirm-check { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.9rem; cursor: pointer; }
.btn-delete-confirm { padding: 0.6rem 1.2rem; background: #dc2626; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-delete-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

.error { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1rem; }

/* Owner badge on admin-created cards */
.owner-badge {
  display: flex; flex-direction: column; gap: 1px;
  background: #f0f9ff; padding: 0.5rem 1rem;
  border-bottom: 1px solid #bae6fd;
}
.owner-label { font-size: 0.85rem; font-weight: 600; color: #0369a1; }
.owner-email { font-size: 0.75rem; color: #1e293b; }

.owner-actions {
  padding: 0.5rem 1rem 0;
}

.btn-assign-owner {
  width: 100%;
  padding: 0.45rem 0.7rem;
  border: 1px solid #0ea5e9;
  border-radius: 8px;
  background: #f0f9ff;
  color: #075985;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-assign-owner:hover {
  background: #e0f2fe;
}

.modal-assign-owner { max-width: 520px; }
.assign-owner-text { margin: 0 0 1rem; color: #334155; }

.period-tabs {
  display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem;
}

.period-tab {
  padding: 0.4rem 0.8rem;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #fff;
  color: #334155;
  font-weight: 600;
  cursor: pointer;
}

.period-tab.active {
  border-color: #0ea5e9;
  color: #0369a1;
  background: #f0f9ff;
}

.period-tab.export-tab {
  margin-left: auto;
  border-color: #16a34a;
  color: #166534;
  background: #f0fdf4;
}

.insights-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
}

.insight-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.75rem;
}

.insight-label {
  margin: 0 0 0.35rem;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #64748b;
}

.insight-value {
  margin: 0;
  color: #0f172a;
  font-size: 1.4rem;
  font-weight: 700;
}

.change-up { color: #15803d; }
.change-down { color: #b91c1c; }
.change-flat { color: #334155; }

.insight-summary-row {
  margin-top: 1rem;
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.5rem;
}

.insight-summary-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.trend-section {
  margin-top: 1rem;
}

.trend-section h3 {
  margin: 0 0 0.6rem;
  color: #1e293b;
  font-size: 1rem;
}

.trend-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 0.45rem;
}

.trend-item {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.5rem 0.65rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.86rem;
  color: #334155;
}

.trend-empty {
  font-size: 0.9rem;
  color: #64748b;
}

.insights-note {
  margin-top: 1rem;
  color: #64748b;
  font-size: 0.8rem;
}

@media (max-width: 640px) {
  .restaurants-container { padding: 1rem; }
  .header h1 { font-size: 1.4rem; }
  .btn-create { padding: 0.6rem 1rem; font-size: 0.9rem; }
  .restaurants-grid { grid-template-columns: 1fr; gap: 1rem; }
  .section-header { flex-wrap: wrap; gap: 0.5rem; }
  .pagination { flex-wrap: wrap; gap: 0.5rem; padding: 1rem 0 0.5rem; }
  .page-info { width: 100%; text-align: center; order: -1; }
}
</style>
