import { api } from './api'

/**
 * Returns a stable session identifier stored in sessionStorage.
 * One UUID per browser tab session — used for unique-visit counting.
 */
function getSessionId() {
  let sid = sessionStorage.getItem('_s2o_sid')
  if (!sid) {
    sid = typeof crypto !== 'undefined' && crypto.randomUUID
      ? crypto.randomUUID()
      : Math.random().toString(36).slice(2) + Date.now().toString(36)
    sessionStorage.setItem('_s2o_sid', sid)
  }
  return sid
}

export const analyticsService = {
  /**
   * Track an analytics event for a restaurant (fire-and-forget).
   * Errors are silently swallowed so tracking never breaks the UI.
   */
  async trackEvent(restaurantId, eventType, metadata = null) {
    try {
      await fetch('/api/analytics/event', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'omit',
        body: JSON.stringify({
          restaurant_id: restaurantId,
          event_type: eventType,
          session_id: getSessionId(),
          ...(metadata ? { metadata } : {}),
        }),
      })
    } catch {
      // Tracking errors must never propagate to the user
    }
  },

  /**
   * GET /api/analytics/ranking?period=all|7d|30d
   * Requires superadmin auth token (handled by `api` client).
   */
  getRanking(period = 'all') {
    return api.get(`/analytics/ranking?period=${period}`)
  },

  /**
   * GET /api/analytics/top-restaurants?period=7d|30d|all
   * Public — no auth required.
   */
  async getTopRestaurants(period = '7d') {
    const res = await fetch(`/api/analytics/top-restaurants?period=${period}`, {
      headers: { Accept: 'application/json' },
      credentials: 'omit',
    })
    if (!res.ok) throw new Error('Error al cargar el ranking de restaurantes')
    return res.json()
  },
}
