<template>
  <div v-if="modelValue" class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ isEditing ? 'Editar restaurante' : 'Crear restaurante' }}</h2>
        <button @click="$emit('close')" class="btn-close">×</button>
      </div>
      <form @submit.prevent="handleSubmit" class="modal-body">
        <section class="form-section">
          <h3>Información del restaurante</h3>
          <div class="form-grid">
            <div class="form-group">
              <label for="rfm-name">Nombre:</label>
              <input id="rfm-name" v-model="form.name" type="text" required placeholder="Nombre del restaurante" />
            </div>
            <div class="form-group">
              <label for="rfm-phone">Teléfono:</label>
              <input id="rfm-phone" v-model="form.phone" type="text" placeholder="Teléfono" />
            </div>
          </div>
          <div class="form-group">
            <label for="rfm-address">Dirección:</label>
            <input id="rfm-address" v-model="form.address" type="text" placeholder="Dirección" />
          </div>
          <div class="form-group">
            <label for="rfm-image">Foto del restaurante:</label>
            <input id="rfm-image" ref="fileInput" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="file-input" @change="onFileChange" />
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
            <div v-for="day in days" :key="day.key" class="schedule-row">
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

        <div v-if="error" class="error">{{ error }}</div>

        <div class="form-actions">
          <button type="button" class="btn-cancel" @click="$emit('close')" :disabled="saving">Cancelar</button>
          <button type="submit" class="btn-save" :disabled="saving">
            {{ saving ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear') }}
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
  isEditing: Boolean,
  initial: { type: Object, default: null },
  days: { type: Array, required: true },
  defaultSchedule: { type: Function, required: true },
  saving: Boolean,
  error: { type: String, default: null },
})

const emit = defineEmits(['close', 'save'])

const { inputRef: fileInput, file: imageFile, preview: imagePreview, reset: resetImage, setPreview, handleChange } = useImageField()

const form = reactive({
  id: null, name: '', address: '', phone: '', active: true, schedule: props.defaultSchedule(),
})

watch(() => props.initial, (val) => {
  if (val) {
    form.id = val.id
    form.name = val.name ?? ''
    form.address = val.address ?? ''
    form.phone = val.phone ?? ''
    form.active = Boolean(val.active)
    form.schedule = (val.schedule && Object.keys(val.schedule).length) ? val.schedule : props.defaultSchedule()
    setPreview(val._imagePreview ?? null)
  } else {
    form.id = null
    form.name = ''
    form.address = ''
    form.phone = ''
    form.active = true
    form.schedule = props.defaultSchedule()
    resetImage()
  }
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
