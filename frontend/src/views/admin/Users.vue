<template>
  <div class="users-container">
    <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">{{ toast.message }}</div>

    <div class="header">
      <h1>👥 Gestión de Usuarios</h1>
      <div class="header-actions">
        <router-link to="/admin/settings" class="btn-settings">⚙️ Configuración</router-link>
        <button class="btn-create" @click="openCreateModal">+ Crear Usuario</button>
      </div>
    </div>

    <div class="search-bar">
      <span class="search-icon">🔎</span>
      <input v-model="searchTerm" type="text" class="search-input" placeholder="Buscar por nombre, email o teléfono..." />
      <button v-if="searchTerm" type="button" class="search-clear" @click="searchTerm = ''">✕</button>
    </div>

    <div class="content">
      <div v-if="isLoading" class="loading">Cargando usuarios...</div>
      <div v-else-if="error" class="error">{{ error }}</div>

      <div v-else-if="users.length === 0" class="empty-state">
        <h2>📭 No hay usuarios</h2>
        <p>Crea el primero para comenzar.</p>
      </div>

      <table v-else class="users-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Rol</th>
            <th>Restaurantes</th>
            <th>Límites</th>
            <th>Estado</th>
            <th>Creado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in filteredUsers" :key="u.id">
            <td class="cell-name">{{ u.name }}</td>
            <td>{{ u.email }}</td>
            <td>{{ u.phone || '—' }}</td>
            <td>
              <span class="role-badge" :class="`role-${u.role?.name}`">{{ u.role?.name || 'sin rol' }}</span>
            </td>
            <td class="cell-restaurants">
              <template v-if="u.restaurants && u.restaurants.length">
                <span
                  v-for="r in u.restaurants"
                  :key="r.id"
                  class="restaurant-badge"
                  :title="r.name"
                >{{ r.name }}</span>
              </template>
              <span v-else class="no-restaurants">—</span>
            </td>
            <td class="cell-limits">
              <span class="limit-item" title="Restaurantes">🏠 {{ u.max_restaurants ?? '∞' }}</span>
              <span class="limit-item" title="Catálogos">📋 {{ u.max_catalogs ?? '∞' }}</span>
              <span class="limit-item" title="Productos">🍽️ {{ u.max_products ?? '∞' }}</span>
            </td>
            <td>
              <span class="status-badge" :class="`status-${u.status}`">{{ statusLabel(u.status) }}</span>
            </td>
            <td>{{ formatDate(u.created_at) }}</td>
            <td class="cell-actions">
              <button class="btn-action" @click="openEditModal(u)" :disabled="u.id === currentUserId">✏️</button>
              <button class="btn-action btn-danger" @click="openDeleteModal(u)" :disabled="u.id === currentUserId">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination">
        <button class="page-btn" :disabled="pagination.current_page <= 1" @click="fetchUsers(pagination.current_page - 1)">‹ Anterior</button>
        <span class="page-info">Página {{ pagination.current_page }} de {{ pagination.last_page }} ({{ pagination.total }} usuarios)</span>
        <button class="page-btn" :disabled="pagination.current_page >= pagination.last_page" @click="fetchUsers(pagination.current_page + 1)">Siguiente ›</button>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showFormModal" class="modal-overlay" @click.self="closeFormModal">
      <div class="modal">
        <div class="modal-header">
          <h2>{{ isEditing ? 'Editar usuario' : 'Crear usuario' }}</h2>
          <button @click="closeFormModal" class="btn-close">×</button>
        </div>
        <form @submit.prevent="saveUser" class="modal-body">
          <div class="form-group">
            <label for="u-name">Nombre:</label>
            <input id="u-name" v-model="form.name" type="text" required placeholder="Nombre completo" />
          </div>
          <div class="form-group">
            <label for="u-email">Email:</label>
            <input id="u-email" v-model="form.email" type="email" required placeholder="email@ejemplo.com" />
          </div>
          <div class="form-group">
            <label for="u-phone">Teléfono:</label>
            <input id="u-phone" v-model="form.phone" type="text" placeholder="Teléfono (opcional)" />
          </div>
          <div class="form-group">
            <label for="u-password">{{ isEditing ? 'Nueva contraseña (dejar vacío para no cambiar):' : 'Contraseña:' }}</label>
            <input id="u-password" v-model="form.password" type="password" :required="!isEditing" minlength="12" placeholder="Mínimo 12 caracteres" />
          </div>
          <div class="form-group">
            <label for="u-role">Rol:</label>
            <template v-if="isEditing">
              <select id="u-role" v-model="form.role_id" required>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
              </select>
            </template>
            <template v-else>
              <div class="role-fixed-badge">admin</div>
              <small class="role-hint">Los usuarios creados desde este panel siempre son administradores.</small>
            </template>
          </div>
          <div class="form-group">
            <label for="u-status">Estado:</label>
            <select id="u-status" v-model="form.status">
              <option value="active">Activo</option>
              <option value="inactive">Inactivo</option>
              <option value="suspended">Suspendido</option>
            </select>
          </div>

          <div class="limits-section">
            <div class="limits-title">Límites del administrador</div>
            <small class="limits-hint">Dejar en blanco para acceso ilimitado.</small>
            <div class="limits-grid">
              <div class="form-group">
                <label for="u-max-restaurants">🏠 Máx. restaurantes:</label>
                <input id="u-max-restaurants" v-model.number="form.max_restaurants" type="number" min="0" max="9999" placeholder="Sin límite" />
              </div>
              <div class="form-group">
                <label for="u-max-catalogs">📋 Máx. catálogos:</label>
                <input id="u-max-catalogs" v-model.number="form.max_catalogs" type="number" min="0" max="9999" placeholder="Sin límite" />
              </div>
              <div class="form-group">
                <label for="u-max-products">🍽️ Máx. productos:</label>
                <input id="u-max-products" v-model.number="form.max_products" type="number" min="0" max="9999" placeholder="Sin límite" />
              </div>
            </div>
          </div>

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
          <p>¿Seguro que quieres eliminar al usuario <strong>{{ userToDelete?.name }}</strong> ({{ userToDelete?.email }})?</p>
          <p class="warning-text">Esta acción revocará todos los accesos del usuario.</p>
          <label class="delete-confirm-check">
            <input v-model="deleteConfirmed" type="checkbox" />
            Sí, deseo eliminar este usuario
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
  </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue'
