import { ref } from 'vue'

const defaultMeta = {
  brand_name: 'Scan2Order',
  company_name: 'PENDIENTE_DE_CONFIGURAR',
  activity_description: 'Software de digitalización de menús para restauración.',
  contact_email: 'legal@tu-dominio.com',
  support_email: 'soporte@tu-dominio.com',
  privacy_email: 'privacidad@tu-dominio.com',
  support_phone: '+34 000 000 000',
  version: '2026-04',
}

let cachedMeta = null
let inFlightPromise = null

async function fetchLegalMeta() {
  if (cachedMeta) return cachedMeta
  if (inFlightPromise) return inFlightPromise

  inFlightPromise = fetch('/api/legal/meta', {
    headers: { Accept: 'application/json' },
  })
    .then(async (response) => {
      if (!response.ok) return defaultMeta
      const data = await response.json().catch(() => ({}))
      cachedMeta = { ...defaultMeta, ...data }
      return cachedMeta
    })
    .catch(() => defaultMeta)
    .finally(() => { inFlightPromise = null })

  return inFlightPromise
}

export function useLegalMeta() {
  const meta = ref(cachedMeta || defaultMeta)
  const loading = ref(false)

  async function load() {
    loading.value = true
    meta.value = await fetchLegalMeta()
    loading.value = false
    return meta.value
  }

  return { meta, loading, load, defaultMeta }
}
