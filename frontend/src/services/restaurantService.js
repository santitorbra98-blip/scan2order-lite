import { api } from './api.js'

export const restaurantService = {
  getAll(page = 1) {
    return api.get(`/restaurants?page=${page}`)
  },

  getUsers(page = 1) {
    return api.get(`/users?page=${page}`)
  },

  getInsights(restaurantId, period = '30d') {
    return api.get(`/analytics/restaurants/${restaurantId}/insights?period=${period}`)
  },

  create(formData) {
    return api.upload('/restaurants', formData)
  },

  update(restaurantId, formData) {
    return api.upload(`/restaurants/${restaurantId}`, formData)
  },

  assignOwner(restaurantId, userId) {
    return api.post(`/restaurants/${restaurantId}/assign-owner`, { user_id: userId })
  },

  remove(restaurantId) {
    return api.delete(`/restaurants/${restaurantId}`)
  },
}
