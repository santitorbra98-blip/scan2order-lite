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
        <!-- Superadmin view: own restaurants -->
        <template v-if="isSuperAdmin">
          <div class="section-header">
            <h2 class="section-title">🏠 Mis restaurantes</h2>
            <span class="section-count">{{ ownRestaurants.length }}</span>
          </div>
          <div v-if="ownRestaurants.length === 0" class="section-empty">Sin restaurantes propios.</div>
          <div v-else class="restaurants-grid">
            <div v-for="restaurant in ownRestaurants" :key="restaurant.id" class="restaurant-card">
              <RestaurantCardContent
                :restaurant="restaurant"
                :get-image-url="getImageUrl"
                :format-date="formatDate"
                :days="DAYS"
                @edit="openEditModal(restaurant)"
                @edit-schedule="openEditModal(restaurant, true)"
                @qr="qrRestaurant = restaurant"
                @delete="openDeleteModal(restaurant)"
              />
            </div>
          </div>

          <div class="section-header" style="margin-top: 2.5rem;">
            <h2 class="section-title">👥 Restaurantes de admins</h2>
            <span class="section-count">{{ adminRestaurants.length }}</span>
          </div>
          <div v-if="adminRestaurants.length === 0" class="section-empty">Ningún admin ha creado restaurantes aún.</div>
          <div v-else class="restaurants-grid">
            <div v-for="restaurant in adminRestaurants" :key="restaurant.id" class="restaurant-card">
              <div class="owner-badge">
                <span class="owner-label">👤 {{ restaurant.creator?.name }}</span>
                <span class="owner-email">{{ restaurant.creator?.email }}</span>
              </div>
              <RestaurantCardContent
                :restaurant="restaurant"
                :get-image-url="getImageUrl"
                :format-date="formatDate"
                :days="DAYS"
                @edit="openEditModal(restaurant)"
                @edit-schedule="openEditModal(restaurant, true)"
                @qr="qrRestaurant = restaurant"
                @delete="openDeleteModal(restaurant)"
              />
            </div>
          </div>
        </template>

        <!-- Regular admin view -->
        <div v-else class="restaurants-grid">
          <div v-for="restaurant in restaurants" :key="restaurant.id" class="restaurant-card">
            <RestaurantCardContent
              :restaurant="restaurant"
              :get-image-url="getImageUrl"
              :format-date="formatDate"
              :days="DAYS"
              @edit="openEditModal(restaurant)"
              @edit-schedule="openEditModal(restaurant, true)"
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

    <!-- QR Modal -->
    <QrPrintModal v-if="qrRestaurant" :restaurant-id="qrRestaurant.id" :restaurant-name="qrRestaurant.name" @close="qrRestaurant = null" />
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

// Superadmin: split into own vs admin-owned
const ownRestaurants = computed(() =>
  restaurants.value.filter(r => r.creator?.role === 'superadmin' || !r.creator)
)
const adminRestaurants = computed(() =>
  restaurants.value.filter(r => r.creator?.role === 'admin')
)

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

const { toast, showToast } = useToast()

function formatDate(d) {
  if (!d) return 'N/A'
  return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function getImageUrl(r) {
  return r?.image || ''
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
    formData.append('schedule', JSON.stringify(form.schedule))

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
.page-info { font-size: 0.9rem; color: #64748b; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.header h1 { font-size: 2rem; color: #1e293b; margin: 0; }

.btn-create {
  padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1rem;
}
.btn-create:hover { opacity: 0.9; }

.loading, .error { text-align: center; padding: 3rem; color: #64748b; font-size: 1.1rem; }
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
.form-group input:focus { border-color: #667eea; outline: none; }
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

/* Section separators (superadmin view) */
.section-header {
  display: flex; align-items: center; gap: 0.75rem;
  margin-bottom: 1.25rem; padding-bottom: 0.5rem;
  border-bottom: 2px solid #e2e8f0;
}
.section-title { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0; }
.section-count {
  background: #e0e7ff; color: #4338ca;
  font-size: 0.8rem; font-weight: 700; padding: 0.2rem 0.6rem;
  border-radius: 999px; line-height: 1.5;
}
.section-empty { color: #94a3b8; font-style: italic; padding: 1rem 0 1.5rem; }

/* Owner badge on admin-created cards */
.owner-badge {
  display: flex; flex-direction: column; gap: 1px;
  background: #f0f9ff; padding: 0.5rem 1rem;
  border-bottom: 1px solid #bae6fd;
}
.owner-label { font-size: 0.85rem; font-weight: 600; color: #0369a1; }
.owner-email { font-size: 0.75rem; color: #64748b; }

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
