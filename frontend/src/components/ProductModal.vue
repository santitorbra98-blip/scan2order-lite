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
        <div class="form-group">
          <label>Imagen (opcional):</label>
          <input ref="fileInput" type="file" @change="onFileChange" accept="image/jpeg,image/png,image/gif,image/webp" class="file-input" />
          <small>Formatos: JPG, PNG, GIF, WEBP. Máximo 5MB.</small>
          <div v-if="imagePreview" class="image-preview">
            <img :src="imagePreview" alt="Vista previa" />
            <button type="button" @click="removeImage" class="btn-remove-image">✕ Eliminar</button>
          </div>
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
import { reactive, ref, watch } from 'vue'
import { useImageField } from '../composables/useImageField'

const props = defineProps({
  modelValue: Boolean,
  editing: { type: Object, default: null },
  allergenOptions: { type: Array, default: () => [] },
  dietOptions: { type: Array, default: () => [] },
  saving: Boolean,
  error: { type: String, default: null },
})

const emit = defineEmits(['close', 'save'])

const { inputRef: fileInput, file: imageFile, preview: imagePreview, reset: resetImage, handleChange } = useImageField()

const form = reactive({ name: '', description: '', price: 0, isNew: false, allergens: [], dietTags: [] })

watch(() => props.editing, (val) => {
  if (val) {
    form.name = val.name ?? ''
    form.description = val.description ?? ''
    form.price = val.price ?? 0
    form.isNew = Boolean(val.is_new)
    form.allergens = [...(val.allergens ?? [])]
    form.dietTags = [...(val.diet_tags ?? [])]
  } else {
    form.name = ''
    form.description = ''
    form.price = 0
    form.isNew = false
    form.allergens = []
    form.dietTags = []
  }
  resetImage()
}, { immediate: true })

async function onFileChange(event) {
  await handleChange(event)
}

function removeImage() {
  resetImage()
}

function handleSubmit() {
  emit('save', { form: { ...form }, imageFile: imageFile.value })
}
</script>
