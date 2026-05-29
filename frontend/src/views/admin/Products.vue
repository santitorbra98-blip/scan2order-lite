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
        <button class="btn-import-json" @click="showImportJsonModal = true">📥 Importar JSON</button>
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
    <CatalogModal
      v-model="showCatalogModal"
      :editing="editingCatalog"
      @close="closeCatalogModal"
      @save="saveCatalog"
    />

    <!-- Section Modal -->
    <SectionModal
      v-model="showSectionModal"
      :editing="editingSection"
      @close="closeSectionModal"
      @save="saveSection"
    />

    <!-- Product Modal -->
    <ProductModal
      v-model="showProductModal"
      :editing="editingProduct"
      :allergen-options="ALLERGEN_OPTIONS"
      :diet-options="DIET_OPTIONS"
      :saving="isSavingProduct"
      :error="productFormError"
      @close="closeProductModal"
      @save="saveProduct"
    />

    <!-- QR Modal -->
    <QrPrintModal v-if="showQrModal" :restaurant-id="selectedRestaurantId" :restaurant-name="selectedRestaurantName" @close="showQrModal = false" />

    <!-- Import JSON Modal -->
    <ImportJsonModal
      v-if="showImportJsonModal"
      :restaurant-id="selectedRestaurantId"
      @close="showImportJsonModal = false"
      @import="importFromJson"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { api, getToken } from '../../services/api'
import { catalogService } from '../../services/catalogService'
import { useToast } from '../../composables/useToast'
import { ALLERGEN_OPTIONS, getAllergenMeta } from '../../constants/allergens'
import { DIET_TYPE_OPTIONS as DIET_OPTIONS } from '../../constants/dietTypes'
import QrPrintModal from '../../components/QrPrintModal.vue'
import CatalogModal from '../../components/CatalogModal.vue'
import SectionModal from '../../components/SectionModal.vue'
import ProductModal from '../../components/ProductModal.vue'
import ImportJsonModal from '../../components/ImportJsonModal.vue'

const { toast, showToast } = useToast()

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

// Section modal
const showSectionModal = ref(false)
const editingSection = ref(null)
const sectionCatalog = ref(null)

// Product modal
const showProductModal = ref(false)
const editingProduct = ref(null)
const productCatalog = ref(null)
const productSection = ref(null)
const isSavingProduct = ref(false)
const productFormError = ref(null)

// Import JSON modal
const showImportJsonModal = ref(false)
const isImportingJson = ref(false)

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
    const data = await catalogService.getRestaurantsStats()
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
    const data = await catalogService.getCatalogs(selectedRestaurantId.value)
    catalogs.value = Array.isArray(data) ? data : []
  } catch (err) { showToast(err.message || 'Error al cargar catálogos', 'error') }
  finally { isLoading.value = false }
}

// Catalog CRUD
function openCatalogForm(catalog = null) {
  editingCatalog.value = catalog
  showCatalogModal.value = true
}
function editCatalog(catalog) { openCatalogForm(catalog) }
function closeCatalogModal() { showCatalogModal.value = false; editingCatalog.value = null }

async function saveCatalog(formData) {
  try {
    if (editingCatalog.value) {
      await catalogService.updateCatalog(selectedRestaurantId.value, editingCatalog.value.id, formData)
      showToast('Catálogo actualizado')
    } else {
      await catalogService.createCatalog(selectedRestaurantId.value, formData)
      showToast('Catálogo creado')
    }
    closeCatalogModal()
    await fetchCatalogs()
  } catch (err) { showToast(err.message || 'Error', 'error') }
}

async function deleteCatalog(catalog) {
  if (!confirm(`¿Eliminar catálogo "${catalog.name}"?`)) return
  try {
    await catalogService.deleteCatalog(selectedRestaurantId.value, catalog.id)
    showToast('Catálogo eliminado')
    await fetchCatalogs()
  } catch (err) { showToast(err.message || 'Error', 'error') }
}

