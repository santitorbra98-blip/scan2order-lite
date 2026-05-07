import { api } from './api.js'

export const restaurantService = {
  getAll(page = 1) {
    return api.get(`/restaurants?page=${page}`)
  },

  create(formData) {
    return api.upload('/restaurants', formData)
  },

  update(restaurantId, formData) {
    return api.upload(`/restaurants/${restaurantId}`, formData)
  },

  remove(restaurantId) {
    return api.delete(`/restaurants/${restaurantId}`)
  },
}
