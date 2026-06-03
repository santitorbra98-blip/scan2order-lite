<template>
  <Transition name="cookie-fade">
    <div v-if="visible" class="cookie-bar" role="note">
      <p>
        Este sitio usa cookies técnicas necesarias para su funcionamiento.
        <router-link to="/legal/cookies">Más información</router-link>
      </p>
      <button @click="dismiss" aria-label="Cerrar aviso de cookies">✕</button>
    </div>
  </Transition>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const STORAGE_KEY = 's2o_cookie_info'
const visible = ref(false)

onMounted(() => {
  if (!sessionStorage.getItem(STORAGE_KEY)) visible.value = true
})

function dismiss() {
  sessionStorage.setItem(STORAGE_KEY, '1')
  visible.value = false
}
</script>

<style scoped>
.cookie-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 0.65rem 1.25rem;
  background: rgba(15, 23, 42, 0.93);
  backdrop-filter: blur(4px);
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.cookie-bar p {
  margin: 0;
  font-size: 0.82rem;
  color: rgba(255, 255, 255, 0.7);
}

.cookie-bar a {
  color: #818cf8;
  text-underline-offset: 2px;
}

.cookie-bar button {
  flex-shrink: 0;
  background: none;
  border: none;
  color: rgba(255, 255, 255, 0.45);
  font-size: 0.9rem;
  cursor: pointer;
  padding: 0 0.25rem;
  line-height: 1;
}

.cookie-bar button:hover {
  color: white;
}

.cookie-fade-enter-active,
.cookie-fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.cookie-fade-enter-from,
.cookie-fade-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
