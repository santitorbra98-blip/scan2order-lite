const baseURL = '/api'
const REQUEST_TIMEOUT_MS = 30_000
const RETRY_DELAY_MS = 1_000
const TOKEN_KEY = 'auth_token'
const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'OPTIONS']

let _authToken = sessionStorage.getItem(TOKEN_KEY) || localStorage.getItem(TOKEN_KEY) || null

// Migrate old tokens from localStorage to sessionStorage to reduce persistence.
if (_authToken && !sessionStorage.getItem(TOKEN_KEY)) {
  sessionStorage.setItem(TOKEN_KEY, _authToken)
}
localStorage.removeItem(TOKEN_KEY)

function getCsrfToken() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : null
}

function setToken(token) {
  _authToken = token
  if (token) {
    sessionStorage.setItem(TOKEN_KEY, token)
    localStorage.removeItem(TOKEN_KEY)
  } else {
    sessionStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(TOKEN_KEY)
  }
}

function getToken() {
  return _authToken
}

const apiClient = {
  async request(method, endpoint, data = null) {
    let lastError

    for (let attempt = 0; attempt < 2; attempt++) {
      const controller = new AbortController()
      const timeoutId = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS)

      try {
        const options = {
          method,
          credentials: 'include',
          signal: controller.signal,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        }

        const bearerToken = getToken()
        if (bearerToken) {
          options.headers['Authorization'] = `Bearer ${bearerToken}`
        }

        const xsrfToken = getCsrfToken()
        if (xsrfToken) {
          options.headers['X-XSRF-TOKEN'] = xsrfToken
        }

        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
          options.body = JSON.stringify(data)
        }

        const response = await fetch(`${baseURL}${endpoint}`, options)

        if (!response.ok) {
          const error = await response.json().catch(() => ({}))
          const httpError = {
            status: response.status,
            message: error.message || `HTTP ${response.status}`
          }

          if (response.status >= 500 && attempt === 0 && IDEMPOTENT_METHODS.includes(method)) {
            lastError = httpError
            await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS))
            continue
          }

          throw httpError
        }

        const json = await response.json()
        // Pass through full paginated response (has both data + meta).
        // For non-paginated resource responses, unwrap the data array directly.
        if (json !== null && typeof json === 'object' && 'data' in json && !('message' in json)) {
          return 'meta' in json ? json : json.data
        }
        return json
      } catch (error) {
        clearTimeout(timeoutId)

        if (error.name === 'AbortError') {
          const timeoutError = { status: 0, message: 'Request timeout' }
          if (attempt === 0) {
            lastError = timeoutError
            await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS))
            continue
          }
          throw timeoutError
        }

        if (error instanceof TypeError && attempt === 0) {
          lastError = error
          await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS))
          continue
        }

        throw error
      } finally {
        clearTimeout(timeoutId)
      }
    }

    throw lastError
  },

  async getCsrfCookie() {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
  },

  get(endpoint) {
    return this.request('GET', endpoint)
  },
  post(endpoint, data) {
    return this.request('POST', endpoint, data)
  },
  put(endpoint, data) {
    return this.request('PUT', endpoint, data)
  },
  patch(endpoint, data) {
    return this.request('PATCH', endpoint, data)
  },
  delete(endpoint) {
    return this.request('DELETE', endpoint)
  },
  async upload(endpoint, formData) {
    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS)

    try {
      const headers = { 'Accept': 'application/json' }
      const bearerToken = getToken()
      if (bearerToken) headers['Authorization'] = `Bearer ${bearerToken}`
      const xsrfToken = getCsrfToken()
      if (xsrfToken) headers['X-XSRF-TOKEN'] = xsrfToken

      const response = await fetch(`${baseURL}${endpoint}`, {
        method: 'POST',
        credentials: 'include',
        signal: controller.signal,
        headers,
        body: formData
      })

      if (!response.ok) {
        const error = await response.json().catch(() => ({}))
        throw { status: response.status, message: error.message || `HTTP ${response.status}`, data: error }
      }

      const json = await response.json()
      if (json !== null && typeof json === 'object' && 'data' in json && !('message' in json)) {
        return 'meta' in json ? json : json.data
      }
      return json
    } finally {
      clearTimeout(timeoutId)
    }
  }
}

export { apiClient as api, setToken, getToken }
export default apiClient
