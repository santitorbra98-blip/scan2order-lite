import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '../services/api'

export const useRestaurantsStore = defineStore('restaurants', () => {
  const restaurants = ref([])
  const loading = ref(false)
  const error = ref(null)

  const fetchRestaurants = async () => {
    loading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get('/restaurants')
      restaurants.value = data || []
    } catch (err) {
      error.value = err.message
      restaurants.value = []
    } finally {
      loading.value = false
    }
  }

  const createRestaurant = async (payload) => {
    try {
      await apiClient.post('/restaurants', payload)
      await fetchRestaurants()
      return true
    } catch (err) {
      error.value = err.message
      return false
    }
  }

  const deleteRestaurant = async (id) => {
    try {
      await apiClient.delete(`/restaurants/${id}`)
      await fetchRestaurants()
      return true
    } catch (err) {
      error.value = err.message
      return false
    }
  }

  return {
    restaurants, loading, error,
    fetchRestaurants, createRestaurant, deleteRestaurant
  }
})
