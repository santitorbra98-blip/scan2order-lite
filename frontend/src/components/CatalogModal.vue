<template>
  <div v-if="modelValue" class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ editing ? 'Editar catálogo' : 'Nuevo catálogo' }}</h2>
        <button @click="$emit('close')" class="btn-close">×</button>
      </div>
      <form @submit.prevent="$emit('save', form)" class="modal-body">
        <div class="form-group">
          <label>Nombre:</label>
          <input v-model="form.name" type="text" required placeholder="Desayuno, Almuerzo..." />
        </div>
        <div class="form-group">
          <label>Descripción:</label>
          <textarea v-model="form.description" placeholder="Descripción opcional"></textarea>
        </div>
        <div class="form-actions">
          <button type="button" @click="$emit('close')" class="btn-cancel">Cancelar</button>
          <button type="submit" class="btn-save">{{ editing ? 'Actualizar' : 'Crear' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  editing: { type: Object, default: null },
})

defineEmits(['close', 'save'])

const form = reactive({ name: '', description: '' })

watch(() => props.editing, (val) => {
  form.name = val?.name ?? ''
  form.description = val?.description ?? ''
}, { immediate: true })
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal {
  background: white; border-radius: 16px; width: 100%; max-width: 500px;
  max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9;
}
.modal-header h2 { margin: 0; font-size: 1.3rem; color: #1e293b; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
.modal-body { padding: 1.5rem; }

.form-group { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 1rem; }
.form-group label { font-weight: 600; font-size: 0.9rem; color: #334155; }
.form-group input, .form-group textarea {
  width: 100%; padding: 0.6rem 0.8rem; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 0.95rem; font-family: inherit;
}
.form-group input:focus, .form-group textarea:focus { border-color: #667eea; outline: none; }
.form-group textarea { min-height: 80px; resize: vertical; }

.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; }
.btn-cancel {
  padding: 0.6rem 1.2rem; background: #f1f5f9; border: none;
  border-radius: 8px; cursor: pointer; font-weight: 600;
}
.btn-save {
  padding: 0.6rem 1.2rem; background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
}

@media (max-width: 640px) {
  .modal { border-radius: 12px; }
  .modal-body { padding: 1rem; }
}
</style>
