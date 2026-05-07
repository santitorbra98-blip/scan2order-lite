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
