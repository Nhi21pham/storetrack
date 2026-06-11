import { ref, computed, watch } from 'vue'
import { fetchStockReport } from '@/features/reports/services/reportService'
import { useSortCriteria } from '@/composables/useSortCriteria'

const NUMERIC_KEYS = new Set(['quantity_received', 'quantity_remaining', 'unit_cost', 'total_cost'])

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
    (row.supplier_name || '').toLowerCase().includes(q) ||
    (row.invoice_code || '').toLowerCase().includes(q)
  )
}

export const useStockReport = ({ currentStore, onError }) => {
  const rows              = ref([])
  const loading           = ref(false)
  const searchQuery       = ref('')
  const supplierFilter    = ref('')
  const minQty            = ref('')
  const maxQty            = ref('')
  const includeOutOfStock = ref(false)
  const startDate         = ref('')
  const endDate           = ref('')

  const hasActiveFilters = computed(() =>
    !!(supplierFilter.value || minQty.value !== '' || maxQty.value !== '' ||
       includeOutOfStock.value || startDate.value || endDate.value),
  )

  const clearFilters = () => {
    supplierFilter.value = ''
    minQty.value = ''
    maxQty.value = ''
    includeOutOfStock.value = false
    startDate.value = ''
    endDate.value = ''
  }

  const baseRows = computed(() => {
    let list = rows.value

    if (!includeOutOfStock.value) {
      list = list.filter((r) => Number(r.quantity_remaining) > 0)
    }
    if (supplierFilter.value) {
      list = list.filter((r) => String(r.supplier_party_id) === supplierFilter.value)
    }
    if (minQty.value !== '') {
      const min = Number(minQty.value)
      list = list.filter((r) => Number(r.quantity_remaining) >= min)
    }
    if (maxQty.value !== '') {
      const max = Number(maxQty.value)
      list = list.filter((r) => Number(r.quantity_remaining) <= max)
    }
    if (startDate.value || endDate.value) {
      list = list.filter((r) => {
        const d = String(r.purchase_date).slice(0, 10)
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

  const sort = useSortCriteria()
  const sortedRows = computed(() => sort.sortItems(filteredRows.value, getSortValue))

  // Distinct suppliers present in the loaded rows, for the column filter.
  const supplierOptions = computed(() => {
    const seen = new Map()
    for (const r of rows.value) {
      const id = r.supplier_party_id != null ? String(r.supplier_party_id) : null
      if (id && !seen.has(id)) seen.set(id, r.supplier_name || `#${id}`)
    }
    return Array.from(seen, ([value, label]) => ({ value, label }))
  })

  // Footer totals over the full filtered set (independent of pagination).
  const totals = computed(() => {
    const list = filteredRows.value
    const products = new Set()
    let totalPurchased = 0
    let totalRemaining = 0
    let totalCost = 0
    for (const r of list) {
      products.add(r.product_id)
      totalPurchased += Number(r.quantity_received) || 0
      totalRemaining += Number(r.quantity_remaining) || 0
      totalCost += Number(r.total_cost) || 0
    }
    return { productCount: products.size, totalPurchased, totalRemaining, totalCost }
  })

  const load = async () => {
    if (!currentStore.value?.id) return
    loading.value = true
    try {
      rows.value = await fetchStockReport({ storeId: currentStore.value.id })
    } catch (err) {
      onError?.(err.message)
    } finally {
      loading.value = false
    }
  }

  watch(() => currentStore.value?.id, (id) => {
    if (id) load()
  }, { immediate: true })

  return {
    rows, loading, searchQuery, supplierFilter, minQty, maxQty, includeOutOfStock, startDate, endDate,
    hasActiveFilters, clearFilters,
    baseRows, filteredRows, sortedRows, supplierOptions, totals,
    sort, load,
  }
}
