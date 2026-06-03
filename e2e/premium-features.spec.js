import { test, expect } from '@playwright/test'

const API_URL = 'https://localhost:8443/api'
const SUPERADMIN_EMAIL = process.env.SUPERADMIN_EMAIL ?? 'superadmin@scan2order.test'
const SUPERADMIN_PASSWORDS = [
  process.env.SUPERADMIN_PASSWORD,
  'superadmin1234!',
  'SuperAdmin12345!'
].filter(Boolean)
const ADMIN_PASSWORD = 'Password12345!'
const TS = Date.now()

async function loginWithPasswordCandidates(request, email, passwords) {
  for (const password of passwords) {
    const response = await request.post(`${API_URL}/login`, {
      data: { login: email, password },
    })

    if (response.ok()) {
      const json = await response.json()
      return { token: json.token, user: json.user }
    }
  }

  throw new Error(`No se pudo iniciar sesión con ${email}`)
}

async function createAdminUser(request, superadminToken, { name, email, canUploadImages, canExportPdf }) {
  const rolesResponse = await request.get(`${API_URL}/roles`, {
    headers: { Authorization: `Bearer ${superadminToken}` },
  })
  expect(rolesResponse.ok()).toBeTruthy()
  const rolesPayload = await rolesResponse.json()
  const roles = Array.isArray(rolesPayload) ? rolesPayload : rolesPayload.data ?? []
  const adminRole = roles.find((role) => role.name === 'admin')
  expect(adminRole, 'No se encontró el rol admin').toBeTruthy()

  const response = await request.post(`${API_URL}/users`, {
    headers: { Authorization: `Bearer ${superadminToken}` },
    data: {
      name,
      email,
      phone: null,
      password: ADMIN_PASSWORD,
      role_id: adminRole.id,
      status: 'active',
      max_restaurants: 5,
      max_catalogs: 20,
      max_products: null,
      can_upload_images: canUploadImages,
      can_export_pdf: canExportPdf,
    },
  })

  expect(response.ok()).toBeTruthy()
  const json = await response.json()
  return json.data
}

async function createRestaurantBundle(request, token, restaurantName) {
  const restaurantResponse = await request.post(`${API_URL}/restaurants`, {
    headers: { Authorization: `Bearer ${token}` },
    data: {
      name: restaurantName,
      address: 'Calle Premium 1',
      phone: '600123123',
      active: true,
    },
  })

  expect(restaurantResponse.ok()).toBeTruthy()
  const restaurantJson = await restaurantResponse.json()
  const restaurant = restaurantJson.data

  const catalogResponse = await request.post(`${API_URL}/restaurants/${restaurant.id}/catalogs`, {
    headers: { Authorization: `Bearer ${token}` },
    data: {
      name: 'Carta principal',
      active: true,
      order: 0,
    },
  })

  expect(catalogResponse.ok()).toBeTruthy()
  const catalogJson = await catalogResponse.json()
  const catalog = catalogJson.data

  const sectionResponse = await request.post(
    `${API_URL}/restaurants/${restaurant.id}/catalogs/${catalog.id}/sections`,
    {
      headers: { Authorization: `Bearer ${token}` },
      data: {
        name: 'Entrantes',
        active: true,
        order: 0,
      },
    },
  )

  expect(sectionResponse.ok()).toBeTruthy()

  return restaurant
}

async function openRestaurantInProducts(page, restaurantName) {
  await page.goto('/admin/products')
  await expect(page.locator('.header h1')).toContainText('Gestión de Catálogos y Productos')
  await page.locator('.restaurant-card').filter({ hasText: restaurantName }).click()
  await expect(page.locator('.restaurant-detail')).toBeVisible()
  await expect(page.locator('button.btn-add-product').first()).toBeVisible()
}

async function assertPremiumControls(page, shouldShowPremium) {
  const exportButton = page.locator('.btn-export-pdf')
  if (shouldShowPremium) {
    await expect(exportButton).toBeVisible()
  } else {
    await expect(exportButton).toHaveCount(0)
  }

  await page.locator('button.btn-add-product').first().click()
  await expect(page.locator('.modal')).toBeVisible()

  const imageInput = page.locator('input.file-input')
  if (shouldShowPremium) {
    await expect(imageInput).toBeVisible()
  } else {
    await expect(imageInput).toHaveCount(0)
  }
}

test('premium controls stay hidden for non-premium admins and appear when enabled', async ({ page, request }) => {
  const { token: superadminToken } = await loginWithPasswordCandidates(
    request,
    SUPERADMIN_EMAIL,
    SUPERADMIN_PASSWORDS,
  )

  const adminNoPremium = await createAdminUser(request, superadminToken, {
    name: `Admin Sin Premium ${TS}`,
    email: `admin-no-premium-${TS}@example.com`,
    canUploadImages: false,
    canExportPdf: false,
  })

  const adminPremium = await createAdminUser(request, superadminToken, {
    name: `Admin Premium ${TS}`,
    email: `admin-premium-${TS}@example.com`,
    canUploadImages: true,
    canExportPdf: true,
  })

  const adminNoPremiumLogin = await loginWithPasswordCandidates(request, adminNoPremium.email, [ADMIN_PASSWORD])
  const adminPremiumLogin = await loginWithPasswordCandidates(request, adminPremium.email, [ADMIN_PASSWORD])

  const noPremiumRestaurant = await createRestaurantBundle(request, adminNoPremiumLogin.token, `Restaurante Sin Premium ${TS}`)
  const premiumRestaurant = await createRestaurantBundle(request, adminPremiumLogin.token, `Restaurante Premium ${TS}`)

  await page.addInitScript((token) => {
    localStorage.setItem('auth_token', token)
    sessionStorage.setItem('auth_token', token)
  }, adminNoPremiumLogin.token)
  await openRestaurantInProducts(page, noPremiumRestaurant.name)
  await assertPremiumControls(page, false)

  await page.addInitScript((token) => {
    localStorage.setItem('auth_token', token)
    sessionStorage.setItem('auth_token', token)
  }, adminPremiumLogin.token)
  await openRestaurantInProducts(page, premiumRestaurant.name)
  await assertPremiumControls(page, true)
})