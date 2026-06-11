import { ref, computed, watch } from 'vue'
import { fetchSaleReport } from '@/features/reports/services/reportService'
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
    (row.invoice_code || '').toLowerCase().includes(q)
  )
}

export const useSaleReport = ({ currentStore, onError }) => {
  const rows           = ref([])
  const loading        = ref(false)
  const searchQuery    = ref('')
  const customerFilter = ref('')
  const tagFilter      = ref('')
  const minQty         = ref('')
  const maxQty         = ref('')
  const startDate      = ref('')
  const endDate        = ref('')

  const hasActiveFilters = computed(() =>
    !!(customerFilter.value || tagFilter.value || minQty.value !== '' || maxQty.value !== '' ||
       startDate.value || endDate.value),
  )

  const clearFilters = () => {
    customerFilter.value = ''
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
    if (!currentStore.value?.id) return
    loading.value = true
    try {
      rows.value = await fetchSaleReport({ storeId: currentStore.value.id })
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
    rows, loading, searchQuery, customerFilter, tagFilter, minQty, maxQty, startDate, endDate,
    hasActiveFilters, clearFilters,
    baseRows, filteredRows, sortedRows, customerOptions, tagOptions, totals,
    sort, load,
  }
}
