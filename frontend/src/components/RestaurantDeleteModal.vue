<template>
  <div v-if="modelValue" class="modal-overlay" @click.self="$emit('close')">
    <div class="modal modal-delete">
      <div class="modal-header">
        <h2>Confirmar eliminación</h2>
        <button @click="$emit('close')" class="btn-close">×</button>
      </div>
      <div class="modal-body">
        <p>¿Seguro que quieres eliminar <strong>{{ restaurantName }}</strong>?</p>
        <label class="delete-confirm-check">
          <input v-model="confirmed" type="checkbox" />
          Sí, deseo eliminar este restaurante
        </label>
        <div class="form-actions">
          <button type="button" @click="$emit('close')" class="btn-cancel" :disabled="deleting">Cancelar</button>
          <button type="button" @click="$emit('confirm')" class="btn-delete-confirm" :disabled="deleting || !confirmed">
            {{ deleting ? 'Eliminando...' : 'Eliminar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  restaurantName: { type: String, default: '' },
  deleting: Boolean,
})

defineEmits(['close', 'confirm'])

const confirmed = ref(false)

watch(() => props.modelValue, (val) => {
  if (val) confirmed.value = false
})
</script>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal {
  background: white; border-radius: 16px; width: 100%; max-width: 480px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9;
}
.modal-header h2 { margin: 0; font-size: 1.3rem; color: #1e293b; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
.modal-body { padding: 1.5rem; }
.modal-body p { margin: 0 0 1rem; color: #475569; }

.delete-confirm-check {
  display: flex; align-items: center; gap: 0.5rem;
  margin-bottom: 1.25rem; font-size: 0.9rem; cursor: pointer; color: #334155;
}

.form-actions { display: flex; justify-content: flex-end; gap: 0.75rem; }
.btn-cancel {
  padding: 0.6rem 1.2rem; background: #f1f5f9; border: none;
  border-radius: 8px; cursor: pointer; font-weight: 600; color: #475569;
}
.btn-cancel:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-delete-confirm {
  padding: 0.6rem 1.2rem; background: #dc2626; color: white;
  border: none; border-radius: 8px; cursor: pointer; font-weight: 600;
}
.btn-delete-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

@media (max-width: 640px) {
  .modal { border-radius: 12px; }
  .modal-body { padding: 1rem; }
  .form-actions { flex-direction: column-reverse; }
  .btn-cancel, .btn-delete-confirm { width: 100%; text-align: center; padding: 0.7rem 1rem; }
}
</style>
