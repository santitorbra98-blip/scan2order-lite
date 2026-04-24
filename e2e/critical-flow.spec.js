/**
 * E2E — Flujo crítico de Scan2Order
 *
 * Cubre el recorrido completo de un nuevo usuario en un único flujo:
 *  1. Registro con verificación de email (código MFA vía Mailpit)
 *  2. Login
 *  3. Creación de un restaurante desde el panel admin
 *  4. Visita del menú público del restaurante creado
 *
 * Se ejecuta como un único test para:
 *  - Respetar el rate-limiting del backend (4 registros / 15 min por IP)
 *  - Mantener el contexto de navegador entre pasos (localStorage con token)
 */

import { test, expect } from '@playwright/test'
import { clearMailpit, getMfaCodeFromMailpit } from './helpers.js'

// ─── Datos de test ────────────────────────────────────────────────────────────
const TS = Date.now()
const USER = {
  name: 'E2E Tester',
  email: `e2e_${TS}@example.com`,
  password: 'TestPassword12345!',
}
const RESTAURANT_NAME = `Restaurante E2E ${TS}`

// ─── Test único fluido ────────────────────────────────────────────────────────

test('Flujo crítico completo: registro → email MFA → login → restaurante → menú público', async ({ page }) => {
  // ── PASO 1: Registro ───────────────────────────────────────────────────────
  await test.step('Registro: rellenar formulario y enviar', async () => {
    await clearMailpit()

    await page.goto('/register')
    await expect(page.locator('h1')).toContainText('Crear Cuenta')

    await page.fill('#name', USER.name)
    await page.fill('#email', USER.email)
    await page.fill('#password', USER.password)
    await page.fill('#password_confirmation', USER.password)

    const checkboxes = page.locator('.legal-consents input[type="checkbox"]')
    for (let i = 0; i < await checkboxes.count(); i++) {
      await checkboxes.nth(i).check()
    }

    await page.click('button.btn-submit')

    await expect(page.locator('h2')).toContainText('Verifica tu email', { timeout: 10_000 })
    await expect(page.locator('.verify-step p')).toContainText(USER.email)
  })

  // ── PASO 2: Verificar código MFA ───────────────────────────────────────────
  await test.step('Email MFA: leer código de Mailpit y verificar', async () => {
    const code = await getMfaCodeFromMailpit(USER.email)
    expect(code).toMatch(/^\d{6}$/)

    await page.fill('#code', code)
    await page.click('button.btn-submit')

    // Tras verificar, redirige al panel admin (los nuevos usuarios van a /admin/onboarding)
    await expect(page).toHaveURL(/\/admin/, { timeout: 15_000 })
  })

  // ── PASO 3: Logout y Login ────────────────────────────────────────────────
  await test.step('Login: cerrar sesión y volver a entrar', async () => {
    // Logout via UI: abrir menú de usuario y pulsar "Cerrar sesión"
    await page.click('.user-btn')
    await page.click('.logout-btn')

    // La app redirige a /login tras el logout
    await expect(page).toHaveURL(/\/login/, { timeout: 8_000 })
    await expect(page.locator('h1')).toContainText('Iniciar Sesión')

    await page.fill('#email', USER.email)
    await page.fill('#password', USER.password)
    await page.click('button.btn-submit')

    await expect(page).toHaveURL(/\/admin/, { timeout: 10_000 })
  })

  // ── PASO 4: Crear restaurante ─────────────────────────────────────────────
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

