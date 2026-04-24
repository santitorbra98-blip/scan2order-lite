const baseUrl = process.env.SMOKE_BASE_URL
const healthToken = process.env.SMOKE_HEALTH_TOKEN
const login = process.env.SMOKE_LOGIN
const password = process.env.SMOKE_PASSWORD

function assertEnv(name, value) {
  if (!value || !value.trim()) {
    throw new Error(`Missing required env var: ${name}`)
  }
}

async function requestJson(url, options = {}, retries = 2) {
  let lastError

  for (let attempt = 1; attempt <= retries + 1; attempt += 1) {
    try {
      const res = await fetch(url, {
        ...options,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...(options.headers || {}),
        },
      })

      const text = await res.text()
      const body = text ? JSON.parse(text) : null

      if ((res.status === 429 || res.status >= 500) && attempt <= retries) {
        continue
      }

      return { status: res.status, body }
    } catch (err) {
      lastError = err
      if (attempt <= retries) {
        continue
      }
      throw err
    }
  }

  throw lastError || new Error('Unknown request error')
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message)
  }
}

async function main() {
  assertEnv('SMOKE_BASE_URL', baseUrl)
  assertEnv('SMOKE_HEALTH_TOKEN', healthToken)
  assertEnv('SMOKE_LOGIN', login)
  assertEnv('SMOKE_PASSWORD', password)

  const normalizedBase = baseUrl.replace(/\/+$/, '')

  const hello = await requestJson(`${normalizedBase}/api/hello`, { method: 'GET' })
  assert(hello.status === 200, `hello expected 200, got ${hello.status}`)
  assert(hello.body?.message === 'Hello from Laravel', 'hello payload does not match expected message')
  console.log('OK /api/hello')

  const health = await requestJson(`${normalizedBase}/api/health?token=${encodeURIComponent(healthToken)}`, {
    method: 'GET',
  })
  assert(health.status === 200, `health expected 200, got ${health.status}`)
  assert(health.body?.status === 'ok', 'health payload does not contain status=ok')
  console.log('OK /api/health')

  const auth = await requestJson(`${normalizedBase}/api/login`, {
    method: 'POST',
    body: JSON.stringify({ login, password }),
  })
  assert(auth.status === 200, `login expected 200, got ${auth.status}`)
  assert(typeof auth.body?.token === 'string' && auth.body.token.length > 10, 'login did not return a valid token')
  assert(auth.body?.user, 'login did not return user object')
  console.log('OK /api/login')

  console.log('Smoke test completed successfully')
}

main().catch((err) => {
  console.error(`Smoke test failed: ${err.message}`)
  process.exit(1)
})
