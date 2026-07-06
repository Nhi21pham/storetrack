import { ref, computed, watch } from 'vue'
import { fetchProfitReport, fetchProfitReportByBusiness } from '@/features/reports/services/reportService'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { usePersistentRef } from '@/composables/usePersistentRef'
import { matchesTagFilter, buildTagOptions } from '@/utils/tagFilter'

const NUMERIC_KEYS = new Set(['quantity', 'unit_cost', 'unit_price', 'revenue', 'cost', 'profit'])

const getSortValue = (row, key) => {
  if (key === 'id') return Number(row.id)
  if (NUMERIC_KEYS.has(key)) return Number(row[key] || 0)
  const value = row[key]
  return value == null ? '' : String(value).toLowerCase()
}

const matchesSearch = (row, query) => {
  const q = query.toLowerCase()
  return (
    (row.product_name || '').toLowerCase().includes(q) ||
    (row.product_code || '').toLowerCase().includes(q) ||
    (row.invoice_code || '').toLowerCase().includes(q) ||
    (row.purchase_invoice_code || '').toLowerCase().includes(q) ||
    (row.store_name || '').toLowerCase().includes(q)
  )
}

export const useProfitReport = ({ currentStore, currentBusiness, scope, onError }) => {
  const rows         = ref([])
  const loading      = ref(false)
  const searchQuery  = usePersistentRef('profit-report:search', '')
  const storeFilters = usePersistentRef('profit-report:stores', [])
  const tagFilter    = usePersistentRef('profit-report:tags', [])
  const minQty       = usePersistentRef('profit-report:minQty', '')
  const maxQty       = usePersistentRef('profit-report:maxQty', '')
  const startDate    = usePersistentRef('profit-report:startDate', '')
  const endDate      = usePersistentRef('profit-report:endDate', '')

  const isBusinessScope = computed(() => scope?.value === 'business')

  const hasActiveFilters = computed(() =>
    !!(storeFilters.value.length || tagFilter.value.length || minQty.value !== '' || maxQty.value !== '' ||
       startDate.value || endDate.value),
  )

  const clearFilters = () => {
    storeFilters.value = []
    tagFilter.value = []
    minQty.value = ''
    maxQty.value = ''
    startDate.value = ''
    endDate.value = ''
  }

  const baseRows = computed(() => {
    let list = rows.value

    if (storeFilters.value.length) {
      list = list.filter((r) => storeFilters.value.includes(String(r.store_id)))
    }
    if (tagFilter.value.length) {
      list = list.filter((r) => matchesTagFilter(r.tags, tagFilter.value))
    }
    if (minQty.value !== '') {
      const min = Number(minQty.value)
      list = list.filter((r) => Number(r.quantity) >= min)
    }
    if (maxQty.value !== '') {
      const max = Number(maxQty.value)
      list = list.filter((r) => Number(r.quantity) <= max)
    }
    // Date range is over the sale date: profit earned in the period.
    if (startDate.value || endDate.value) {
      list = list.filter((r) => {
        const d = String(r.invoice_date).slice(0, 10)
        if (startDate.value && d < startDate.value) return false
        if (endDate.value && d > endDate.value) return false
        return true
      })
    }

    return list
  })

  const filteredRows = computed(() => {
    if (!searchQuery.value.trim()) return baseRows.value
    return baseRows.value.filter((r) => matchesSearch(r, searchQuery.value))
  })

  const sort = useSortCriteria('profit-report')
  const sortedRows = computed(() => sort.sortItems(filteredRows.value, getSortValue))

  // Distinct stores present in the loaded rows, for the column filter (business scope only).
  const storeOptions = computed(() => {
    const seen = new Map()
    for (const r of rows.value) {
      const id = r.store_id != null ? String(r.store_id) : null
      if (id && !seen.has(id)) seen.set(id, r.store_name || `#${id}`)
    }
    return Array.from(seen, ([value, label]) => ({ value, label }))
  })

  const tagOptions = computed(() => buildTagOptions(rows.value))

  // Footer totals over the full filtered set (independent of pagination).
  const totals = computed(() => {
    const list = filteredRows.value
    let totalQty = 0
    let totalRevenue = 0
    let totalCost = 0
    let totalProfit = 0
    for (const r of list) {
      totalQty += Number(r.quantity) || 0
      totalRevenue += Number(r.revenue) || 0
      totalCost += Number(r.cost) || 0
      totalProfit += Number(r.profit) || 0
    }
    return { lineCount: list.length, totalQty, totalRevenue, totalCost, totalProfit }
  })

  const load = async () => {
    const businessScope = isBusinessScope.value
    const id = businessScope ? currentBusiness?.value?.id : currentStore.value?.id
    if (!id) return

    loading.value = true
    try {
      rows.value = businessScope
        ? await fetchProfitReportByBusiness({ businessId: id })
        : await fetchProfitReport({ storeId: id })
    } catch (err) {
      onError?.(err.message)
    } finally {
      loading.value = false
    }
  }

  // Reload whenever the active scope or its selected store/business changes.
  watch(
    [() => scope?.value, () => currentStore.value?.id, () => currentBusiness?.value?.id],
    () => {
      const id = isBusinessScope.value ? currentBusiness?.value?.id : currentStore.value?.id
      if (id) load()
    },
    { immediate: true },
  )

  return {
    rows, loading, searchQuery, storeFilters, tagFilter, minQty, maxQty, startDate, endDate,
    hasActiveFilters, clearFilters,
    baseRows, filteredRows, sortedRows, storeOptions, tagOptions, totals,
    sort, load,
  }
}
