import { ref, watch } from 'vue'
import { fetchScanHistory } from '@/features/invoices/services/scanHistoryService'
import { loadPerPage, savePerPage } from '@/composables/useClientPagination'

// Loads a store's invoice scan history (server-side paginated) for one invoice
// kind (`type`: 'purchase' | 'sale'). Scans are synchronous, so unlike import
// history there is nothing in-progress to poll — the refresh button reloads the
// current page on demand.
export const useScanHistory = ({ storeId, type }) => {
  const loading = ref(true)
  const refreshing = ref(false)
  const error = ref('')
  const rows = ref([])
  const page = ref(1)
  const perPage = ref(loadPerPage(20))
  watch(perPage, savePerPage)
  const total = ref(0)
  const lastPage = ref(1)
  const startDate = ref('')
  const endDate = ref('')

  const load = async (toPage = page.value, { silent = false } = {}) => {
    if (silent) refreshing.value = true
    else loading.value = true
    error.value = ''
    try {
      const data = await fetchScanHistory({
        storeId: storeId.value,
        type,
        page: toPage,
        perPage: perPage.value,
        startDate: startDate.value,
        endDate: endDate.value,
      })
      rows.value = data.data || []
      total.value = data.total || 0
      lastPage.value = data.last_page || 1
      page.value = data.current_page || toPage
    } catch (err) {
      error.value = err.message
    } finally {
      loading.value = false
      refreshing.value = false
    }
  }

  const goToPage = (p) => load(p)
  const refresh = () => load(page.value, { silent: true })

  return { loading, refreshing, error, rows, page, perPage, total, lastPage, startDate, endDate, load, goToPage, refresh }
}
