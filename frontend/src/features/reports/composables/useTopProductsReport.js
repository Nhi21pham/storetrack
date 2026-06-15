import { ref, computed, watch } from 'vue'
import { fetchTopProductsReport, fetchTopProductsReportByBusiness } from '@/features/reports/services/reportService'
import { useSortCriteria } from '@/composables/useSortCriteria'

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
  const searchQuery = ref('')
  const tagFilter   = ref('')
  const startDate   = ref('')
  const endDate     = ref('')
  const rankBy      = ref('qty_sold')

  const isBusinessScope = computed(() => scope?.value === 'business')

  const hasActiveFilters = computed(() => !!(tagFilter.value || startDate.value || endDate.value))

  const clearFilters = () => {
    tagFilter.value = ''
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
    if (tagFilter.value) {
      const [kind, id] = tagFilter.value.split(':')
      list = list.filter((r) => {
        const tags = r.tags || []
        if (kind === 'tag') return tags.some((t) => String(t.tag_id) === id)
        if (kind === 'val') return tags.some((t) => String(t.tag_value_id) === id)
        return true
      })
    }
    return list
  })

  const filteredRows = computed(() => {
    if (!searchQuery.value.trim()) return baseRows.value
    return baseRows.value.filter((r) => matchesSearch(r, searchQuery.value))
  })

  const sortedRows = computed(() => sort.sortItems(filteredRows.value, getSortValue))

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
