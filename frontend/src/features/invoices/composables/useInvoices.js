import { ref, computed, watch } from 'vue'
import {
  fetchInvoices,
  deleteInvoice as deleteInvoiceRequest,
} from '@/features/invoices/services/invoiceService'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { INVOICE_TYPE } from '@/features/invoices/constants'

const NUMERIC_KEYS = new Set(['subtotal', 'tax_total', 'grand_total'])

const DATE_KEYS = new Set(['invoice_date', 'created_at', 'updated_at'])

const getSortValue = (invoice, key) => {
  if (NUMERIC_KEYS.has(key)) return Number(invoice[key] || 0)
  if (DATE_KEYS.has(key)) return new Date(invoice[key] || 0).getTime()
  const value = invoice[key]
  return value == null ? '' : String(value).toLowerCase()
}

const matchesSearch = (invoice, query) => {
  const q = query.toLowerCase()
  return (
    (invoice.code || '').toLowerCase().includes(q) ||
    (invoice.party_name || '').toLowerCase().includes(q)
  )
}

export const useInvoices = ({ currentStore, currentBusiness, onError, type = INVOICE_TYPE.PURCHASE }) => {
  const invoices     = ref([])
  const loading      = ref(false)
  const searchQuery  = ref('')
  const statusFilter = ref('')
  const methodFilter = ref('')
  const partyFilter  = ref('')
  const startDate    = ref('')
  const endDate      = ref('')

  const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')

  const canDelete = computed(() => {
    if (isBusinessOwner.value) return true
    const role = currentStore.value?.my_role
    return role === 'owner' || role === 'accountant'
  })

  const hasActiveFilters = computed(() =>
    !!(statusFilter.value || methodFilter.value || partyFilter.value || startDate.value || endDate.value),
  )

  const clearFilters = () => {
    statusFilter.value = ''
    methodFilter.value = ''
    partyFilter.value = ''
    startDate.value = ''
    endDate.value = ''
  }

  const baseInvoices = computed(() => {
    let list = invoices.value
    if (statusFilter.value) list = list.filter((i) => i.payment_status === statusFilter.value)
    if (methodFilter.value) list = list.filter((i) => i.payment_method === methodFilter.value)
    if (partyFilter.value) list = list.filter((i) => String(i.party_id) === partyFilter.value)
    if (startDate.value || endDate.value) {
      list = list.filter((i) => {
        const d = String(i.invoice_date).slice(0, 10)
        if (startDate.value && d < startDate.value) return false
        if (endDate.value && d > endDate.value) return false
        return true
      })
    }
    return list
  })

  const filteredInvoices = computed(() => {
    if (!searchQuery.value.trim()) return baseInvoices.value
    return baseInvoices.value.filter((i) => matchesSearch(i, searchQuery.value))
  })

  // Newest invoice first by default (invoice date, then most recently updated),
  // so the No. column reads newest-to-oldest before any user sort is applied.
  const orderedInvoices = computed(() =>
    [...filteredInvoices.value].sort((a, b) => {
      const byDate = new Date(b.invoice_date || 0) - new Date(a.invoice_date || 0)
      if (byDate !== 0) return byDate
      return new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0)
    })
  )

  const sort = useSortCriteria()
  const sortedInvoices = computed(() => sort.sortItems(orderedInvoices.value, getSortValue))

  // Distinct parties present in the loaded invoices, for the column filter.
  const partyOptions = computed(() => {
    const seen = new Map()
    for (const i of invoices.value) {
      const id = i.party_id != null ? String(i.party_id) : null
      if (id && !seen.has(id)) seen.set(id, i.party_name || `#${id}`)
    }
    return Array.from(seen, ([value, label]) => ({ value, label }))
  })

  const load = async () => {
    if (!currentStore.value?.id) return
    loading.value = true
    try {
      invoices.value = await fetchInvoices({ storeId: currentStore.value.id, type })
    } catch (err) {
      onError?.(err.message)
    } finally {
      loading.value = false
    }
  }

  const removeOne = async (invoice) => {
    await deleteInvoiceRequest({ id: invoice.id })
    await load()
  }

  const removeMany = async (ids) => {
    const results = await Promise.allSettled(ids.map((id) => deleteInvoiceRequest({ id })))
    await load()
    const deleted = results.filter((r) => r.status === 'fulfilled').length
    return { deleted, failed: ids.length - deleted }
  }

  watch(() => currentStore.value?.id, (id) => {
    if (id) load()
  }, { immediate: true })

  return {
    invoices, loading, searchQuery, statusFilter, methodFilter, partyFilter, startDate, endDate,
    canDelete, hasActiveFilters, clearFilters,
    baseInvoices, filteredInvoices, sortedInvoices, partyOptions,
    sort,
    load, removeOne, removeMany,
  }
}
