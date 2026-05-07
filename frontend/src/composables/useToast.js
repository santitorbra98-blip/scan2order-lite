import { ref } from 'vue'

/**
 * Composable reutilizable para notificaciones toast.
 * Reemplaza el patrón toast/toastTimer duplicado en cada vista.
 *
 * @param {number} duration  Milisegundos antes de ocultarse (defecto 2500)
 */
export function useToast(duration = 2500) {
  const toast = ref({ show: false, type: 'success', message: '' })
  let timer = null

  function showToast(message, type = 'success') {
    if (timer) clearTimeout(timer)
    toast.value = { show: true, type, message }
    timer = setTimeout(() => { toast.value.show = false }, duration)
  }

  return { toast, showToast }
}
