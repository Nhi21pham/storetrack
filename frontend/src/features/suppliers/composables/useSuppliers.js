import { ref, computed, watch } from 'vue'
import {
  fetchSuppliers,
  deleteSupplier as deleteSupplierRequest,
  deleteSuppliers as deleteSuppliersRequest,
} from '@/features/suppliers/services/supplierService'
import { useSortCriteria } from '@/composables/useSortCriteria'

const getSortValue = (supplier, key) => {
  if (key === 'outstanding') return Number(supplier.outstanding || 0)
  if (key === 'created_at' || key === 'updated_at') return new Date(supplier[key] || 0).getTime()
  const value = supplier[key]
  return value == null ? '' : String(value).toLowerCase()
}

// Most recently updated first (falling back to created_at), so the No. column
// reads newest-to-oldest before any user sort is applied.
const byUpdatedDesc = (a, b) =>
  new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0)

const matchesSearch = (supplier, query) => {
  const q = query.toLowerCase()
  return (
    supplier.name.toLowerCase().includes(q) ||
    supplier.email?.toLowerCase().includes(q) ||
    supplier.tax_code?.toLowerCase().includes(q) ||
    supplier.address?.toLowerCase().includes(q) ||
    supplier.phone?.includes(q)
  )
}

export const useSuppliers = ({ currentStore, currentBusiness, onError }) => {
  const suppliers   = ref([])
  const loading     = ref(false)
  const searchQuery = ref('')
  const storeFilter = ref('store')

  const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')

  const canDelete = computed(() => {
    if (isBusinessOwner.value) return true
    const role = currentStore.value?.my_role
    return role === 'owner' || role === 'accountant'
  })

  // A supplier belongs to the current store if it's linked via the stores pivot —
  // that covers the store it was created at plus any store it has transacted at.
  const inCurrentStore = (supplier) => {
    const storeId = String(currentStore.value?.id)
    return (supplier.stores || []).some((s) => String(s.id) === storeId)
  }

  const canManageRow = (supplier) => {
    if (isBusinessOwner.value) return true
    if (!currentStore.value?.id) return false
    return inCurrentStore(supplier)
  }

  // Outstanding follows the view: the current store's balance under "this store",
  // the business-wide total under "all stores".
  const withScopedOutstanding = (supplier) => ({
    ...supplier,
    outstanding: storeFilter.value === 'store'
      ? Number(supplier.outstanding || 0)
      : Number(supplier.business_outstanding || 0),
  })

  const baseSuppliers = computed(() => {
    if (storeFilter.value === 'store') {
      return suppliers.value
        .filter(inCurrentStore)
        .map(withScopedOutstanding)
        .sort(byUpdatedDesc)
    }
    return [...suppliers.value]
      .sort((a, b) => {
        const aOwn = inCurrentStore(a)
        const bOwn = inCurrentStore(b)
        if (aOwn !== bOwn) return aOwn ? -1 : 1
        return byUpdatedDesc(a, b)
      })
      .map(withScopedOutstanding)
  })

  const filteredSuppliers = computed(() => {
    if (!searchQuery.value.trim()) return baseSuppliers.value
    return baseSuppliers.value.filter((s) => matchesSearch(s, searchQuery.value))
  })

  const sort = useSortCriteria()
  const sortedSuppliers = computed(() => sort.sortItems(filteredSuppliers.value, getSortValue))

  const load = async () => {
    if (!currentStore.value?.id || !currentBusiness.value?.id) return
    loading.value = true
    try {
      suppliers.value = await fetchSuppliers({
        storeId: currentStore.value.id,
        businessId: currentBusiness.value.id,
      })
    } catch (err) {
      onError?.(err.message)
    } finally {
      loading.value = false
    }
  }

  const removeOne = async (supplier) => {
    await deleteSupplierRequest({ id: supplier.id, businessId: currentBusiness.value?.id })
    await load()
  }

  const removeMany = async (ids) => {
    const count = await deleteSuppliersRequest({
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
    suppliers, loading, searchQuery, storeFilter,
    isBusinessOwner, canDelete, canManageRow,
    baseSuppliers, filteredSuppliers, sortedSuppliers,
    sort,
    load, removeOne, removeMany,
  }
}
