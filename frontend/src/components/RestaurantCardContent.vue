<template>
  <div>
    <!-- Photo -->
    <div class="restaurant-photo-wrap">
      <img
        v-if="restaurant.image"
        :src="getImageUrl(restaurant)"
        :alt="restaurant.name"
        class="restaurant-photo"
      />
      <div v-else class="restaurant-photo-placeholder">🍽️</div>
    </div>

    <!-- Main info -->
    <div class="restaurant-main">
      <h3 class="restaurant-name">{{ restaurant.name }}</h3>
      <p v-if="restaurant.address" class="restaurant-address">📍 {{ restaurant.address }}</p>
      <p v-if="restaurant.phone" class="restaurant-phone">📞 {{ restaurant.phone }}</p>
      <p class="restaurant-created">📅 Creado: {{ formatDate(restaurant.created_at) }}</p>

      <!-- Schedule -->
      <div v-if="restaurant.schedule && Object.keys(restaurant.schedule).length" class="card-schedule">
        <div class="card-schedule-header">
          <span>🕐 Horario</span>
          <button class="card-schedule-edit" @click="$emit('edit-schedule', restaurant)">Editar</button>
        </div>
        <div class="card-schedule-grid">
          <div
            v-for="day in days"
            :key="day.key"
            class="card-schedule-day"
            :class="restaurant.schedule[day.key]?.enabled ? 'day-open' : 'day-closed'"
          >
            <span class="day-abbr">{{ day.label.slice(0, 2) }}</span>
            <span v-if="restaurant.schedule[day.key]?.enabled" class="day-hours">
              {{ restaurant.schedule[day.key].open }}
            </span>
          </div>
        </div>
      </div>
      <div v-else class="card-schedule">
        <p class="card-schedule-empty">Sin horario configurado.</p>
      </div>
    </div>

    <!-- Status badge -->
    <div class="restaurant-meta">
      <span class="status-badge" :class="restaurant.active ? 'status-active' : 'status-inactive'">
        {{ restaurant.active ? 'Activo' : 'Inactivo' }}
      </span>
    </div>

    <!-- Actions -->
    <div class="restaurant-actions">
      <button class="btn-action" @click="$emit('edit', restaurant)">✏️ Editar</button>
      <button class="btn-action btn-qr" @click="$emit('qr', restaurant)">📷 QR</button>
      <button class="btn-action btn-danger" @click="$emit('delete', restaurant)">🗑️ Eliminar</button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  restaurant:   { type: Object, required: true },
  getImageUrl:  { type: Function, required: true },
  formatDate:   { type: Function, required: true },
  days:         { type: Array,    required: true },
})

defineEmits(['edit', 'edit-schedule', 'qr', 'delete'])
</script>

<style scoped>
.restaurant-photo-wrap { height: 180px; overflow: hidden; }
.restaurant-photo { width: 100%; height: 100%; object-fit: cover; }
.restaurant-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 3rem; background: #f1f5f9; }

.restaurant-main { padding: 1.25rem; }
.restaurant-name { font-size: 1.2rem; color: #1e293b; margin: 0 0 0.5rem; }
.restaurant-address, .restaurant-phone, .restaurant-created { margin: 0.2rem 0; color: #1e293b; font-size: 0.9rem; }

.restaurant-meta { padding: 0 1.25rem; display: flex; gap: 0.5rem; }
.status-badge { padding: 0.3rem 0.8rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600; }
.status-active { background: #dcfce7; color: #166534; }
.status-inactive { background: #fef2f2; color: #dc2626; }

.restaurant-actions { padding: 1rem 1.25rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
.btn-action {
  padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px;
  background: white; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: all 0.2s;
}
.btn-action:hover { background: #f8fafc; border-color: #cbd5e1; }
.btn-action.btn-qr { border-color: #667eea; color: #667eea; }
.btn-action.btn-danger { border-color: #fca5a5; color: #dc2626; }
.btn-action.btn-danger:hover { background: #fef2f2; }

.card-schedule { margin-top: 1rem; padding: 0.75rem; background: #f8fafc; border-radius: 8px; }
.card-schedule-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
.card-schedule-edit { margin-left: auto; background: none; border: none; color: #667eea; cursor: pointer; font-size: 0.85rem; font-weight: 600; }
.card-schedule-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.25rem; }
.card-schedule-day { text-align: center; padding: 0.3rem; border-radius: 6px; font-size: 0.75rem; }
.day-open { background: #dcfce7; }
.day-closed { background: #f1f5f9; color: #94a3b8; }
.day-abbr { display: block; font-weight: 700; }
.day-hours { display: block; font-size: 0.7rem; }
.card-schedule-empty { margin: 0; font-size: 0.85rem; color: #94a3b8; }

@media (max-width: 640px) {
  .restaurant-photo-wrap { height: 160px; }
  .restaurant-main { padding: 1rem; }
  .restaurant-meta { padding: 0 1rem; }
  .restaurant-actions { padding: 0.75rem 1rem; gap: 0.4rem; }
  .btn-action { flex: 1 1 calc(50% - 0.2rem); text-align: center; padding: 0.5rem 0.5rem; font-size: 0.82rem; }
  .card-schedule-grid { grid-template-columns: repeat(4, 1fr); }
}
</style>
