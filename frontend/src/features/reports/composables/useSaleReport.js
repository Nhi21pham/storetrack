import { ref, computed, watch } from 'vue'
import { fetchSaleReport, fetchSaleReportByBusiness } from '@/features/reports/services/reportService'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { usePersistentRef } from '@/composables/usePersistentRef'
import { matchesTagFilter, buildTagOptions } from '@/utils/tagFilter'

const NUMERIC_KEYS = new Set(['quantity', 'unit_price', 'total_sale'])

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
    (row.customer_name || '').toLowerCase().includes(q) ||
    (row.invoice_code || '').toLowerCase().includes(q) ||
    (row.purchase_invoice_code || '').toLowerCase().includes(q) ||
    (row.store_name || '').toLowerCase().includes(q)
  )
}

export const useSaleReport = ({ currentStore, currentBusiness, scope, onError }) => {
  const rows            = ref([])
  const loading         = ref(false)
  const searchQuery     = usePersistentRef('sale-report:search', '')
  const customerFilters = usePersistentRef('sale-report:customers', [])
  const storeFilters    = usePersistentRef('sale-report:stores', [])
  const tagFilter       = usePersistentRef('sale-report:tags', [])
  const minQty          = usePersistentRef('sale-report:minQty', '')
  const maxQty          = usePersistentRef('sale-report:maxQty', '')
  const startDate       = usePersistentRef('sale-report:startDate', '')
  const endDate         = usePersistentRef('sale-report:endDate', '')

  const isBusinessScope = computed(() => scope?.value === 'business')

  const hasActiveFilters = computed(() =>
    !!(customerFilters.value.length || storeFilters.value.length || tagFilter.value.length ||
       minQty.value !== '' || maxQty.value !== '' || startDate.value || endDate.value),
  )

  const clearFilters = () => {
    customerFilters.value = []
    storeFilters.value = []
    tagFilter.value = []
    minQty.value = ''
    maxQty.value = ''
    startDate.value = ''
    endDate.value = ''
  }

  const baseRows = computed(() => {
    let list = rows.value

    if (customerFilters.value.length) {
      list = list.filter((r) => customerFilters.value.includes(String(r.customer_party_id)))
    }
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

  const sort = useSortCriteria('sale-report')
  const sortedRows = computed(() => sort.sortItems(filteredRows.value, getSortValue))

  // Distinct customers present in the loaded rows, for the column filter.
  const customerOptions = computed(() => {
    const seen = new Map()
    for (const r of rows.value) {
      const id = r.customer_party_id != null ? String(r.customer_party_id) : null
      if (id && !seen.has(id)) seen.set(id, r.customer_name || `#${id}`)
    }
    return Array.from(seen, ([value, label]) => ({ value, label }))
  })

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
    let totalSale = 0
    for (const r of list) {
      totalQty += Number(r.quantity) || 0
      totalSale += Number(r.total_sale) || 0
    }
    return { lineCount: list.length, totalQty, totalSale }
  })

  const load = async () => {
    const businessScope = isBusinessScope.value
    const id = businessScope ? currentBusiness?.value?.id : currentStore.value?.id
    if (!id) return

    loading.value = true
    try {
      rows.value = businessScope
        ? await fetchSaleReportByBusiness({ businessId: id })
        : await fetchSaleReport({ storeId: id })
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
    rows, loading, searchQuery, customerFilters, storeFilters, tagFilter, minQty, maxQty, startDate, endDate,
    hasActiveFilters, clearFilters,
    baseRows, filteredRows, sortedRows, customerOptions, storeOptions, tagOptions, totals,
    sort, load,
  }
}
