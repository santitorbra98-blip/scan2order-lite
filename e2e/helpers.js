/**
 * Helpers de soporte para los tests E2E.
 *
 * - mailpit: lee el código MFA del servidor de correo de desarrollo (Mailpit).
 * - api:     operaciones directas contra la API (limpieza de datos de test, etc.).
 */

const MAILPIT_URL = 'http://localhost:8025'
const API_URL = 'http://localhost:8080/api'

/**
 * Espera hasta que Mailpit reciba un mensaje para `toAddress` y extrae
 * el código de 6 dígitos del snippet del email.
 *
 * @param {string} toAddress
 * @param {number} [maxWaitMs=10000]
 * @returns {Promise<string>} Código de 6 dígitos
 */
export async function getMfaCodeFromMailpit(toAddress, maxWaitMs = 10_000) {
  const deadline = Date.now() + maxWaitMs
  const email = toAddress.toLowerCase()

  while (Date.now() < deadline) {
    const res = await fetch(`${MAILPIT_URL}/api/v1/messages`)
    const data = await res.json()
    const messages = data.messages ?? []

    const msg = messages.find(
      (m) =>
        Array.isArray(m.To) &&
        m.To.some((t) => t.Address.toLowerCase() === email),
    )

    if (msg) {
      const match = msg.Snippet.match(/\b(\d{6})\b/)
      if (match) return match[1]
    }

    await new Promise((r) => setTimeout(r, 500))
  }

  throw new Error(`No se recibió email MFA para ${toAddress} en ${maxWaitMs}ms`)
}

/**
 * Borra todos los mensajes de Mailpit (útil al inicio de cada test).
 */
export async function clearMailpit() {
  await fetch(`${MAILPIT_URL}/api/v1/messages`, { method: 'DELETE' })
}

/**
 * Elimina el usuario con el email dado llamando a la API de test.
 * Usa las credenciales del superadmin definidas en variables de entorno
 * o los valores por defecto usados para el test de superadmin.
 *
 * @param {string} email
 * @param {string} superadminToken
 */
export async function deleteUserByEmail(email, superadminToken) {
  const listRes = await fetch(`${API_URL}/users`, {
    headers: { Authorization: `Bearer ${superadminToken}` },
  })
  if (!listRes.ok) return

  const users = await listRes.json()
  const list = Array.isArray(users) ? users : users.data ?? []
  const user = list.find(
    (u) => u.email.toLowerCase() === email.toLowerCase(),
  )

  if (user) {
    await fetch(`${API_URL}/users/${user.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${superadminToken}` },
    })
  }
}

/**
 * Obtiene un token Sanctum para el superadmin de test.
 * Las credenciales se leen de las variables de entorno:
 *   SUPERADMIN_EMAIL    (default: superadmin@scan2order.test)
 *   SUPERADMIN_PASSWORD (default: SuperAdmin12345!)
 *
 * Devuelve null si el login falla (superadmin no creado todavía).
 *
 * @returns {Promise<string|null>}
 */
export async function getSuperadminToken() {
  const email = process.env.SUPERADMIN_EMAIL ?? 'superadmin@scan2order.test'
  const password = process.env.SUPERADMIN_PASSWORD ?? 'SuperAdmin12345!'

  const res = await fetch(`${API_URL}/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ login: email, password }),
  })

  if (!res.ok) return null
  const data = await res.json()
  return data.token ?? null
}
