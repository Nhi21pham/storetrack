import { ref, computed, watch } from 'vue'
import {
  fetchCustomers,
  deleteCustomer as deleteCustomerRequest,
  deleteCustomers as deleteCustomersRequest,
} from '@/features/customers/services/customerService'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { useDateRangeFilter } from '@/composables/useDateRangeFilter'

const getSortValue = (customer, key) => {
  if (key === 'outstanding') return Number(customer.outstanding || 0)
  if (key === 'created_at' || key === 'updated_at') return new Date(customer[key] || 0).getTime()
  const value = customer[key]
  return value == null ? '' : String(value).toLowerCase()
}

// Most recently updated first (falling back to created_at), so the No. column
// reads newest-to-oldest before any user sort is applied.
const byUpdatedDesc = (a, b) =>
  new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0)

const matchesSearch = (customer, query) => {
  const q = query.toLowerCase()
  return (
    customer.name.toLowerCase().includes(q) ||
    customer.email?.toLowerCase().includes(q) ||
    customer.tax_code?.toLowerCase().includes(q) ||
    customer.address?.toLowerCase().includes(q) ||
    customer.phone?.includes(q)
  )
}

export const useCustomers = ({ currentStore, currentBusiness, onError }) => {
  const customers   = ref([])
  const loading     = ref(false)
  const searchQuery = ref('')
  const storeFilter = ref('store')

  const {
    startDate, endDate, dateField,
    isActive: dateRangeActive, inDateRange, clear: clearDateRange,
  } = useDateRangeFilter()

  const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')

  const canDelete = computed(() => {
    if (isBusinessOwner.value) return true
    const role = currentStore.value?.my_role
    return role === 'owner' || role === 'accountant'
  })

  // A customer belongs to the current store if it's linked via the stores pivot —
  // that covers the store it was created at plus any store it has transacted at.
  const inCurrentStore = (customer) => {
    const storeId = String(currentStore.value?.id)
    return (customer.stores || []).some((s) => String(s.id) === storeId)
  }

  const canManageRow = (customer) => {
    if (isBusinessOwner.value) return true
    if (!currentStore.value?.id) return false
    return inCurrentStore(customer)
  }

  // Outstanding follows the view: the current store's balance under "this store",
  // the business-wide total under "all stores".
  const withScopedOutstanding = (customer) => ({
    ...customer,
    outstanding: storeFilter.value === 'store'
      ? Number(customer.outstanding || 0)
      : Number(customer.business_outstanding || 0),
  })

  const baseCustomers = computed(() => {
    if (storeFilter.value === 'store') {
      return customers.value
        .filter(inCurrentStore)
        .map(withScopedOutstanding)
        .sort(byUpdatedDesc)
    }
    return [...customers.value]
      .sort((a, b) => {
        const aOwn = inCurrentStore(a)
        const bOwn = inCurrentStore(b)
        if (aOwn !== bOwn) return aOwn ? -1 : 1
        return byUpdatedDesc(a, b)
      })
      .map(withScopedOutstanding)
  })

  const filteredCustomers = computed(() => {
    const q = searchQuery.value.trim()
    return baseCustomers.value.filter((c) => {
      if (!inDateRange(c)) return false
      if (!q) return true
      return matchesSearch(c, searchQuery.value)
    })
  })

  const sort = useSortCriteria()
  const sortedCustomers = computed(() => sort.sortItems(filteredCustomers.value, getSortValue))

  const load = async () => {
    if (!currentStore.value?.id || !currentBusiness.value?.id) return
    loading.value = true
    try {
      customers.value = await fetchCustomers({
        storeId: currentStore.value.id,
        businessId: currentBusiness.value.id,
      })
    } catch (err) {
      onError?.(err.message)
    } finally {
      loading.value = false
    }
  }

  const removeOne = async (customer) => {
    await deleteCustomerRequest({ id: customer.id, businessId: currentBusiness.value?.id })
    await load()
  }

  const removeMany = async (ids) => {
    const count = await deleteCustomersRequest({
      ids,
      businessId: currentBusiness.value?.id,
      storeId: storeFilter.value === 'store' ? currentStore.value?.id : null,
    })
    await load()
    return count
  }

  watch(() => currentStore.value?.id, (id) => {
    if (id && currentBusiness.value?.id) load()
  }, { immediate: true })

  return {
    customers, loading, searchQuery, storeFilter,
    startDate, endDate, dateField, dateRangeActive, clearDateRange,
    isBusinessOwner, canDelete, canManageRow,
    baseCustomers, filteredCustomers, sortedCustomers,
    sort,
    load, removeOne, removeMany,
  }
}