import { api } from '../../services/api'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const currentUserId = ref(auth.user?.id)

const users = ref([])
const pagination = ref(null)
const roles = ref([])
const defaults = ref({ max_restaurants: 1, max_catalogs: 20, max_products: null })
const isLoading = ref(false)
const error = ref(null)
const searchTerm = ref('')

const filteredUsers = computed(() => {
  const term = searchTerm.value.trim().toLowerCase()
  if (!term) return users.value
  return users.value.filter((u) =>
    [u.name, u.email, u.phone].some((v) => String(v || '').toLowerCase().includes(term))
  )
})

const showFormModal = ref(false)
const showDeleteModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const formError = ref(null)
const userToDelete = ref(null)
const deleteConfirmed = ref(false)

const toast = ref({ show: false, type: 'success', message: '' })
let toastTimer = null

const form = ref({ id: null, name: '', email: '', phone: '', password: '', role_id: null, status: 'active', max_restaurants: null, max_catalogs: null, max_products: null })

function showToast(msg, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { show: true, type, message: msg }
  toastTimer = setTimeout(() => { toast.value.show = false }, 2500)
}

function formatDate(d) {
  if (!d) return 'N/A'
  return new Date(d).toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

function statusLabel(s) {
  return { active: 'Activo', inactive: 'Inactivo', suspended: 'Suspendido' }[s] || s
}

async function fetchUsers(page = 1) {
  isLoading.value = true
  error.value = null
  try {
    const result = await api.get(`/users?page=${page}`)
    if (result && result.meta) {
      users.value = result.data
      pagination.value = result.meta
    } else {
      users.value = Array.isArray(result) ? result : []
      pagination.value = null
    }
  } catch (err) {
    error.value = err.message || 'Error al cargar usuarios'
  } finally {
    isLoading.value = false
  }
}

async function fetchRoles() {
  try {
    const data = await api.get('/roles')
    roles.value = Array.isArray(data) ? data : []
  } catch { roles.value = [] }
}

async function fetchDefaults() {
  try {
    const data = await api.get('/settings')
    const toInt = v => (v === null || v === undefined || v === '') ? null : parseInt(v, 10) || null
    defaults.value = {
      max_restaurants: toInt(data.default_max_restaurants) ?? 1,
      max_catalogs:    toInt(data.default_max_catalogs),
      max_products:    toInt(data.default_max_products),
    }
  } catch { /* keep hardcoded fallback */ }
}

function resetForm() {
  form.value = { id: null, name: '', email: '', phone: '', password: '', role_id: roles.value[0]?.id || null, status: 'active', max_restaurants: defaults.value.max_restaurants, max_catalogs: defaults.value.max_catalogs, max_products: defaults.value.max_products }
}

function openCreateModal() {
  isEditing.value = false
  formError.value = null
  resetForm()
  // Always create as admin
  const adminRole = roles.value.find(r => r.name === 'admin')
  if (adminRole) form.value.role_id = adminRole.id
  showFormModal.value = true
}

function openEditModal(user) {
  isEditing.value = true
  formError.value = null
  form.value = {
    id: user.id,
    name: user.name || '',
    email: user.email || '',
    phone: user.phone || '',
    password: '',
    role_id: user.role?.id || null,
    status: user.status || 'active',
    max_restaurants: user.max_restaurants ?? null,
    max_catalogs: user.max_catalogs ?? null,
    max_products: user.max_products ?? null,
  }
  showFormModal.value = true
}

function closeFormModal() { showFormModal.value = false; resetForm() }

async function saveUser() {
  isSaving.value = true
  formError.value = null
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      phone: form.value.phone || null,
      role_id: form.value.role_id,
      status: form.value.status,
      max_restaurants: form.value.max_restaurants === '' ? null : form.value.max_restaurants,
      max_catalogs:    form.value.max_catalogs    === '' ? null : form.value.max_catalogs,
      max_products:    form.value.max_products    === '' ? null : form.value.max_products,
    }

    if (form.value.password) {
      payload.password = form.value.password
    }

    if (isEditing.value) {
      await api.put(`/users/${form.value.id}`, payload)
      showToast('Usuario actualizado')
    } else {
      if (!form.value.password) {
        formError.value = 'La contraseña es obligatoria para nuevos usuarios'
        return
      }
      payload.password = form.value.password
      await api.post('/users', payload)
      showToast('Usuario creado')
    }

    closeFormModal()
    await fetchUsers()
  } catch (err) {
    formError.value = err?.message || 'Error al guardar usuario'
  } finally {
    isSaving.value = false
  }
}

