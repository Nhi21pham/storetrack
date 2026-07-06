import { ref, computed, watch } from 'vue'
import { fetchTopProductsReport, fetchTopProductsReportByBusiness } from '@/features/reports/services/reportService'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { usePersistentRef } from '@/composables/usePersistentRef'
import { matchesTagFilter, buildTagOptions } from '@/utils/tagFilter'

const NUMERIC_KEYS = new Set(['qty_sold', 'revenue', 'profit', 'orders'])

const getSortValue = (row, key) => {
  if (key === 'product_id') return Number(row.product_id)
  if (NUMERIC_KEYS.has(key)) return Number(row[key] || 0)
  const value = row[key]
  return value == null ? '' : String(value).toLowerCase()
}

const matchesSearch = (row, query) => {
  const q = query.toLowerCase()
  return (
    (row.product_name || '').toLowerCase().includes(q) ||
    (row.product_code || '').toLowerCase().includes(q)
  )
}

/**
 * Backs the Top Products report. Rows are server-aggregated per product (one
 * GROUP BY over the date range), so the date range drives a reload; search, the
 * tag filter, the "Rank by" toggle and column sorting all run client-side over
 * the (small) per-product set. The toggle just sets the sort to that metric desc.
 */
export const useTopProductsReport = ({ currentStore, currentBusiness, scope, onError }) => {
  const rows        = ref([])
  const loading     = ref(false)
  const searchQuery = usePersistentRef('top-products-report:search', '')
  const tagFilter   = usePersistentRef('top-products-report:tags', [])
  const startDate   = usePersistentRef('top-products-report:startDate', '')
  const endDate     = usePersistentRef('top-products-report:endDate', '')
  const rankBy      = usePersistentRef('top-products-report:rankBy', 'qty_sold')

  const isBusinessScope = computed(() => scope?.value === 'business')

  const hasActiveFilters = computed(() => !!(tagFilter.value.length || startDate.value || endDate.value))

  const clearFilters = () => {
    tagFilter.value = []
    startDate.value = ''
    endDate.value = ''
  }

  const sort = useSortCriteria()
  // The toggle is a preset: rank the list by the chosen metric, highest first.
  const applyRank = () => { sort.sortCriteria.value = [{ key: rankBy.value, dir: 'desc' }] }
  applyRank()
  watch(rankBy, applyRank)

  const baseRows = computed(() => {
    let list = rows.value
    if (tagFilter.value.length) {
      list = list.filter((r) => matchesTagFilter(r.tags, tagFilter.value))
    }
    return list
  })

  const filteredRows = computed(() => {
    if (!searchQuery.value.trim()) return baseRows.value
    return baseRows.value.filter((r) => matchesSearch(r, searchQuery.value))
  })

  const sortedRows = computed(() => sort.sortItems(filteredRows.value, getSortValue))

  const tagOptions = computed(() => buildTagOptions(rows.value))

  const totals = computed(() => {
    const list = filteredRows.value
    let qty = 0
    let revenue = 0
    let profit = 0
    let orders = 0
    for (const r of list) {
      qty += Number(r.qty_sold) || 0
      revenue += Number(r.revenue) || 0
      profit += Number(r.profit) || 0
      orders += Number(r.orders) || 0
    }
    return { productCount: list.length, qty, revenue, profit, orders }
  })

  const load = async () => {
    const businessScope = isBusinessScope.value
    const id = businessScope ? currentBusiness?.value?.id : currentStore.value?.id
    if (!id) return

    loading.value = true
    try {
      const args = { startDate: startDate.value || undefined, endDate: endDate.value || undefined }
      rows.value = businessScope
        ? await fetchTopProductsReportByBusiness({ businessId: id, ...args })
        : await fetchTopProductsReport({ storeId: id, ...args })
    } catch (err) {
      onError?.(err.message)
    } finally {
      loading.value = false
    }
  }

  // The date range is a server filter, so it reloads (along with scope / store).
  watch(
    [() => scope?.value, () => currentStore.value?.id, () => currentBusiness?.value?.id, startDate, endDate],
    () => {
      const id = isBusinessScope.value ? currentBusiness?.value?.id : currentStore.value?.id
      if (id) load()
    },
    { immediate: true },
  )

  return {
    rows, loading, searchQuery, tagFilter, startDate, endDate, rankBy,
    hasActiveFilters, clearFilters,
    baseRows, filteredRows, sortedRows, tagOptions, totals,
    sort, load,
  }
}
