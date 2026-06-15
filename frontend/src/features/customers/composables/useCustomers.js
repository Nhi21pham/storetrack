import { ref, computed, watch } from 'vue'
import {
  fetchCustomers,
  deleteCustomer as deleteCustomerRequest,
  deleteCustomers as deleteCustomersRequest,
} from '@/features/customers/services/customerService'
import { useSortCriteria } from '@/composables/useSortCriteria'

const getSortValue = (customer, key) => {
  if (key === 'id') return Number(customer.id)
  if (key === 'outstanding') return Number(customer.outstanding || 0)
  const value = customer[key]
  return value == null ? '' : String(value).toLowerCase()
}

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
      return customers.value.filter(inCurrentStore).map(withScopedOutstanding)
    }
    return [...customers.value]
      .sort((a, b) => {
        const aOwn = inCurrentStore(a)
        const bOwn = inCurrentStore(b)
        if (aOwn !== bOwn) return aOwn ? -1 : 1
        return Number(a.id) - Number(b.id)
      })
      .map(withScopedOutstanding)
  })

  const filteredCustomers = computed(() => {
    if (!searchQuery.value.trim()) return baseCustomers.value
    return baseCustomers.value.filter((c) => matchesSearch(c, searchQuery.value))
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
    isBusinessOwner, canDelete, canManageRow,
    baseCustomers, filteredCustomers, sortedCustomers,
    sort,
    load, removeOne, removeMany,
  }
}
