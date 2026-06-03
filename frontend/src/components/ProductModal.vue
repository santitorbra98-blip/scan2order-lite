<template>
  <div v-if="modelValue" class="modal-overlay" @click.self="$emit('close')">
    <div class="modal modal-wide">
      <div class="modal-header">
        <h2>{{ editing ? 'Editar producto' : 'Nuevo producto' }}</h2>
        <button @click="$emit('close')" class="btn-close">×</button>
      </div>
      <form @submit.prevent="handleSubmit" class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre:</label>
            <input v-model="form.name" type="text" required placeholder="Nombre del producto" />
          </div>
          <div class="form-group">
            <label>Precio:</label>
            <input v-model.number="form.price" type="number" step="0.01" required placeholder="0.00" />
          </div>
        </div>
        <div class="form-group">
          <label>Descripción:</label>
          <textarea v-model="form.description" placeholder="Descripción del producto"></textarea>
        </div>
        <div class="form-group checkbox-group">
          <label><input v-model="form.isNew" type="checkbox" /> Destacar como "NEW"</label>
        </div>

        <details class="allergens-dropdown">
          <summary>Alérgenos ({{ form.allergens.length }} seleccionados)</summary>
          <div class="allergens-grid">
            <label v-for="a in allergenOptions" :key="a.code" class="allergen-option">
              <input v-model="form.allergens" type="checkbox" :value="a.code" />
              <span>{{ a.symbol }}</span> <span>{{ a.label }}</span>
            </label>
          </div>
        </details>

        <details class="allergens-dropdown">
          <summary>Tipo de alimento ({{ form.dietTags.length }} seleccionados)</summary>
          <div class="allergens-grid">
            <label v-for="d in dietOptions" :key="d.code" class="allergen-option">
              <input v-model="form.dietTags" type="checkbox" :value="d.code" />
              <span>{{ d.symbol }}</span> <span>{{ d.label }}</span>
            </label>
          </div>
        </details>

        <div v-if="canUploadImages" class="form-group">
          <label>🖼️ Imagen del producto:</label>
          <input ref="fileInput" type="file" class="file-input" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" @change="onFileChange" />
          <small>Máx. 5 MB. Formatos: JPEG, PNG, GIF, WEBP.</small>
          <div v-if="currentImageUrl" class="image-preview">
            <img :src="currentImageUrl" alt="Imagen actual" />
            <button type="button" class="btn-remove-image" @click="removeImage">Eliminar imagen</button>
          </div>
          <div v-if="imageError" class="error">{{ imageError }}</div>
        </div>

        <div v-if="error" class="error">{{ error }}</div>
        <div class="form-actions">
          <button type="button" @click="$emit('close')" class="btn-cancel">Cancelar</button>
          <button type="submit" class="btn-save" :disabled="saving">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { useImageField } from '../composables/useImageField'

const props = defineProps({
  modelValue: Boolean,
  editing: { type: Object, default: null },
  allergenOptions: { type: Array, default: () => [] },
  dietOptions: { type: Array, default: () => [] },
  saving: Boolean,
  error: { type: String, default: null },
  canUploadImages: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'save'])

const {
  inputRef: fileInput,
  file: imageFile,
  preview: imagePreview,
  remove: removeFlag,
  reset: resetImage,
  setPreview,
  handleChange,
  removeSelection,
} = useImageField()

const form = reactive({ name: '', description: '', price: 0, isNew: false, allergens: [], dietTags: [], removeImage: false })
const imageError = ref(null)

const currentImageUrl = computed(() => {
  return imagePreview.value
})

async function onFileChange(event) {
  imageError.value = null
  const result = await handleChange(event)
  if (!result.ok) {
    imageError.value = result.error
    return
  }

  form.removeImage = false
}

function removeImage() {
  imageError.value = null
  form.removeImage = true
  removeSelection()
}

watch(() => props.editing, (val) => {
  if (val) {
    form.name = val.name ?? ''
    form.description = val.description ?? ''
    form.price = val.price ?? 0
    form.isNew = Boolean(val.is_new)
    form.allergens = [...(val.allergens ?? [])]
    form.dietTags = [...(val.diet_tags ?? [])]
    form.removeImage = false
    setPreview(val.image ?? null)
  } else {
    form.name = ''
    form.description = ''
    form.price = 0
    form.isNew = false
    form.allergens = []
    form.dietTags = []
    form.removeImage = false
    imageError.value = null
    resetImage()
  }
}, { immediate: true })

function handleSubmit() {
  emit('save', { form: { ...form }, imageFile: imageFile.value, removeImage: removeFlag.value })
}
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal {
  background: white; border-radius: 16px; width: 100%; max-width: 550px;
  max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-wide { max-width: 650px; }
.modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9;
}
.modal-header h2 { margin: 0; font-size: 1.3rem; color: #1e293b; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
.modal-body { padding: 1.5rem; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

.form-group { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 1rem; }
.form-group label { font-weight: 600; font-size: 0.9rem; color: #334155; }
.form-group input, .form-group textarea {
  width: 100%; padding: 0.6rem 0.8rem; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 0.95rem; font-family: inherit;
}
.form-group input:focus, .form-group textarea:focus { border-color: #667eea; outline: none; }
.form-group textarea { min-height: 80px; resize: vertical; }
.form-group small { color: #94a3b8; font-size: 0.8rem; }

.file-input { font-size: 0.9rem; }
.image-preview { margin-top: 0.5rem; }
.image-preview img { max-width: 150px; border-radius: 8px; display: block; }
.btn-remove-image {
  display: block; margin-top: 0.4rem; background: none; border: none;
  color: #dc2626; cursor: pointer; font-size: 0.85rem; padding: 0;
}

.checkbox-group label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 500; }

.allergens-dropdown { margin: 1rem 0; border: 1px solid #e2e8f0; border-radius: 8px; }
.allergens-dropdown summary { padding: 0.75rem 1rem; cursor: pointer; font-weight: 600; color: #475569; }
.allergens-grid {
  padding: 0.75rem 1rem;
  display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.5rem;
}
.allergen-option { display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; cursor: pointer; }

.error { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; margin: 0.75rem 0; }

.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.btn-cancel {
  padding: 0.6rem 1.2rem; background: #f1f5f9; border: none;
  border-radius: 8px; cursor: pointer; font-weight: 600;
}
.btn-save {
  padding: 0.6rem 1.2rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
}
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 640px) {
  .modal { border-radius: 12px; }
  .modal-body { padding: 1rem; }
  .form-grid { grid-template-columns: 1fr; }
  .allergens-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
}
</style>
