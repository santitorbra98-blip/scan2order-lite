<template>
  <div class="products-container">
    <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">{{ toast.message }}</div>

    <div class="header">
      <h1>📋 Gestión de Catálogos y Productos</h1>
      <button v-if="selectedRestaurantId" @click="backToList" class="btn-back">← Volver a lista</button>
    </div>

    <!-- Restaurant List -->
    <div v-if="!selectedRestaurantId" class="restaurants-stats">
      <div v-if="isLoadingStats" class="loading">Cargando restaurantes...</div>
      <div v-else-if="restaurantsStats.length === 0" class="empty-state">
        <p>No hay restaurantes disponibles</p>
      </div>
      <div v-else class="stats-grid">
        <div v-for="r in restaurantsStats" :key="r.id" class="restaurant-card" @click="selectRestaurant(r)">
          <div class="restaurant-card-header">
            <h3>{{ r.name }}</h3>
            <p>📍 {{ r.address }} | ☎️ {{ r.phone }}</p>
          </div>
          <div class="restaurant-stats">
            <div class="stat-item"><span class="stat-label">Catálogos</span><span class="stat-value">{{ r.menus_count }}</span></div>
            <div class="stat-item"><span class="stat-label">Productos</span><span class="stat-value">{{ r.total_products }}</span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Restaurant Detail: Catalogs/Sections/Products -->
    <div v-if="selectedRestaurantId" class="restaurant-detail">
      <div class="restaurant-context">📍 Restaurante: <strong>{{ selectedRestaurantName }}</strong></div>

      <div class="tools-row">
        <input v-model="searchQuery" type="text" class="search-input" placeholder="Buscar en catálogos, secciones y productos..." />
        <button v-if="searchQuery" class="btn-clear-search" @click="searchQuery = ''; fetchCatalogs()">Limpiar</button>
        <button class="btn-export-pdf" :disabled="isExportingPdf" @click="exportMenuPdf">
          {{ isExportingPdf ? 'Generando PDF...' : '⬇️ Exportar PDF' }}
        </button>
        <button class="btn-print-qr" @click="showQrModal = true">📱 Generar QR</button>
      </div>

      <div v-if="isLoading" class="loading">Cargando catálogos...</div>
      <div v-else class="content">
        <div class="catalogs-list">
          <div v-if="catalogs.length === 0" class="empty-section">
            <p>No hay catálogos creados</p>
            <button class="btn-primary" @click="openCatalogForm()">+ Crear primer catálogo</button>
          </div>

          <div v-else-if="filteredCatalogs.length === 0" class="empty-section">
            <p>No hay resultados para la búsqueda</p>
          </div>

          <div v-for="catalog in filteredCatalogs" :key="catalog.id" class="catalog-card">
            <div class="catalog-header">
              <div class="catalog-info">
                <h3>{{ catalog.name }}</h3>
                <p>{{ catalog.description || 'Sin descripción' }}</p>
              </div>
              <div class="catalog-actions">
                <button class="btn-icon" @click="editCatalog(catalog)" title="Editar">✏️</button>
                <button class="btn-icon btn-danger" @click="deleteCatalog(catalog)" title="Eliminar">🗑️</button>
              </div>
            </div>

            <div class="sections-container">
              <div v-if="catalog.sections.length === 0" class="empty-subsection">
                <small>Sin secciones</small>
                <button class="btn-small" @click="openSectionForm(catalog)">+ Sección</button>
              </div>

              <div v-for="section in catalog.sections" :key="section.id" class="section-item">
                <div class="section-header">
                  <h4>{{ section.name }}</h4>
                  <div class="section-actions">
                    <button class="btn-icon" @click="editSection(catalog, section)" title="Editar">✏️</button>
                    <button class="btn-icon btn-danger" @click="deleteSection(catalog, section)" title="Eliminar">🗑️</button>
                  </div>
                </div>

                <div class="products-list">
                  <div v-if="section.products.length === 0" class="empty-products">
                    <small>Sin productos</small>
                    <button class="btn-small" @click="openProductForm(catalog, section)">+ Producto</button>
                  </div>

                  <div v-for="product in section.products" :key="product.id" :class="['product-item', { 'product-item-inactive': !product.active }]">
                    <img v-if="product.image" :src="`/storage/${product.image}`" alt="" class="product-thumbnail" />
                    <div v-else class="product-no-image">📦</div>
                    <div class="product-name">
                      {{ product.name }}
                      <span v-if="product.is_new" class="badge-new">NEW</span>
                      <div v-if="product.allergens?.length" class="product-allergen-badges">
                        <span v-for="code in product.allergens" :key="code" class="allergen-badge" :title="getAllergenMeta(code).label">
                          {{ getAllergenMeta(code).symbol }}
                        </span>
                      </div>
                    </div>
                    <div class="product-price">{{ Number(product.price).toFixed(2) }} €</div>
                    <div class="product-actions">
                      <button class="btn-icon-small" @click="editProduct(catalog, section, product)" title="Editar">✏️</button>
                      <button class="btn-icon-small btn-danger" @click="deleteProduct(catalog, section, product)" title="Eliminar">🗑️</button>
                    </div>
                  </div>

                  <button class="btn-add-product" @click="openProductForm(catalog, section)">+ Agregar producto</button>
                </div>
              </div>

              <button class="btn-add-section" @click="openSectionForm(catalog)">+ Agregar sección</button>
            </div>
          </div>

          <button class="btn-primary btn-large" @click="openCatalogForm()">+ Crear nuevo catálogo</button>
        </div>
      </div>
    </div>

    <!-- Catalog Modal -->
    <div v-if="showCatalogModal" class="modal-overlay" @click.self="closeCatalogModal">
      <div class="modal">
        <div class="modal-header">
          <h2>{{ editingCatalog ? 'Editar catálogo' : 'Nuevo catálogo' }}</h2>
          <button @click="closeCatalogModal" class="btn-close">×</button>
        </div>
        <form @submit.prevent="saveCatalog" class="modal-body">
          <div class="form-group">
            <label>Nombre:</label>
            <input v-model="catalogForm.name" type="text" required placeholder="Desayuno, Almuerzo..." />
          </div>
          <div class="form-group">
            <label>Descripción:</label>
            <textarea v-model="catalogForm.description" placeholder="Descripción opcional"></textarea>
          </div>
          <div class="form-actions">
            <button type="button" @click="closeCatalogModal" class="btn-cancel">Cancelar</button>
            <button type="submit" class="btn-save">{{ editingCatalog ? 'Actualizar' : 'Crear' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Section Modal -->
    <div v-if="showSectionModal" class="modal-overlay" @click.self="closeSectionModal">
      <div class="modal">
        <div class="modal-header">
          <h2>{{ editingSection ? 'Editar sección' : 'Nueva sección' }}</h2>
          <button @click="closeSectionModal" class="btn-close">×</button>
        </div>
        <form @submit.prevent="saveSection" class="modal-body">
          <div class="form-group">
            <label>Nombre:</label>
            <input v-model="sectionForm.name" type="text" required placeholder="Bebidas, Postres..." />
          </div>
          <div class="form-group">
            <label>Descripción:</label>
            <textarea v-model="sectionForm.description" placeholder="Descripción opcional"></textarea>
          </div>
          <div class="form-actions">
            <button type="button" @click="closeSectionModal" class="btn-cancel">Cancelar</button>
            <button type="submit" class="btn-save">{{ editingSection ? 'Actualizar' : 'Crear' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Product Modal -->
    <div v-if="showProductModal" class="modal-overlay" @click.self="closeProductModal">
      <div class="modal modal-wide">
        <div class="modal-header">
          <h2>{{ editingProduct ? 'Editar producto' : 'Nuevo producto' }}</h2>
          <button @click="closeProductModal" class="btn-close">×</button>
        </div>
        <form @submit.prevent="saveProduct" class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label>Nombre:</label>
              <input v-model="productForm.name" type="text" required placeholder="Nombre del producto" />
            </div>
            <div class="form-group">
              <label>Precio:</label>
              <input v-model.number="productForm.price" type="number" step="0.01" required placeholder="0.00" />
            </div>
          </div>
          <div class="form-group">
            <label>Descripción:</label>
            <textarea v-model="productForm.description" placeholder="Descripción del producto"></textarea>
          </div>
          <div class="form-group">
            <label>Imagen (opcional):</label>
            <input ref="productImageInput" type="file" @change="onProductImageChange" accept="image/jpeg,image/png,image/gif,image/webp" class="file-input" />
            <small>Formatos: JPG, PNG, GIF, WEBP. Máximo 5MB.</small>
            <div v-if="productImagePreview" class="image-preview">
              <img :src="productImagePreview" alt="Vista previa" />
              <button type="button" @click="removeProductImage" class="btn-remove-image">✕ Eliminar</button>
            </div>
          </div>
          <div class="form-group checkbox-group">
            <label><input v-model="productForm.isNew" type="checkbox" /> Destacar como "NEW"</label>
          </div>

          <details class="allergens-dropdown">
            <summary>Alérgenos ({{ productForm.allergens.length }} seleccionados)</summary>
            <div class="allergens-grid">
              <label v-for="a in ALLERGEN_OPTIONS" :key="a.code" class="allergen-option">
                <input v-model="productForm.allergens" type="checkbox" :value="a.code" />
                <span>{{ a.symbol }}</span> <span>{{ a.label }}</span>
              </label>
            </div>
          </details>

          <details class="allergens-dropdown">
            <summary>Tipo de alimento ({{ productForm.dietTags.length }} seleccionados)</summary>
            <div class="allergens-grid">
              <label v-for="d in DIET_OPTIONS" :key="d.code" class="allergen-option">
                <input v-model="productForm.dietTags" type="checkbox" :value="d.code" />
                <span>{{ d.symbol }}</span> <span>{{ d.label }}</span>
              </label>
            </div>
          </details>

          <div v-if="productFormError" class="error">{{ productFormError }}</div>
          <div class="form-actions">
            <button type="button" @click="closeProductModal" class="btn-cancel">Cancelar</button>
            <button type="submit" class="btn-save" :disabled="isSavingProduct">
              {{ isSavingProduct ? 'Guardando...' : (editingProduct ? 'Actualizar' : 'Crear') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- QR Modal -->
    <QrPrintModal v-if="showQrModal" :restaurant-id="selectedRestaurantId" :restaurant-name="selectedRestaurantName" @close="showQrModal = false" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { api, getToken } from '../../services/api'
import { useImageField } from '../../composables/useImageField'
import { ALLERGEN_OPTIONS, getAllergenMeta } from '../../constants/allergens'
import { DIET_TYPE_OPTIONS as DIET_OPTIONS } from '../../constants/dietTypes'
import QrPrintModal from '../../components/QrPrintModal.vue'

const {
  inputRef: productImageInput,
  file: productImageFile,
  preview: productImagePreview,
  reset: resetProductImage,
  handleChange: processProductImageChange,
  removeSelection: removeProductImage,
} = useImageField()

const toast = ref({ show: false, type: 'success', message: '' })
let toastTimer = null
function showToast(msg, type = 'success') {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { show: true, type, message: msg }
  toastTimer = setTimeout(() => { toast.value.show = false }, 2500)
}

// State
const restaurantsStats = ref([])
const isLoadingStats = ref(false)
const selectedRestaurantId = ref(null)
const selectedRestaurantName = ref('')
const catalogs = ref([])
const isLoading = ref(false)
const searchQuery = ref('')
const isExportingPdf = ref(false)
const showQrModal = ref(false)

// Catalog modal
const showCatalogModal = ref(false)
const editingCatalog = ref(null)
const catalogForm = ref({ name: '', description: '' })

// Section modal
const showSectionModal = ref(false)
const editingSection = ref(null)
const sectionCatalog = ref(null)
const sectionForm = ref({ name: '', description: '' })

// Product modal
const showProductModal = ref(false)
const editingProduct = ref(null)
const productCatalog = ref(null)
const productSection = ref(null)
const isSavingProduct = ref(false)
const productFormError = ref(null)
const productForm = ref({ name: '', description: '', price: 0, isNew: false, allergens: [], dietTags: [] })

const filteredCatalogs = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return catalogs.value
  return catalogs.value.map(c => {
    const sections = (c.sections || []).map(s => {
      const products = (s.products || []).filter(p => p.name.toLowerCase().includes(q) || (p.description || '').toLowerCase().includes(q))
      return { ...s, products }
    }).filter(s => s.name.toLowerCase().includes(q) || s.products.length > 0)
    return { ...c, sections }
  }).filter(c => c.name.toLowerCase().includes(q) || c.sections.length > 0)
})

// Fetch operations
async function fetchRestaurantsStats() {
  isLoadingStats.value = true
  try {
    const data = await api.get('/restaurants/stats')
    restaurantsStats.value = Array.isArray(data) ? data : []
  } catch { restaurantsStats.value = [] }
  finally { isLoadingStats.value = false }
}

function selectRestaurant(r) {
  selectedRestaurantId.value = r.id
  selectedRestaurantName.value = r.name
  fetchCatalogs()
}

function backToList() {
  selectedRestaurantId.value = null
  selectedRestaurantName.value = ''
  catalogs.value = []
}

async function fetchCatalogs() {
  isLoading.value = true
  try {
    const data = await api.get(`/restaurants/${selectedRestaurantId.value}/catalogs`)
    catalogs.value = Array.isArray(data) ? data : []
  } catch (err) { showToast(err.message || 'Error al cargar catálogos', 'error') }
  finally { isLoading.value = false }
}

// Catalog CRUD
function openCatalogForm(catalog = null) {
  editingCatalog.value = catalog
  catalogForm.value = catalog ? { name: catalog.name, description: catalog.description || '' } : { name: '', description: '' }
  showCatalogModal.value = true
}
function editCatalog(catalog) { openCatalogForm(catalog) }
function closeCatalogModal() { showCatalogModal.value = false; editingCatalog.value = null }

async function saveCatalog() {
  try {
    if (editingCatalog.value) {
      await api.put(`/restaurants/${selectedRestaurantId.value}/catalogs/${editingCatalog.value.id}`, catalogForm.value)
      showToast('Catálogo actualizado')
    } else {
      await api.post(`/restaurants/${selectedRestaurantId.value}/catalogs`, catalogForm.value)
      showToast('Catálogo creado')
    }
    closeCatalogModal()
    await fetchCatalogs()
  } catch (err) { showToast(err.message || 'Error', 'error') }
}

async function deleteCatalog(catalog) {
  if (!confirm(`¿Eliminar catálogo "${catalog.name}"?`)) return
  try {
    await api.delete(`/restaurants/${selectedRestaurantId.value}/catalogs/${catalog.id}`)
    showToast('Catálogo eliminado')
    await fetchCatalogs()
  } catch (err) { showToast(err.message || 'Error', 'error') }
}

// Section CRUD
function openSectionForm(catalog, section = null) {
  sectionCatalog.value = catalog
  editingSection.value = section
  sectionForm.value = section ? { name: section.name, description: section.description || '' } : { name: '', description: '' }
  showSectionModal.value = true
}
function editSection(catalog, section) { openSectionForm(catalog, section) }
function closeSectionModal() { showSectionModal.value = false; editingSection.value = null }

async function saveSection() {
  const cId = sectionCatalog.value.id
  try {
    if (editingSection.value) {
      await api.put(`/restaurants/${selectedRestaurantId.value}/catalogs/${cId}/sections/${editingSection.value.id}`, sectionForm.value)
      showToast('Sección actualizada')
    } else {
      await api.post(`/restaurants/${selectedRestaurantId.value}/catalogs/${cId}/sections`, sectionForm.value)
      showToast('Sección creada')
    }
    closeSectionModal()
    await fetchCatalogs()
  } catch (err) { showToast(err.message || 'Error', 'error') }
}

async function deleteSection(catalog, section) {
  if (!confirm(`¿Eliminar sección "${section.name}"?`)) return
  try {
    await api.delete(`/restaurants/${selectedRestaurantId.value}/catalogs/${catalog.id}/sections/${section.id}`)
    showToast('Sección eliminada')
    await fetchCatalogs()
  } catch (err) { showToast(err.message || 'Error', 'error') }
}

// Product CRUD
function openProductForm(catalog, section, product = null) {
  productCatalog.value = catalog
  productSection.value = section
  editingProduct.value = product
  productFormError.value = null
  resetProductImage()
  if (product) {
    productForm.value = {
      name: product.name, description: product.description || '', price: product.price,
      isNew: Boolean(product.is_new), allergens: [...(product.allergens || [])], dietTags: [...(product.diet_tags || [])],
    }
  } else {
    productForm.value = { name: '', description: '', price: 0, isNew: false, allergens: [], dietTags: [] }
  }
  showProductModal.value = true
}
function editProduct(catalog, section, product) { openProductForm(catalog, section, product) }
function closeProductModal() { showProductModal.value = false; editingProduct.value = null; resetProductImage() }

async function onProductImageChange(event) {
  const result = await processProductImageChange(event)
  if (!result.ok && result.error) showToast(result.error, 'error')
}

async function saveProduct() {
  isSavingProduct.value = true
  productFormError.value = null
  const cId = productCatalog.value.id
  const sId = productSection.value.id
  try {
    const fd = new FormData()
    fd.append('name', productForm.value.name)
    fd.append('description', productForm.value.description || '')
    fd.append('price', String(productForm.value.price))
    fd.append('is_new', productForm.value.isNew ? '1' : '0')
    fd.append('active', '1')
    productForm.value.allergens.forEach(a => fd.append('allergens[]', a))
    productForm.value.dietTags.forEach(d => fd.append('diet_tags[]', d))
    if (productImageFile.value) fd.append('image', productImageFile.value)

    if (editingProduct.value) {
      fd.append('_method', 'PUT')
      await api.upload(`/restaurants/${selectedRestaurantId.value}/catalogs/${cId}/sections/${sId}/products/${editingProduct.value.id}`, fd)
      showToast('Producto actualizado')
    } else {
      await api.upload(`/restaurants/${selectedRestaurantId.value}/catalogs/${cId}/sections/${sId}/products`, fd)
      showToast('Producto creado')
    }
    closeProductModal()
    await fetchCatalogs()
  } catch (err) {
    productFormError.value = err?.data?.message || err.message || 'Error'
  } finally { isSavingProduct.value = false }
}

async function deleteProduct(catalog, section, product) {
  if (!confirm(`¿Eliminar "${product.name}"?`)) return
  try {
    await api.delete(`/restaurants/${selectedRestaurantId.value}/catalogs/${catalog.id}/sections/${section.id}/products/${product.id}`)
    showToast('Producto eliminado')
    await fetchCatalogs()
  } catch (err) { showToast(err.message || 'Error', 'error') }
}

async function exportMenuPdf() {
  isExportingPdf.value = true
  try {
    const headers = { Accept: 'application/pdf' }
    const token = getToken()
    if (token) headers['Authorization'] = `Bearer ${token}`
    const xsrfMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
    if (xsrfMatch) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfMatch[1])
    const response = await fetch(`/api/restaurants/${selectedRestaurantId.value}/catalogs/export-pdf`, {
      credentials: 'include', headers,
    })
    if (!response.ok) throw new Error('Error al generar PDF')
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `menu-${selectedRestaurantName.value}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (err) { showToast(err.message || 'Error al exportar', 'error') }
  finally { isExportingPdf.value = false }
}

onMounted(() => fetchRestaurantsStats())
</script>

<style scoped>
.products-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

.toast { position: fixed; top: 1rem; right: 1rem; z-index: 1000; padding: 0.85rem 1.5rem; border-radius: 10px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,0.15); animation: slideIn 0.3s; }
.toast-success { background: #dcfce7; color: #166534; }
.toast-error { background: #fef2f2; color: #dc2626; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.header h1 { font-size: 1.8rem; color: #1e293b; margin: 0; }
.btn-back { padding: 0.5rem 1rem; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-weight: 600; }

.loading { text-align: center; padding: 3rem; color: #64748b; }
.empty-state, .empty-section { text-align: center; padding: 3rem; color: #64748b; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
.restaurant-card { background: white; border-radius: 12px; padding: 1.5rem; cursor: pointer; box-shadow: 0 2px 12px rgba(0,0,0,0.06); transition: transform 0.2s; }
.restaurant-card:hover { transform: translateY(-3px); }
.restaurant-card-header h3 { margin: 0 0 0.25rem; color: #1e293b; }
.restaurant-card-header p { margin: 0; color: #64748b; font-size: 0.9rem; }
.restaurant-stats { display: flex; gap: 2rem; margin-top: 1rem; }
.stat-item { text-align: center; }
.stat-label { display: block; font-size: 0.8rem; color: #94a3b8; text-transform: uppercase; }
.stat-value { display: block; font-size: 1.5rem; font-weight: 700; color: #667eea; }

.restaurant-context { background: #f0f4ff; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; color: #475569; }

.tools-row { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center; }
.search-input { flex: 1; min-width: 200px; padding: 0.6rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; }
.search-input:focus { border-color: #667eea; outline: none; }
.btn-clear-search, .btn-export-pdf, .btn-print-qr { padding: 0.6rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; background: white; cursor: pointer; font-weight: 600; font-size: 0.9rem; }
.btn-export-pdf { border-color: #667eea; color: #667eea; }
.btn-print-qr { border-color: #667eea; color: #667eea; }

.catalogs-list { display: flex; flex-direction: column; gap: 1.5rem; }

.catalog-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.catalog-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.catalog-info h3 { margin: 0; color: #1e293b; }
.catalog-info p { margin: 0.25rem 0 0; color: #64748b; font-size: 0.9rem; }
.catalog-actions { display: flex; gap: 0.25rem; }

.btn-icon { background: none; border: none; cursor: pointer; font-size: 1.1rem; padding: 0.3rem; border-radius: 6px; }
.btn-icon:hover { background: #f1f5f9; }
.btn-icon.btn-danger:hover { background: #fef2f2; }
.btn-icon-small { background: none; border: none; cursor: pointer; font-size: 0.9rem; padding: 0.2rem; }

.sections-container { margin-left: 1rem; border-left: 2px solid #e2e8f0; padding-left: 1rem; }

.section-item { margin-bottom: 1rem; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.section-header h4 { margin: 0; color: #334155; font-size: 1rem; }
.section-actions { display: flex; gap: 0.25rem; }

.products-list { margin-left: 0.5rem; }
.empty-products, .empty-subsection { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0; color: #94a3b8; }

.product-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem; border-radius: 8px; transition: background 0.15s; }
.product-item:hover { background: #f8fafc; }
.product-item-inactive { opacity: 0.5; }

.product-thumbnail { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; }
.product-no-image { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border-radius: 6px; font-size: 1.1rem; }
.product-name { flex: 1; font-size: 0.95rem; color: #1e293b; }
.badge-new { background: #f59e0b; color: white; font-size: 0.65rem; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 700; margin-left: 0.4rem; vertical-align: middle; }
.product-allergen-badges { display: flex; gap: 0.25rem; margin-top: 0.2rem; flex-wrap: wrap; }
.allergen-badge { font-size: 0.75rem; background: #fef3c7; padding: 0.1rem 0.3rem; border-radius: 4px; }
.product-price { font-weight: 700; color: #667eea; white-space: nowrap; }
.product-actions { display: flex; gap: 0.25rem; }

.btn-small, .btn-add-product, .btn-add-section { background: none; border: 1px dashed #cbd5e1; padding: 0.4rem 0.8rem; border-radius: 6px; color: #667eea; cursor: pointer; font-size: 0.85rem; font-weight: 600; }
.btn-add-product { margin-top: 0.5rem; }
.btn-add-section { margin-top: 0.75rem; }

.btn-primary { padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 1rem; }
.btn-large { display: block; width: 100%; }

/* Modals */
.modal-overlay { position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal { background: white; border-radius: 16px; width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-wide { max-width: 650px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; }
.modal-header h2 { margin: 0; font-size: 1.3rem; color: #1e293b; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
.modal-body { padding: 1.5rem; }

.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-weight: 600; font-size: 0.9rem; color: #334155; margin-bottom: 0.3rem; }
.form-group input, .form-group textarea { width: 100%; padding: 0.6rem 0.8rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; }
.form-group input:focus, .form-group textarea:focus { border-color: #667eea; outline: none; }
.form-group textarea { min-height: 80px; resize: vertical; }
.form-group small { color: #94a3b8; font-size: 0.8rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.file-input { font-size: 0.9rem; }
.image-preview { margin-top: 0.5rem; }
.image-preview img { max-width: 150px; border-radius: 8px; }
.btn-remove-image { display: block; margin-top: 0.5rem; background: none; border: none; color: #dc2626; cursor: pointer; font-size: 0.85rem; }
.checkbox-group label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }

.allergens-dropdown { margin: 1rem 0; border: 1px solid #e2e8f0; border-radius: 8px; }
.allergens-dropdown summary { padding: 0.75rem 1rem; cursor: pointer; font-weight: 600; color: #475569; }
.allergens-grid { padding: 0.75rem 1rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.5rem; }
.allergen-option { display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; cursor: pointer; }

.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.btn-cancel { padding: 0.6rem 1.2rem; background: #f1f5f9; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-save { padding: 0.6rem 1.2rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.error { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; margin: 0.75rem 0; }
</style>
