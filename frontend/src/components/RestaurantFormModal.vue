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

const { inputRef: fileInput, file: imageFile, preview: imagePreview, remove: removeFlag, reset: resetImage, setPreview, handleChange, removeSelection } = useImageField()

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
  removeSelection()
}

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
  background: white; border-radius: 16px; width: 100%; max-width: 600px;
  max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9;
}
.modal-header h2 { margin: 0; font-size: 1.3rem; color: #1e293b; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
.modal-body { padding: 1.5rem; }

.form-section { margin-bottom: 1.5rem; }
.form-section h3 {
  font-size: 1rem; color: #475569; margin: 0 0 1rem;
  padding-bottom: 0.5rem; border-bottom: 1px solid #f1f5f9;
}
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

.form-group { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 0.75rem; }
.form-group label { font-weight: 600; font-size: 0.9rem; color: #334155; }
.form-group input[type="text"],
.form-group input[type="time"] {
  padding: 0.6rem 0.8rem; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 0.95rem;
}
.form-group input:focus { border-color: #667eea; outline: none; }
.form-group small { color: #94a3b8; font-size: 0.8rem; }

.file-input { font-size: 0.9rem; }
.image-preview { margin-top: 0.5rem; }
.image-preview img { max-width: 200px; border-radius: 8px; display: block; }
.btn-remove-image {
  display: block; margin-top: 0.4rem; background: none; border: none;
  color: #dc2626; cursor: pointer; font-size: 0.85rem; padding: 0;
}

.checkbox-group label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 500; }

.schedule-grid { display: flex; flex-direction: column; gap: 0.5rem; }
.schedule-row { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.schedule-day-toggle {
  display: flex; align-items: center; gap: 0.4rem;
  min-width: 130px; font-size: 0.9rem; cursor: pointer; font-weight: 500;
}
.time-input { padding: 0.4rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; }
.schedule-closed { color: #94a3b8; font-size: 0.85rem; }

.error { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1rem; }

.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.btn-cancel {
  padding: 0.6rem 1.2rem; background: #f1f5f9; border: none;
  border-radius: 8px; cursor: pointer; font-weight: 600;
}
.btn-save {
  padding: 0.6rem 1.2rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
}
.btn-save:disabled, .btn-cancel:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 640px) {
  .modal { max-height: 95vh; border-radius: 12px; }
  .form-grid { grid-template-columns: 1fr; }
  .schedule-day-toggle { min-width: 110px; }
  .modal-body { padding: 1rem; }
}
</style>
