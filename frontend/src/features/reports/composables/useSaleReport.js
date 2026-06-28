import { ref, computed, watch } from 'vue'
import { fetchSaleReport, fetchSaleReportByBusiness } from '@/features/reports/services/reportService'
import { useSortCriteria } from '@/composables/useSortCriteria'

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
  const rows           = ref([])
  const loading        = ref(false)
  const searchQuery    = ref('')
  const customerFilter = ref('')
  const storeFilter    = ref('')
  const tagFilter      = ref('')
  const minQty         = ref('')
  const maxQty         = ref('')
  const startDate      = ref('')
  const endDate        = ref('')

  const isBusinessScope = computed(() => scope?.value === 'business')

  const hasActiveFilters = computed(() =>
    !!(customerFilter.value || storeFilter.value || tagFilter.value || minQty.value !== '' || maxQty.value !== '' ||
       startDate.value || endDate.value),
  )

  const clearFilters = () => {
    customerFilter.value = ''
    storeFilter.value = ''
    tagFilter.value = ''
    minQty.value = ''
    maxQty.value = ''
    startDate.value = ''
    endDate.value = ''
  }

  const baseRows = computed(() => {
    let list = rows.value

    if (customerFilter.value) {
      list = list.filter((r) => String(r.customer_party_id) === customerFilter.value)
    }
    if (storeFilter.value) {
      list = list.filter((r) => String(r.store_id) === storeFilter.value)
    }
    if (tagFilter.value) {
      const [kind, id] = tagFilter.value.split(':')
      list = list.filter((r) => {
        const tags = r.tags || []
        if (kind === 'tag') return tags.some((t) => String(t.tag_id) === id)
        if (kind === 'val') return tags.some((t) => String(t.tag_value_id) === id)
        return true
      })
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

  const sort = useSortCriteria()
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

  // Tags present in the loaded rows: each tag offers an "(any)" option plus one
  // per distinct value. Filter value is "tag:<id>" or "val:<id>" (mirrors products).
  const tagOptions = computed(() => {
    const byTag = new Map()
    for (const r of rows.value) {
      for (const t of (r.tags || [])) {
        if (!byTag.has(t.tag_id)) byTag.set(t.tag_id, { name: t.tag_name, values: new Map() })
        if (t.tag_value_id != null) byTag.get(t.tag_id).values.set(t.tag_value_id, t.value)
      }
    }
    const opts = []
    for (const [tagId, { name, values }] of byTag) {
      opts.push({ value: `tag:${tagId}`, label: `${name} (any)` })
      for (const [valueId, value] of values) {
        opts.push({ value: `val:${valueId}`, label: `${name}: ${value}` })
      }
    }
    return opts
  })

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
    rows, loading, searchQuery, customerFilter, storeFilter, tagFilter, minQty, maxQty, startDate, endDate,
    hasActiveFilters, clearFilters,
    baseRows, filteredRows, sortedRows, customerOptions, storeOptions, tagOptions, totals,
    sort, load,
  }
}
