import { ref, onBeforeUnmount } from 'vue'
import { fetchImportHistory } from '@/features/imports/services/importHistoryService'

const REFRESH_MS = 3000

// Loads a scope's import history for one entity type (server-side paginated).
// `scope` is 'store' or 'business'; `scopeId` is the matching id ref. While any
// row is still pending/processing it quietly re-polls so the board reflects the
// background job's progress without a manual refresh.
export const useImportHistory = ({ scope = 'store', scopeId, type }) => {
  const loading = ref(true)
  const refreshing = ref(false)
  const error = ref('')
  const rows = ref([])
  const page = ref(1)
  const perPage = ref(20)
  const total = ref(0)
  const lastPage = ref(1)
  const startDate = ref('')
  const endDate = ref('')

  let timer = null
  const stopAuto = () => {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
  }
  onBeforeUnmount(stopAuto)

  const hasInProgress = () =>
    rows.value.some((r) => r.status === 'pending' || r.status === 'processing')

  const scheduleAuto = () => {
    stopAuto()
    if (hasInProgress()) {
      timer = setTimeout(() => load(page.value, { silent: true }), REFRESH_MS)
    }
  }

  const load = async (toPage = page.value, { silent = false } = {}) => {
    if (silent) refreshing.value = true
    else loading.value = true
    error.value = ''
    try {
      const data = await fetchImportHistory({
        scope,
        scopeId: scopeId.value,
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
      scheduleAuto()
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
