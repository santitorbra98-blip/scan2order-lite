import { ref } from 'vue'
import { api } from '../services/api'

/**
 * Reusable composable for two-step MFA flows:
 * 1. Request a code  → POST requestUrl  with optional payload
 * 2. Confirm the code → POST confirmUrl with form data
 *
 * Exposed refs match what Profile.vue expects:
 *   requesting / requestError  — state for step 1
 *   saving     / error         — state for step 2
 *   codeSent                   — toggles the form between steps
 *
 * @param {string} requestUrl  – endpoint to trigger the code email
 * @param {string} confirmUrl  – endpoint to verify the code and execute the change
 */
export function useMfaFlow(requestUrl, confirmUrl) {
  const requesting    = ref(false)
  const saving        = ref(false)
  const codeSent      = ref(false)
  const requestError  = ref(null)
  const error         = ref(null)

  async function requestCode(payload = {}) {
    requesting.value   = true
    requestError.value = null
    try {
      await api.post(requestUrl, payload)
      codeSent.value = true
    } catch (err) {
      requestError.value = err?.data?.message || err.message || 'Error al enviar el código'
    } finally {
      requesting.value = false
    }
  }

  async function confirmCode(payload = {}) {
    saving.value = true
    error.value  = null
    try {
      const data = await api.post(confirmUrl, payload)
      codeSent.value = false
      return data
    } catch (err) {
      error.value = err?.data?.message || err.message || 'Código incorrecto o expirado'
      throw err
    } finally {
      saving.value = false
    }
  }

  function reset() {
    codeSent.value     = false
    requestError.value = null
    error.value        = null
    requesting.value   = false
    saving.value       = false
  }

  return { requesting, saving, codeSent, requestError, error, requestCode, confirmCode, reset }
}
