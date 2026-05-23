import { ref, computed, watch } from 'vue'
import {
  fetchStoreAuditLogs,
  fetchBusinessAuditLogs,
} from '@/features/audit/services/auditService'
import { PER_PAGE_STORAGE_KEY } from '@/features/audit/constants'

export const useAuditLogs = ({ currentStore, currentBusiness, viewMode, onError }) => {
  const logs         = ref([])
  const loading      = ref(false)
  const fetching     = ref(false)
  const currentPage  = ref(1)
  const lastPage     = ref(1)
  const total        = ref(0)
  const perPage      = ref(Number(localStorage.getItem(PER_PAGE_STORAGE_KEY)) || 20)
  const startDate    = ref('')
  const endDate      = ref('')
  const searchQuery  = ref('')
  const objectFilter = ref('')
  const actionFilter = ref('')

  const hasActiveFilter = computed(() =>
    !!(startDate.value || endDate.value || objectFilter.value || actionFilter.value),
  )

  const buildFilters = (page) => ({
    page,
    per_page: perPage.value,
    start_date:  startDate.value || null,
    end_date:    endDate.value || null,
    object_type: objectFilter.value || null,
    action:      actionFilter.value || null,
    search:      searchQuery.value.trim() || null,
  })

  const exportParams = () => ({
    start_date:  startDate.value || undefined,
    end_date:    endDate.value || undefined,
    object_type: objectFilter.value || undefined,
    action:      actionFilter.value || undefined,
    search:      searchQuery.value.trim() || undefined,
  })

  const fetchLogs = async (page = 1) => {
    if (viewMode.value === 'store'    && !currentStore.value?.id)    return
    if (viewMode.value === 'business' && !currentBusiness.value?.id) return

    if (logs.value.length === 0) loading.value = true
    else fetching.value = true

    try {
      const result = viewMode.value === 'store'
        ? await fetchStoreAuditLogs(currentStore.value.id, buildFilters(page))
        : await fetchBusinessAuditLogs(currentBusiness.value.id, buildFilters(page))

      logs.value        = result.data
      currentPage.value = result.current_page
      lastPage.value    = result.last_page
      total.value       = result.total
    } catch (err) {
      onError?.(err.message)
    } finally {
      loading.value  = false
      fetching.value = false
    }
  }

  const resetAndFetch = () => {
    logs.value         = []
    currentPage.value  = 1
    startDate.value    = ''
    endDate.value      = ''
    searchQuery.value  = ''
    objectFilter.value = ''
    actionFilter.value = ''
    fetchLogs(1)
  }

  const applyFilter = () => {
    currentPage.value = 1
    fetchLogs(1)
  }

  const clearFilter = () => {
    startDate.value    = ''
    endDate.value      = ''
    objectFilter.value = ''
    actionFilter.value = ''
    applyFilter()
  }

  const loadPage = (page) => fetchLogs(page)

  const changePerPage = (val) => {
    perPage.value = val
    localStorage.setItem(PER_PAGE_STORAGE_KEY, val)
    fetchLogs(1)
  }

  let searchDebounce = null
  watch(searchQuery, () => {
    if (searchDebounce) clearTimeout(searchDebounce)
    searchDebounce = setTimeout(() => {
      currentPage.value = 1
      fetchLogs(1)
    }, 300)
  })

  return {
    logs, loading, fetching,
    currentPage, lastPage, total, perPage,
    startDate, endDate, searchQuery, objectFilter, actionFilter,
    hasActiveFilter,
    fetchLogs, resetAndFetch, applyFilter, clearFilter, loadPage, changePerPage,
    exportParams,
  }
}
