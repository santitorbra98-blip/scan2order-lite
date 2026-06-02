import { ref } from 'vue'
import { api } from '../services/api'

/**
 * Reusable composable for two-step MFA flows:
 * 1. Request a code  → POST requestUrl  with optional payload
 * 2. Confirm the code → POST confirmUrl with form data
 *
 * @param {string} requestUrl  – endpoint to trigger the code email
 * @param {string} confirmUrl  – endpoint to verify the code and execute the change
 */
export function useMfaFlow(requestUrl, confirmUrl) {
  const loading   = ref(false)
  const codeSent  = ref(false)
  const error     = ref(null)

  async function requestCode(payload = {}) {
    loading.value = true
    error.value   = null
    try {
      await api.post(requestUrl, payload)
      codeSent.value = true
    } catch (err) {
      error.value = err?.data?.message || err.message || 'Error al enviar el código'
    } finally {
      loading.value = false
    }
  }

  async function confirmCode(payload = {}) {
    loading.value = true
    error.value   = null
    try {
      const data = await api.post(confirmUrl, payload)
      codeSent.value = false
      return data
    } catch (err) {
      error.value = err?.data?.message || err.message || 'Código incorrecto o expirado'
      throw err
    } finally {
      loading.value = false
    }
  }

  function reset() {
    codeSent.value = false
    error.value    = null
    loading.value  = false
  }

  return { loading, codeSent, error, requestCode, confirmCode, reset }
}
