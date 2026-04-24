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

      <div v-else class="restaurants-grid">
        <div v-for="restaurant in restaurants" :key="restaurant.id" class="restaurant-card">
          <div class="restaurant-photo-wrap">
            <img v-if="restaurant.image" :src="getImageUrl(restaurant)" :alt="restaurant.name" class="restaurant-photo" />
            <div v-else class="restaurant-photo restaurant-photo-placeholder">🏪</div>
          </div>
          <div class="restaurant-main">
            <h3 class="restaurant-name">{{ restaurant.name }}</h3>
            <p class="restaurant-address">{{ restaurant.address || 'Sin dirección' }}</p>
            <p class="restaurant-phone">{{ restaurant.phone || 'Sin teléfono' }}</p>
            <p class="restaurant-created">📅 {{ formatDate(restaurant.created_at) }}</p>

            <div class="card-schedule">
              <div class="card-schedule-header">
                <span>⏰</span>
                <strong>Horario</strong>
                <button type="button" class="card-schedule-edit" @click="openEditModal(restaurant, true)">Editar</button>
              </div>
              <div v-if="restaurant.schedule && Object.keys(restaurant.schedule).length" class="card-schedule-grid">
                <div v-for="day in DAYS" :key="day.key" class="card-schedule-day" :class="restaurant.schedule[day.key]?.enabled ? 'day-open' : 'day-closed'">
                  <span class="day-abbr">{{ day.label.slice(0, 2) }}</span>
                  <span class="day-hours">
                    {{ restaurant.schedule[day.key]?.enabled ? `${restaurant.schedule[day.key].open}–${restaurant.schedule[day.key].close}` : 'Cerrado' }}
                  </span>
                </div>
              </div>
              <p v-else class="card-schedule-empty">Sin horario definido</p>
            </div>
          </div>

          <div class="restaurant-meta">
            <span class="status-badge" :class="restaurant.active ? 'status-active' : 'status-inactive'">
              {{ restaurant.active ? 'Activo' : 'Inactivo' }}
            </span>
          </div>

          <div class="restaurant-actions">
            <button class="btn-action btn-qr" @click="qrRestaurant = restaurant">📱 QR Carta</button>
            <button class="btn-action" @click="openEditModal(restaurant)">✏️ Editar</button>
            <button class="btn-action btn-danger" @click="openDeleteModal(restaurant)">🗑️ Borrar</button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination">
        <button class="page-btn" :disabled="pagination.current_page <= 1" @click="fetchRestaurants(pagination.current_page - 1)">‹ Anterior</button>
        <span class="page-info">Página {{ pagination.current_page }} de {{ pagination.last_page }} ({{ pagination.total }} restaurantes)</span>
        <button class="page-btn" :disabled="pagination.current_page >= pagination.last_page" @click="fetchRestaurants(pagination.current_page + 1)">Siguiente ›</button>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showFormModal" class="modal-overlay" @click.self="closeFormModal">
      <div class="modal">
        <div class="modal-header">
          <h2>{{ isEditing ? 'Editar restaurante' : 'Crear restaurante' }}</h2>
          <button @click="closeFormModal" class="btn-close">×</button>
        </div>
        <form @submit.prevent="saveRestaurant" class="modal-body">
          <section class="form-section">
            <h3>Información del restaurante</h3>
            <div class="form-grid">
              <div class="form-group">
                <label for="name">Nombre:</label>
                <input id="name" v-model="form.name" type="text" required placeholder="Nombre del restaurante" />
              </div>
              <div class="form-group">
                <label for="phone">Teléfono:</label>
                <input id="phone" v-model="form.phone" type="text" placeholder="Teléfono" />
              </div>
            </div>
            <div class="form-group">
              <label for="address">Dirección:</label>
              <input id="address" v-model="form.address" type="text" placeholder="Dirección" />
            </div>
            <div class="form-group">
              <label for="restaurant-image">Foto del restaurante:</label>
              <input id="restaurant-image" ref="restaurantImageInput" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="file-input" @change="onImageChange" />
              <small>Formatos: JPG, PNG, GIF, WEBP. Máximo 5MB.</small>
              <div v-if="imagePreview" class="image-preview">
                <img :src="imagePreview" alt="Vista previa" />
                <button type="button" class="btn-remove-image" @click="removeImage">Eliminar foto</button>
              </div>
            </div>
            <div class="form-group checkbox-group">
              <label><input v-model="form.active" type="checkbox" /> Restaurante activo</label>
            </div>
          </section>

          <section class="form-section" ref="scheduleSection">
            <h3>Horario de apertura</h3>
            <div class="schedule-grid">
              <div v-for="day in DAYS" :key="day.key" class="schedule-row">
                <label class="schedule-day-toggle">
                  <input type="checkbox" v-model="form.schedule[day.key].enabled" />
                  <span>{{ day.label }}</span>
                </label>
                <template v-if="form.schedule[day.key].enabled">
                  <input class="time-input" type="time" v-model="form.schedule[day.key].open" />
                  <span>—</span>
                  <input class="time-input" type="time" v-model="form.schedule[day.key].close" />
                </template>
                <span v-else class="schedule-closed">Cerrado</span>
              </div>
            </div>
          </section>

          <div v-if="formError" class="error">{{ formError }}</div>

          <div class="form-actions">
            <button type="button" class="btn-cancel" @click="closeFormModal" :disabled="isSaving">Cancelar</button>
            <button type="submit" class="btn-save" :disabled="isSaving">
              {{ isSaving ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="closeDeleteModal">
      <div class="modal modal-delete">
        <div class="modal-header">
          <h2>Confirmar eliminación</h2>
          <button @click="closeDeleteModal" class="btn-close">×</button>
        </div>
        <div class="modal-body">
          <p>¿Seguro que quieres eliminar <strong>{{ restaurantToDelete?.name }}</strong>?</p>
          <label class="delete-confirm-check">
            <input v-model="deleteConfirmed" type="checkbox" />
            Sí, deseo eliminar este restaurante
          </label>
          <div class="form-actions">
            <button type="button" @click="closeDeleteModal" class="btn-cancel" :disabled="isDeleting">Cancelar</button>
            <button type="button" @click="confirmDelete" class="btn-delete-confirm" :disabled="isDeleting || !deleteConfirmed">
              {{ isDeleting ? 'Eliminando...' : 'Eliminar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- QR Modal -->
    <QrPrintModal v-if="qrRestaurant" :restaurant-id="qrRestaurant.id" :restaurant-name="qrRestaurant.name" @close="qrRestaurant = null" />
  </div>
</template>

<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useImageField } from '../../composables/useImageField'
import { api } from '../../services/api'
import QrPrintModal from '../../components/QrPrintModal.vue'

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

const {
  inputRef: restaurantImageInput,
  file: imageFile,
  preview: imagePreview,
  reset: resetImageField,
  setPreview: setImagePreview,
  handleChange: processImageChange,
  removeSelection: removeImage,
} = useImageField()

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
const restaurantToDelete = ref(null)
const deleteConfirmed = ref(false)
const scheduleSection = ref(null)

const toast = ref({ show: false, type: 'success', message: '' })
let toastTimer = null

const form = ref({
  id: null, name: '', address: '', phone: '', active: true,
  schedule: defaultSchedule(),
})

function showToast(msg, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { show: true, type, message: msg }
  toastTimer = setTimeout(() => { toast.value.show = false }, 2500)
}

function formatDate(d) {
  if (!d) return 'N/A'
  return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function getImageUrl(r) {
  return r?.image ? `/storage/${r.image}` : ''
}

async function onImageChange(event) {
  const result = await processImageChange(event)
  if (!result.ok && result.error) showToast(result.error, 'error')
}

async function fetchRestaurants(page = 1) {
  isLoading.value = true
  error.value = null
  try {
    const result = await api.get(`/restaurants?page=${page}`)
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

function resetForm() {
  form.value = { id: null, name: '', address: '', phone: '', active: true, schedule: defaultSchedule() }
  resetImageField()
}

function openCreateModal() {
  isEditing.value = false
  formError.value = null
  resetForm()
  showFormModal.value = true
}

async function openEditModal(restaurant, scrollToSchedule = false) {
  isEditing.value = true
  formError.value = null
  form.value = {
    id: restaurant.id,
    name: restaurant.name || '',
    address: restaurant.address || '',
    phone: restaurant.phone || '',
    active: Boolean(restaurant.active),
    schedule: (restaurant.schedule && Object.keys(restaurant.schedule).length) ? restaurant.schedule : defaultSchedule(),
  }
  setImagePreview(restaurant.image ? getImageUrl(restaurant) : null)
  showFormModal.value = true

  if (scrollToSchedule) {
    await nextTick()
    scheduleSection.value?.scrollIntoView({ behavior: 'smooth' })
  }
}

function closeFormModal() { showFormModal.value = false; resetForm() }

async function saveRestaurant() {
  isSaving.value = true
  formError.value = null

  try {
    const formData = new FormData()
    formData.append('name', form.value.name)
    formData.append('address', form.value.address || '')
    formData.append('phone', form.value.phone || '')
    formData.append('active', form.value.active ? '1' : '0')
    formData.append('schedule', JSON.stringify(form.value.schedule))

    if (imageFile.value) {
      formData.append('image', imageFile.value)
    }

    if (isEditing.value) {
      formData.append('_method', 'PUT')
      await api.upload(`/restaurants/${form.value.id}`, formData)
      showToast('Restaurante actualizado')
    } else {
      await api.upload('/restaurants', formData)
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
  deleteConfirmed.value = false
  showDeleteModal.value = true
}

function closeDeleteModal() {
  showDeleteModal.value = false
  restaurantToDelete.value = null
}

async function confirmDelete() {
  if (!deleteConfirmed.value || !restaurantToDelete.value) return
  isDeleting.value = true
  try {
    await api.delete(`/restaurants/${restaurantToDelete.value.id}`)
    showToast('Restaurante eliminado')
    closeDeleteModal()
    await fetchRestaurants()
  } catch (err) {
    showToast(err.message || 'Error al eliminar', 'error')
  } finally {
    isDeleting.value = false
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

.empty-state { text-align: center; padding: 4rem 2rem; color: #64748b; }
.empty-state h2 { font-size: 1.5rem; margin-bottom: 0.5rem; }

.restaurants-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 1.5rem; }

.restaurant-card {
  background: white; border-radius: 14px; overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06); transition: transform 0.2s;
}
.restaurant-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }

.restaurant-photo-wrap { height: 180px; overflow: hidden; }
.restaurant-photo { width: 100%; height: 100%; object-fit: cover; }
.restaurant-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 3rem; background: #f1f5f9; }

.restaurant-main { padding: 1.25rem; }
.restaurant-name { font-size: 1.2rem; color: #1e293b; margin: 0 0 0.5rem; }
.restaurant-address, .restaurant-phone, .restaurant-created { margin: 0.2rem 0; color: #64748b; font-size: 0.9rem; }

.restaurant-meta { padding: 0 1.25rem; display: flex; gap: 0.5rem; }
.status-badge { padding: 0.3rem 0.8rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600; }
.status-active { background: #dcfce7; color: #166534; }
.status-inactive { background: #fef2f2; color: #dc2626; }

.restaurant-actions { padding: 1rem 1.25rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
.btn-action {
  padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px;
  background: white; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: all 0.2s;
}
.btn-action:hover { background: #f8fafc; border-color: #cbd5e1; }
.btn-action.btn-qr { border-color: #667eea; color: #667eea; }
.btn-action.btn-danger { border-color: #fca5a5; color: #dc2626; }
.btn-action.btn-danger:hover { background: #fef2f2; }

.card-schedule { margin-top: 1rem; padding: 0.75rem; background: #f8fafc; border-radius: 8px; }
.card-schedule-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
.card-schedule-edit { margin-left: auto; background: none; border: none; color: #667eea; cursor: pointer; font-size: 0.85rem; font-weight: 600; }
.card-schedule-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.25rem; }
.card-schedule-day { text-align: center; padding: 0.3rem; border-radius: 6px; font-size: 0.75rem; }
.day-open { background: #dcfce7; }
.day-closed { background: #f1f5f9; color: #94a3b8; }
.day-abbr { display: block; font-weight: 700; }
.day-hours { display: block; font-size: 0.7rem; }
.card-schedule-empty { margin: 0; font-size: 0.85rem; color: #94a3b8; }

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
</style>
