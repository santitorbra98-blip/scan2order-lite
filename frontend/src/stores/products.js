import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '../services/api'

export const useProductsStore = defineStore('products', () => {
  const products = ref([])
  const loading = ref(false)
  const error = ref(null)

  const fetchProducts = async () => {
    loading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get('/products')
      products.value = data || []
    } catch (err) {
      error.value = err.message
      products.value = []
    } finally {
      loading.value = false
    }
  }

  const createProduct = async (payload) => {
    try {
      await apiClient.post('/products', payload)
      await fetchProducts()
      return true
    } catch (err) {
      error.value = err.message
      return false
    }
  }

  const deleteProduct = async (id) => {
    try {
      await apiClient.delete(`/products/${id}`)
      await fetchProducts()
      return true
    } catch (err) {
      error.value = err.message
      return false
    }
  }

  return {
    products, loading, error,
    fetchProducts, createProduct, deleteProduct
  }
})
