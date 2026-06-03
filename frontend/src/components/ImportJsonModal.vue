<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <h2>📥 Importar carta desde JSON</h2>
        <button class="btn-close" @click="$emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <!-- Drop zone -->
        <div
          class="drop-zone"
          :class="{ 'drop-zone--over': isDragging, 'drop-zone--loaded': !!parsedData }"
          @dragover.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="onDrop"
          @click="fileInput.click()"
        >
          <input ref="fileInput" type="file" accept=".json,application/json" style="display:none" @change="onFileChange" />

          <template v-if="!parsedData">
            <span class="drop-icon">📄</span>
            <p>Arrastra un archivo <strong>.json</strong> aquí<br />o haz clic para seleccionarlo</p>
            <a class="link-example" :href="exampleUrl" download="example-carta.json" @click.stop>
              ⬇️ Descargar ejemplo JSON
            </a>
          </template>

          <template v-else>
            <span class="drop-icon">✅</span>
            <p><strong>{{ fileName }}</strong> cargado correctamente</p>
            <button class="btn-link" @click.stop="resetFile">Cambiar archivo</button>
          </template>
        </div>

        <!-- Parse error -->
        <p v-if="parseError" class="error-msg">{{ parseError }}</p>

        <!-- Preview -->
        <div v-if="parsedData" class="preview">
          <h3>Vista previa</h3>
          <div class="preview-catalog">
            <div class="preview-catalog-name">
              {{ parsedData.name }}
              <span class="badge-active" :class="parsedData.active !== false ? 'badge-yes' : 'badge-no'">
                {{ parsedData.active !== false ? 'Activo' : 'Inactivo' }}
              </span>
            </div>
            <p v-if="parsedData.description" class="preview-desc">{{ parsedData.description }}</p>

            <div class="preview-stats">
              <span>{{ parsedData.sections?.length ?? 0 }} sección(es)</span>
              <span>{{ totalProducts }} producto(s)</span>
            </div>

            <div v-for="(section, si) in parsedData.sections" :key="si" class="preview-section">
              <div class="preview-section-name">{{ section.name }}</div>
              <div v-for="(product, pi) in section.products" :key="pi" class="preview-product">
                <span class="preview-product-name">{{ product.name }}</span>
                <span class="preview-product-price">{{ Number(product.price ?? 0).toFixed(2) }} €</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="$emit('close')" :disabled="importing">Cancelar</button>
        <button
          class="btn-import"
          :disabled="!parsedData || importing"
          @click="doImport"
        >
          {{ importing ? 'Importando...' : '📥 Importar catálogo' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  restaurantId: { type: [Number, String], required: true },
})

const emit = defineEmits(['close', 'imported'])

const fileInput = ref(null)
const isDragging = ref(false)
const parsedData = ref(null)
const fileName = ref('')
const parseError = ref('')
const importing = ref(false)

// Path to the example file served as a static asset from the docs folder.
// In dev the vite config exposes /docs via the public folder alias; in production
// the file is served by the same static host. Adjust if needed.
const exampleUrl = '/docs/example-carta.json'

const totalProducts = computed(() => {
  if (!parsedData.value?.sections) return 0
  return parsedData.value.sections.reduce((sum, s) => sum + (s.products?.length ?? 0), 0)
})

function parseFile(file) {
  if (!file) return
  fileName.value = file.name
  parseError.value = ''
  parsedData.value = null

  const reader = new FileReader()
  reader.onload = (e) => {
    try {
      const json = JSON.parse(e.target.result)
      if (!json.name) {
        parseError.value = 'El JSON debe tener un campo "name" para el catálogo.'
        return
      }
      parsedData.value = json
    } catch {
      parseError.value = 'El archivo no es un JSON válido.'
    }
  }
  reader.readAsText(file)
}

function onFileChange(e) {
  parseFile(e.target.files[0])
}

function onDrop(e) {
  isDragging.value = false
  parseFile(e.dataTransfer.files[0])
}

function resetFile() {
  parsedData.value = null
  parseError.value = ''
  fileName.value = ''
  if (fileInput.value) fileInput.value.value = ''
}

async function doImport() {
  if (!parsedData.value) return
  importing.value = true
  try {
    emit('import', parsedData.value)
  } finally {
    importing.value = false
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  z-index: 900; padding: 1rem;
}
.modal-box {
  background: white; border-radius: 14px; width: 100%; max-width: 600px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0;
}
.modal-header h2 { margin: 0; font-size: 1.2rem; color: #1e293b; }
.btn-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #1e293b; }
.btn-close:hover { color: #dc2626; }

.modal-body { padding: 1.5rem; }
.modal-footer {
  display: flex; justify-content: flex-end; gap: 0.75rem;
  padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0;
}

/* Drop zone */
.drop-zone {
  border: 2px dashed #cbd5e1; border-radius: 10px;
  padding: 2.5rem 1.5rem; text-align: center; cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}
.drop-zone:hover, .drop-zone--over { border-color: #667eea; background: #f0f4ff; }
.drop-zone--loaded { border-color: #22c55e; background: #f0fdf4; cursor: default; }
.drop-icon { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; }
.drop-zone p { margin: 0.25rem 0; color: #475569; }
.link-example {
  display: inline-block; margin-top: 0.75rem;
  font-size: 0.875rem; color: #667eea; text-decoration: underline;
}
.btn-link {
  background: none; border: none; color: #667eea; cursor: pointer;
  text-decoration: underline; font-size: 0.875rem; margin-top: 0.5rem;
}

.error-msg { color: #dc2626; font-size: 0.875rem; margin-top: 0.5rem; }

/* Preview */
.preview { margin-top: 1.5rem; }
.preview h3 { font-size: 1rem; color: #1e293b; margin: 0 0 0.75rem; }
.preview-catalog {
  background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;
}
.preview-catalog-name { font-weight: 700; font-size: 1rem; color: #1e293b; display: flex; align-items: center; gap: 0.5rem; }
.badge-active { font-size: 0.7rem; padding: 2px 7px; border-radius: 20px; font-weight: 600; }
.badge-yes { background: #dcfce7; color: #166534; }
.badge-no  { background: #fee2e2; color: #991b1b; }
.preview-desc { font-size: 0.85rem; color: #1e293b; margin: 0.25rem 0 0.5rem; }
.preview-stats { display: flex; gap: 1rem; font-size: 0.8rem; color: #667eea; font-weight: 600; margin-bottom: 0.75rem; }
.preview-section { margin-bottom: 0.75rem; }
.preview-section-name {
  font-size: 0.85rem; font-weight: 700; text-transform: uppercase;
  color: #475569; letter-spacing: 0.05em; margin-bottom: 0.25rem;
  border-bottom: 1px solid #e2e8f0; padding-bottom: 0.2rem;
}
.preview-product {
  display: flex; justify-content: space-between;
  font-size: 0.85rem; padding: 0.15rem 0; color: #334155;
}
.preview-product-price { color: #667eea; font-weight: 600; }

/* Footer buttons */
.btn-cancel {
  padding: 0.6rem 1.2rem; border: 1px solid #e2e8f0; border-radius: 8px;
  background: white; cursor: pointer; color: #475569; font-weight: 600;
}
.btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-import {
  padding: 0.6rem 1.4rem; border: none; border-radius: 8px;
  background: #667eea; color: white; font-weight: 700; cursor: pointer;
}
.btn-import:hover:not(:disabled) { background: #5a6fd6; }
.btn-import:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
