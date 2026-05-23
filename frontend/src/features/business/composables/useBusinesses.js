import { ref, computed } from 'vue'
import {
  fetchAccessibleBusinesses,
  deleteBusiness as deleteBusinessRequest,
} from '@/features/business/services/businessService'

export const useBusinesses = ({ onError }) => {
  const businesses  = ref([])
  const loading     = ref(true)
  const searchQuery = ref('')

  const filteredBusinesses = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()
    if (!query) return businesses.value
    return businesses.value.filter((biz) => biz.name.toLowerCase().includes(query))
  })

  const fetchBusinesses = async () => {
    loading.value = true
    try {
      businesses.value = await fetchAccessibleBusinesses()
    } catch (err) {
      onError?.(err.message)
    } finally {
      loading.value = false
    }
  }

  const removeBusiness = async (id) => {
    await deleteBusinessRequest(id)
    await fetchBusinesses()
  }

  return {
    businesses, loading, searchQuery,
    filteredBusinesses,
    fetchBusinesses, removeBusiness,
  }
}
