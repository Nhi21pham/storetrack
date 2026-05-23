import { ref, computed, watch } from 'vue'
import {
  fetchSuppliers,
  deleteSupplier as deleteSupplierRequest,
  deleteSuppliers as deleteSuppliersRequest,
} from '@/features/suppliers/services/supplierService'
import { useSortCriteria } from '@/composables/useSortCriteria'

const getSortValue = (supplier, key) => {
  if (key === 'id') return Number(supplier.id)
  const value = supplier[key]
  return value == null ? '' : String(value).toLowerCase()
}

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

  // Suppliers can be visible from "all stores" view but only managed when
  // they belong to a store the user has access to — business owners override.
  const canManageRow = (supplier) => {
    if (isBusinessOwner.value) return true
    if (!currentStore.value?.id) return false
    const storeIdStr = String(currentStore.value.id)
    return (supplier.stores || []).some((s) => String(s.id) === storeIdStr)
  }

  const baseSuppliers = computed(() => {
    const storeId = String(currentStore.value?.id)
    if (storeFilter.value === 'store') {
      return suppliers.value.filter((s) => String(s.store_id) === storeId)
    }
    return [...suppliers.value].sort((a, b) => {
      const aOwn = String(a.store_id) === storeId
      const bOwn = String(b.store_id) === storeId
      if (aOwn !== bOwn) return aOwn ? -1 : 1
      return Number(a.id) - Number(b.id)
    })
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
