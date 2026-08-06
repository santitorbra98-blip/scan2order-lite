/**
 * E2E — Flujo crítico de Scan2Order
 *
 * Cubre el recorrido completo de un cliente en un único flujo:
 *  1. Solicitud de acceso desde el formulario público
 *  2. Login
 *  3. Creación de un restaurante desde el panel admin
 *  4. Visita del menú público del restaurante creado
 *
 * Se ejecuta como un único test para:
 *  - Respetar el rate-limiting del backend (4 registros / 15 min por IP)
 *  - Mantener el contexto de navegador entre pasos (localStorage con token)
 */

import { test, expect } from '@playwright/test'
import { clearMailpit } from './helpers.js'

// ─── Datos de test ────────────────────────────────────────────────────────────
const TS = Date.now()
const USER = {
  name: 'E2E Tester',
  email: `e2e_${TS}@example.com`,
  password: 'TestPassword12345!',
}
const RESTAURANT_NAME = `Restaurante E2E ${TS}`

// ─── Test único fluido ────────────────────────────────────────────────────────

test('Flujo crítico completo: contacto → login → restaurante → menú público', async ({ page }) => {
  // ── PASO 1: Solicitud de acceso ───────────────────────────────────────────
  await test.step('Contacto: rellenar formulario y enviar', async () => {
    await clearMailpit()

    await page.goto('/contacto')
    await expect(page.locator('h1')).toContainText('Cuéntanos quién eres')

    await page.fill('#name', USER.name)
    await page.fill('#email', USER.email)
    await page.fill('#phone', '666 123 456')
    await page.fill('#restaurant_name', RESTAURANT_NAME)
    await page.fill('#message', 'Necesitamos una cuenta para gestionar el menú y el QR de nuestro restaurante.')

    await page.click('button.submit-btn')

    await expect(page.locator('.feedback-success')).toContainText('Hemos recibido tu solicitud', { timeout: 10_000 })
  })

  // ── PASO 2: Login ─────────────────────────────────────────────────────────
  await test.step('Login: cerrar sesión y volver a entrar', async () => {
    await page.goto('/login')
    await expect(page.locator('h1')).toContainText('Iniciar Sesión')

    await page.fill('#email', USER.email)
    await page.fill('#password', USER.password)
    await page.click('button.btn-submit')

    await expect(page).toHaveURL(/\/admin/, { timeout: 10_000 })

    // Logout via UI: abrir menú de usuario y pulsar "Cerrar sesión"
    await page.click('.user-btn')
    await page.click('.logout-btn')

    // La app redirige a /login tras el logout
    await expect(page).toHaveURL(/\/login/, { timeout: 8_000 })
    await expect(page.locator('h1')).toContainText('Iniciar Sesión')
  })

  // ── PASO 3: Crear restaurante ─────────────────────────────────────────────
  await test.step('Admin: crear un nuevo restaurante', async () => {
    await page.goto('/admin/restaurants')
    await expect(page.locator('.header h1')).toContainText('Gestión de Restaurantes')

    await page.click('button.btn-create')
    await expect(page.locator('.modal')).toBeVisible()

    await page.fill('#name', RESTAURANT_NAME)
    await page.fill('#phone', '666 123 456')
    await page.fill('#address', 'Calle Falsa 123, Las Palmas')

    await page.click('button.btn-save')

    await expect(page.locator('.modal')).not.toBeVisible({ timeout: 8_000 })
    await expect(
      page.locator('.restaurant-name').filter({ hasText: RESTAURANT_NAME })
    ).toBeVisible({ timeout: 8_000 })
  })

  // ── PASO 5: Ver menú público ──────────────────────────────────────────────
  await test.step('Menú público: el restaurante es accesible públicamente', async () => {
    // Obtener ID del restaurante via API
    const res = await page.request.get('/api/restaurants')
    expect(res.ok()).toBeTruthy()
    const body = await res.json()
    const list = Array.isArray(body) ? body : body.data ?? []
    const restaurant = list.find((r) => r.name === RESTAURANT_NAME)
    expect(restaurant, `Restaurante "${RESTAURANT_NAME}" no encontrado en la API`).toBeTruthy()

    // Navegar al menú público (sin sesión)
    await page.evaluate(() => localStorage.removeItem('auth_token'))
    await page.goto(`/restaurant/${restaurant.id}`)

    await expect(page.locator('.menu-container')).toBeVisible({ timeout: 8_000 })
    await expect(page.locator('.menu-container h1')).toContainText(RESTAURANT_NAME)
  })
})

