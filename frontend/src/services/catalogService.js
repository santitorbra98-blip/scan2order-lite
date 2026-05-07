import { api } from './api.js'

export const catalogService = {
  // Restaurants stats (for restaurant selector in Products view)
  getRestaurantsStats() {
    return api.get('/restaurants/stats')
  },

  // Catalogs
  getCatalogs(restaurantId) {
    return api.get(`/restaurants/${restaurantId}/catalogs`)
  },

  createCatalog(restaurantId, data) {
    return api.post(`/restaurants/${restaurantId}/catalogs`, data)
  },

  updateCatalog(restaurantId, catalogId, data) {
    return api.put(`/restaurants/${restaurantId}/catalogs/${catalogId}`, data)
  },

  deleteCatalog(restaurantId, catalogId) {
    return api.delete(`/restaurants/${restaurantId}/catalogs/${catalogId}`)
  },

  // Sections
  createSection(restaurantId, catalogId, data) {
    return api.post(`/restaurants/${restaurantId}/catalogs/${catalogId}/sections`, data)
  },

  updateSection(restaurantId, catalogId, sectionId, data) {
    return api.put(`/restaurants/${restaurantId}/catalogs/${catalogId}/sections/${sectionId}`, data)
  },

  deleteSection(restaurantId, catalogId, sectionId) {
    return api.delete(`/restaurants/${restaurantId}/catalogs/${catalogId}/sections/${sectionId}`)
  },

  // Products
  createProduct(restaurantId, catalogId, sectionId, formData) {
    return api.upload(`/restaurants/${restaurantId}/catalogs/${catalogId}/sections/${sectionId}/products`, formData)
  },

  updateProduct(restaurantId, catalogId, sectionId, productId, formData) {
    return api.upload(`/restaurants/${restaurantId}/catalogs/${catalogId}/sections/${sectionId}/products/${productId}`, formData)
  },

  deleteProduct(restaurantId, catalogId, sectionId, productId) {
    return api.delete(`/restaurants/${restaurantId}/catalogs/${catalogId}/sections/${sectionId}/products/${productId}`)
  },
}