function openDeleteModal(user) {
  userToDelete.value = user
  deleteConfirmed.value = false
  showDeleteModal.value = true
}

function closeDeleteModal() {
  showDeleteModal.value = false
  userToDelete.value = null
}

async function confirmDelete() {
  if (!deleteConfirmed.value || !userToDelete.value) return
  isDeleting.value = true
  try {
    await api.delete(`/users/${userToDelete.value.id}`)
    showToast('Usuario eliminado')
    closeDeleteModal()
    await fetchUsers()
  } catch (err) {
    showToast(err.message || 'Error al eliminar', 'error')
  } finally {
    isDeleting.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchRoles(), fetchDefaults()])
  await fetchUsers()
})
</script>

<style scoped>
.users-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

.toast {
  position: fixed; top: 1rem; right: 1rem; z-index: 1000;
  padding: 0.85rem 1.5rem; border-radius: 10px; font-weight: 600;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15); animation: slideIn 0.3s;
}
.toast-success { background: #dcfce7; color: #166534; }
.toast-error { background: #fef2f2; color: #dc2626; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.header h1 { font-size: 2rem; color: #1e293b; margin: 0; }
.header-actions { display: flex; gap: 0.75rem; align-items: center; }

.btn-settings {
  padding: 0.75rem 1.25rem; background: white; color: #475569;
  border: 1.5px solid #e2e8f0; border-radius: 10px; font-weight: 600;
  cursor: pointer; font-size: 0.95rem; text-decoration: none; display: inline-flex; align-items: center;
}
.btn-settings:hover { background: #f8fafc; border-color: #cbd5e1; }

.btn-create {
  padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1rem;
}
.btn-create:hover { opacity: 0.9; }

.search-bar {
  display: flex; align-items: center; background: white; border: 1.5px solid #e2e8f0;
  border-radius: 12px; padding: 0 1rem; margin-bottom: 1.5rem;
}
.search-icon { font-size: 1rem; margin-right: 0.5rem; color: #94a3b8; }
.search-input { flex: 1; border: none; outline: none; padding: 0.75rem 0; font-size: 0.95rem; background: transparent; }
.search-clear { background: none; border: none; cursor: pointer; font-size: 1rem; color: #94a3b8; padding: 0.25rem; }
.search-clear:hover { color: #475569; }

.loading, .error { text-align: center; padding: 3rem; color: #64748b; font-size: 1.1rem; }
.error { color: #dc2626; background: #fef2f2; padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; }

.empty-state { text-align: center; padding: 4rem 2rem; color: #64748b; }
.empty-state h2 { font-size: 1.5rem; margin-bottom: 0.5rem; }

.users-table {
  width: 100%; border-collapse: collapse; background: white;
  border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.users-table th {
  background: #f8fafc; padding: 0.85rem 1rem; text-align: left;
  font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;
  border-bottom: 2px solid #e2e8f0;
}
.users-table td {
  padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; color: #334155;
}
.users-table tr:hover td { background: #f8fafc; }

.cell-name { font-weight: 600; }
.cell-actions { display: flex; gap: 0.4rem; }

.role-badge {
  padding: 0.25rem 0.6rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600;
}
.role-superadmin { background: #fef3c7; color: #92400e; }
.role-admin { background: #dbeafe; color: #1e40af; }

.status-badge {
  padding: 0.25rem 0.6rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600;
}
.status-active { background: #dcfce7; color: #166534; }
.status-inactive { background: #f1f5f9; color: #64748b; }
.status-suspended { background: #fef2f2; color: #dc2626; }

.btn-action {
  padding: 0.4rem 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px;
  background: white; cursor: pointer; font-size: 0.85rem; transition: all 0.2s;
}
.btn-action:hover { background: #f8fafc; }
.btn-action.btn-danger:hover { background: #fef2f2; border-color: #fca5a5; }
.btn-action:disabled { opacity: 0.4; cursor: not-allowed; }

.cell-restaurants { max-width: 220px; }
.restaurant-badge {
  display: inline-block; padding: 0.2rem 0.55rem; margin: 0.1rem 0.2rem 0.1rem 0;
  background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;
  border-radius: 50px; font-size: 0.78rem; font-weight: 600; white-space: nowrap;
  max-width: 140px; overflow: hidden; text-overflow: ellipsis; vertical-align: middle;
}
.no-restaurants { color: #cbd5e1; }

.role-fixed-badge {
  display: inline-block; padding: 0.35rem 0.8rem; background: #dbeafe; color: #1e40af;
  border-radius: 50px; font-size: 0.85rem; font-weight: 700; text-transform: capitalize;
}
.role-hint { font-size: 0.8rem; color: #94a3b8; margin-top: 0.3rem; display: block; }

/* Modals */
.modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal {
  background: white; border-radius: 16px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; }
.modal-header h2 { margin: 0; font-size: 1.3rem; color: #1e293b; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
.modal-body { padding: 1.5rem; }

.form-group { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 1rem; }
.form-group label { font-weight: 600; font-size: 0.9rem; color: #334155; }
.form-group input, .form-group select {
  padding: 0.6rem 0.8rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem;
}
.form-group input:focus, .form-group select:focus { border-color: #667eea; outline: none; }

.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.btn-cancel { padding: 0.6rem 1.2rem; background: #f1f5f9; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-save { padding: 0.6rem 1.2rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-save:disabled, .btn-cancel:disabled { opacity: 0.6; cursor: not-allowed; }

.modal-delete .modal-body p { margin: 0 0 0.75rem; color: #475569; }
.warning-text { color: #dc2626; font-size: 0.9rem; font-weight: 600; }
.delete-confirm-check { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.9rem; cursor: pointer; }
.btn-delete-confirm { padding: 0.6rem 1.2rem; background: #dc2626; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.cell-limits { white-space: nowrap; }
.limit-item {
  display: inline-block; padding: 0.2rem 0.5rem; margin: 0.1rem 0.15rem 0.1rem 0;
  background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
  font-size: 0.8rem; color: #475569; font-weight: 600;
}

.limits-section {
  background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px;
  padding: 1rem; margin-bottom: 1rem;
}
.limits-title { font-weight: 700; font-size: 0.9rem; color: #334155; margin-bottom: 0.2rem; }
.limits-hint { font-size: 0.8rem; color: #94a3b8; display: block; margin-bottom: 0.75rem; }
.limits-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
.limits-grid .form-group { margin-bottom: 0; }

.btn-delete-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

.pagination {
  display: flex; align-items: center; justify-content: center;
  gap: 1rem; padding: 1.25rem; border-top: 1px solid #f1f5f9;
}
.page-btn {
  padding: 0.5rem 1rem; background: white; border: 1.5px solid #e2e8f0;
  border-radius: 8px; cursor: pointer; font-weight: 600; color: #475569;
  transition: all 0.2s;
}
.page-btn:hover:not(:disabled) { background: #f8fafc; border-color: #667eea; color: #667eea; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.9rem; color: #64748b; }
</style>
