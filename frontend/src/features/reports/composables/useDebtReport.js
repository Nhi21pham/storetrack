import { ref, computed, watch } from 'vue'
import { useSortCriteria } from '@/composables/useSortCriteria'

const NUMERIC_KEYS = new Set(['spent', 'paid', 'owe', 'invoice_count'])

const getSortValue = (row, key) => {
  if (NUMERIC_KEYS.has(key)) return Number(row[key] || 0)
  const value = row[key]
  return value == null ? '' : String(value).toLowerCase()
}

const matchesSearch = (row, query) => {
  const q = query.toLowerCase()
  return (
    (row.name || '').toLowerCase().includes(q) ||
    (row.phone || '').toLowerCase().includes(q) ||
    (row.email || '').toLowerCase().includes(q)
  )
}

const dayOf = (value) => String(value).slice(0, 10)

/**
 * Backs both debt reports. `rows` are parties with their full invoices/payments;
 * the chosen date range narrows those to derive each party's spent (invoices by
 * invoice_date), paid (payments by paid_at) and owe (= spent − paid). All
 * search / sort / totals run client-side over the derived rows.
 */
export const useDebtReport = ({ currentStore, currentBusiness, scope, fetchers, onError }) => {
  const rows        = ref([])
  const loading     = ref(false)
  const searchQuery = ref('')
  const startDate   = ref('')
  const endDate     = ref('')

  const isBusinessScope = computed(() => scope?.value === 'business')

  const hasActiveFilters = computed(() => !!(startDate.value || endDate.value))

  const clearFilters = () => {
    startDate.value = ''
    endDate.value = ''
  }

  const inRange = (value) => {
    const d = dayOf(value)
    if (startDate.value && d < startDate.value) return false
    if (endDate.value && d > endDate.value) return false
    return true
  }

  // One derived row per party: invoices/payments narrowed to the range, plus the
  // spent/paid/owe totals computed from that window.
  const decoratedRows = computed(() =>
    rows.value.map((r) => {
      const rangeInvoices = (r.invoices || []).filter((inv) => inRange(inv.invoice_date))
      const rangePayments = (r.payments || []).filter((p) => inRange(p.paid_at))
      const spent = rangeInvoices.reduce((sum, inv) => sum + Number(inv.grand_total || 0), 0)
      const paid = rangePayments.reduce((sum, p) => sum + Number(p.amount || 0), 0)
      return {
        id: r.party_id,
        name: r.name,
        phone: r.phone,
        email: r.email,
        invoice_count: rangeInvoices.length,
        spent,
        paid,
        // Owe never goes below 0: paying more than was billed in the window
        // (a payment for an invoice dated before the range) clamps to nil owed.
        owe: Math.max(0, spent - paid),
        rangeInvoices,
        rangePayments,
      }
    }),
  )

  const filteredRows = computed(() => {
    if (!searchQuery.value.trim()) return decoratedRows.value
    return decoratedRows.value.filter((r) => matchesSearch(r, searchQuery.value))
  })

  const sort = useSortCriteria()
  const sortedRows = computed(() => sort.sortItems(filteredRows.value, getSortValue))

  // Footer totals over the full filtered set (independent of pagination).
  const totals = computed(() => {
    const list = filteredRows.value
    let spent = 0
    let paid = 0
    let owe = 0
    for (const r of list) {
      spent += Number(r.spent) || 0
      paid += Number(r.paid) || 0
      owe += Number(r.owe) || 0
    }
    return { partyCount: list.length, spent, paid, owe }
  })

  const load = async () => {
    const businessScope = isBusinessScope.value
    const id = businessScope ? currentBusiness?.value?.id : currentStore.value?.id
    if (!id) return

    loading.value = true
    try {
      rows.value = businessScope
        ? await fetchers.business({ businessId: id })
        : await fetchers.store({ storeId: id })
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
    rows, loading, searchQuery, startDate, endDate,
    hasActiveFilters, clearFilters,
    decoratedRows, filteredRows, sortedRows, totals,
    sort, load,
  }
}
