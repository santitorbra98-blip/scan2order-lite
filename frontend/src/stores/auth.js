import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiClient, { setToken, getToken } from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(null)
  const initialized = ref(false)
  const isLoading = ref(false)
  const error = ref(null)

  const isAuthenticated = computed(() => !!user.value)
  const userRole = computed(() => {
    if (!user.value) return null
    const rawRole = user.value.role?.name ?? null
    return typeof rawRole === 'string' ? rawRole.trim().toLowerCase() : null
  })

  const hasRole = (role) => userRole.value === role?.trim().toLowerCase()
  const hasAnyRole = (roles) => {
    if (!Array.isArray(roles) || !userRole.value) return false
    return roles.map(r => r.trim().toLowerCase()).includes(userRole.value)
  }

  // All requests go through apiClient which automatically includes:
  //   - Authorization: Bearer <token>
  //   - X-XSRF-TOKEN (CSRF token from cookie)
  // Never use raw fetch() for API calls — it bypasses both protections.

  async function fetchCurrentUser() {
    try {
      const json = await apiClient.get('/me')
      user.value = json?.data ?? json
      return true
    } catch {
      user.value = null
      return false
    }
  }

  async function login(loginVal, password) {
    isLoading.value = true
    error.value = null
    try {
      // Refresh CSRF cookie before every stateful POST
      await apiClient.getCsrfCookie()
      const data = await apiClient.post('/login', { login: loginVal, password })
      user.value = data.user
      token.value = data.token
      setToken(data.token)
    } catch (err) {
      error.value = err.message || 'Login failed'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function register(name, phone, email, password, passwordConfirmation, legal = {}) {
    isLoading.value = true
    error.value = null
    try {
      await apiClient.getCsrfCookie()
      return await apiClient.post('/register', {
        name, phone, email, password,
        password_confirmation: passwordConfirmation ?? password,
        accept_terms: Boolean(legal.acceptTerms),
        accept_privacy: Boolean(legal.acceptPrivacy),
        accept_marketing: Boolean(legal.acceptMarketing),
      })
    } catch (err) {
      error.value = err.message || 'Registration failed'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function verifyRegister(email, code, password, passwordConfirmation) {
    isLoading.value = true
    error.value = null
    try {
      // Ensure CSRF cookie is fresh — the verification form is a new POST
      await apiClient.getCsrfCookie()
      const data = await apiClient.post('/register/verify', {
        email, code, password,
        password_confirmation: passwordConfirmation ?? password,
      })
      user.value = data.user
      token.value = data.token
      setToken(data.token)
      return data
    } catch (err) {
      error.value = err.message || 'Verification failed'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function forgotPassword(email) {
    isLoading.value = true
    error.value = null
    try {
      await apiClient.getCsrfCookie()
      return await apiClient.post('/forgot-password', { email })
    } catch (err) {
      error.value = err.message || 'Error'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function verifyResetCode(email, code) {
    isLoading.value = true
    error.value = null
    try {
      await apiClient.getCsrfCookie()
      return await apiClient.post('/verify-reset-code', { email, code })
    } catch (err) {
      error.value = err.message || 'Código inválido'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function resetPassword(email, code, password, passwordConfirmation) {
    isLoading.value = true
    error.value = null
    try {
      await apiClient.getCsrfCookie()
      return await apiClient.post('/reset-password', {
        email, code, password,
        password_confirmation: passwordConfirmation,
      })
    } catch (err) {
      error.value = err.message || 'Error'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await apiClient.post('/logout')
    } catch { /* ignore network errors on logout */ }
    user.value = null
    token.value = null
    setToken(null)
  }

  async function initFromStorage() {
    if (initialized.value) return
    initialized.value = true
    // Only call /api/me if a token exists — avoids a 401 for guests on every page load
    if (getToken()) {
      await fetchCurrentUser()
    }
  }

  return {
    user, token, initialized, isLoading, error,
    isAuthenticated, userRole,
    hasRole, hasAnyRole,
    fetchCurrentUser, login, register, verifyRegister,
    forgotPassword, verifyResetCode, resetPassword,
    logout, initFromStorage
  }
})
