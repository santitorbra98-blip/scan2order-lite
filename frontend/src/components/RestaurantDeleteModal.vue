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