// Section CRUD
function openSectionForm(catalog, section = null) {
  sectionCatalog.value = catalog
  editingSection.value = section
  showSectionModal.value = true
}
function editSection(catalog, section) { openSectionForm(catalog, section) }
function closeSectionModal() { showSectionModal.value = false; editingSection.value = null }

async function saveSection(formData) {
  const cId = sectionCatalog.value.id
  try {
    if (editingSection.value) {
      await catalogService.updateSection(selectedRestaurantId.value, cId, editingSection.value.id, formData)
      showToast('Sección actualizada')
    } else {
      await catalogService.createSection(selectedRestaurantId.value, cId, formData)
      showToast('Sección creada')
    }
    closeSectionModal()
    await fetchCatalogs()
  } catch (err) { showToast(err.message || 'Error', 'error') }
}

async function deleteSection(catalog, section) {
  if (!confirm(`¿Eliminar sección "${section.name}"?`)) return
  try {
    await catalogService.deleteSection(selectedRestaurantId.value, catalog.id, section.id)
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
  showProductModal.value = true
}
function editProduct(catalog, section, product) { openProductForm(catalog, section, product) }
function closeProductModal() { showProductModal.value = false; editingProduct.value = null }

async function saveProduct({ form }) {
  isSavingProduct.value = true
  productFormError.value = null
  const cId = productCatalog.value.id
  const sId = productSection.value.id
  try {
    const fd = new FormData()
    fd.append('name', form.name)
    fd.append('description', form.description || '')
    fd.append('price', String(form.price))
    fd.append('is_new', form.isNew ? '1' : '0')
    fd.append('active', '1')
    form.allergens.forEach(a => fd.append('allergens[]', a))
    form.dietTags.forEach(d => fd.append('diet_tags[]', d))

    if (editingProduct.value) {
      fd.append('_method', 'PUT')
      await catalogService.updateProduct(selectedRestaurantId.value, cId, sId, editingProduct.value.id, fd)
      showToast('Producto actualizado')
    } else {
      await catalogService.createProduct(selectedRestaurantId.value, cId, sId, fd)
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
    await catalogService.deleteProduct(selectedRestaurantId.value, catalog.id, section.id, product.id)
    showToast('Producto eliminado')
    await fetchCatalogs()
  } catch (err) { showToast(err.message || 'Error', 'error') }
}

async function importFromJson(jsonData) {
  isImportingJson.value = true
  try {
    await catalogService.importJson(selectedRestaurantId.value, jsonData)
    showToast('Carta importada correctamente')
    showImportJsonModal.value = false
    await fetchCatalogs()
  } catch (err) {
    showToast(err?.data?.message || err.message || 'Error al importar', 'error')
  } finally {
    isImportingJson.value = false
  }
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
.empty-state, .empty-section { text-align: center; padding: 3rem; color: #1e293b; }

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
.btn-clear-search, .btn-export-pdf, .btn-print-qr, .btn-import-json { padding: 0.6rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; background: white; cursor: pointer; font-weight: 600; font-size: 0.9rem; }
.btn-export-pdf { border-color: #667eea; color: #667eea; }
.btn-print-qr { border-color: #667eea; color: #667eea; }
.btn-import-json { border-color: #10b981; color: #10b981; }

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

@media (max-width: 640px) {
  .products-container { padding: 1rem; }
  .header h1 { font-size: 1.3rem; }
  .stats-grid { grid-template-columns: 1fr; gap: 1rem; }
  .tools-row { gap: 0.5rem; }
  .search-input { min-width: 0; width: 100%; }
  .catalog-card { padding: 1rem; }
  .sections-container { margin-left: 0.4rem; padding-left: 0.6rem; }
  .product-item { flex-wrap: wrap; gap: 0.5rem; }
  .product-name { flex: 1 1 calc(100% - 50px - 0.5rem); min-width: 0; }
  .product-price { margin-left: auto; }
  .product-actions { margin-left: auto; }
  .form-grid { grid-template-columns: 1fr; }
}
</style>
