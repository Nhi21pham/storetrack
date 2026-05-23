import { ref, computed } from 'vue'
import {
  fetchAccessibleStores,
  deactivateStore,
  reactivateStore,
  deleteStore as deleteStoreRequest,
} from '@/features/stores/services/storeService'

export const useStores = ({ onError }) => {
  const stores      = ref([])
  const loading     = ref(true)
  const searchQuery = ref('')

  const filteredStores = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return stores.value
    return stores.value.filter((s) =>
      s.name.toLowerCase().includes(q) ||
      s.business.name.toLowerCase().includes(q),
    )
  })

  const fetchStores = async () => {
    loading.value = true
    try {
      stores.value = await fetchAccessibleStores()
    } catch (err) {
      onError?.(err.message)
    } finally {
      loading.value = false
    }
  }

  const toggleActive = async (store) => {
    if (store.is_active) await deactivateStore(store.id)
    else                 await reactivateStore(store.id)
    await fetchStores()
  }

  const removeStore = async (id) => {
    await deleteStoreRequest(id)
    await fetchStores()
  }

  return {
    stores, loading, searchQuery, filteredStores,
    fetchStores, toggleActive, removeStore,
  }
}
