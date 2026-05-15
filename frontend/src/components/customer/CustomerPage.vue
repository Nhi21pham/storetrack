<template>
  <div class="customer-page">
    <div class="page-header">
      <div>
        <h1>Customers</h1>
        <p class="subtitle">Manage your customer contacts and information.</p>
      </div>
      <button v-if="currentBusiness && currentStore?.is_active" class="btn-create" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Customer
      </button>
    </div>

    <div v-if="!currentBusiness" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </div>
      <h3>No business found</h3>
      <p>You need to create a business before managing customers.</p>
    </div>

    <div v-else-if="!currentStore" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
          <path d="M8 7v4"/><path d="M16 7v4"/>
        </svg>
      </div>
      <h3>No store selected</h3>
      <p>Select a store to manage customers.</p>
    </div>

    <template v-else-if="currentStore">
    <div v-if="!currentStore.is_active" class="inactive-banner">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      This store is deactivated. Data is read-only until the store is reactivated.
    </div>

    <div class="filter-bar">
      <SearchBar v-model="searchQuery" placeholder="Search by name, email, tax code, phone or address..." />
      <div class="store-filter">
        <button :class="{ active: storeFilter === 'store' }" @click="storeFilter = 'store'">This store</button>
        <button :class="{ active: storeFilter === 'all' }" @click="storeFilter = 'all'">All stores</button>
      </div>
      <button v-if="sortCriteria.length" class="btn-clear-sort" @click="clearSort">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Clear sort
      </button>
      <button
        class="btn-export"
        :disabled="exporting || sortedCustomers.length === 0"
        :title="exporting ? 'Preparing export...' : 'Export current view to Excel'"
        @click="startExport"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>{{ exporting ? 'Exporting...' : 'Export' }}</span>
      </button>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Loading customers...</span>
    </div>

    <div v-else-if="baseCustomers.length === 0 && customers.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <h3>No customers yet</h3>
      <p>Add your first customer to get started.</p>
    </div>

    <div v-else-if="baseCustomers.length === 0 && storeFilter === 'store'" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
        </svg>
      </div>
      <h3>No customers at this store</h3>
      <p>No customers were created at {{ currentStore.name }}. Switch to "All stores" to see business-wide customers.</p>
    </div>

    <div v-else-if="filteredCustomers.length === 0" class="empty-state">
      <p>No customers matching "{{ searchQuery }}"</p>
    </div>

    <template v-else>
    <div v-if="selectedIds.size > 0" class="selection-bar">
      <span class="selection-count">{{ selectedIds.size }} selected</span>
      <div class="selection-actions">
        <button class="btn-selection-action" @click="clearSelection">Clear</button>
        <button
          class="btn-selection-action"
          :disabled="exporting"
          @click="startExport"
        >
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          {{ exporting ? 'Exporting...' : 'Export selected' }}
        </button>
        <button
          v-if="currentStore?.is_active"
          class="btn-selection-action danger"
          :disabled="bulkDeleting"
          @click="confirmBulkDelete"
        >
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          Delete selected
        </button>
      </div>
    </div>

    <div class="table-wrapper" :class="{ resizing: isResizing }">
      <table>
        <colgroup>
          <col v-for="(w, i) in colWidths" :key="i" :style="{ width: w + 'px' }" />
        </colgroup>
        <thead>
          <tr>
            <th v-for="(col, i) in columns" :key="col.key">
              <template v-if="col.key === 'select'">
                <input
                  type="checkbox"
                  class="row-check"
                  :checked="allVisibleSelected"
                  :indeterminate.prop="someVisibleSelected"
                  @change="toggleSelectAll"
                  title="Select all on this page"
                />
              </template>
              <SortableHeader
                v-else-if="col.sortable"
                :label="col.label"
                :sort-info="getSortInfo(col.key)"
                :rank="sortCriteria.length > 1 && getSortInfo(col.key) ? sortRank(col.key) : null"
                @sort="(dir) => toggleSort(col.key, dir)"
              />
              <template v-else>{{ col.label }}</template>
              <div v-if="col.key !== 'select'" class="resize-handle" @mousedown.prevent="startResize($event, i)"></div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="customer in sortedCustomers" :key="customer.id" :class="{ 'row-selected': isSelected(customer.id) }">
            <td>
              <input
                type="checkbox"
                class="row-check"
                :checked="isSelected(customer.id)"
                @change="toggleRow(customer.id)"
              />
            </td>
            <td><span class="id-badge">#{{ customer.id }}</span></td>
            <td><button class="name-link" @click="openDetail(customer)">{{ customer.name }}</button></td>
            <td>
              <span v-if="customer.tax_code" class="mono">{{ customer.tax_code }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <span v-if="customer.email">{{ customer.email }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <span v-if="customer.phone" class="mono">{{ customer.phone }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <span v-if="customer.address" :title="customer.address" class="truncate">{{ customer.address }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <div v-if="currentStore?.is_active" class="row-actions">
                <button class="action-btn" @click="openEdit(customer)" title="Edit">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="action-btn danger" @click="confirmDelete(customer)" title="Delete">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </template>

    <CustomerFormModal
      v-if="showForm"
      :customer="editingCustomer"
      @close="showForm = false"
      @saved="onSaved"
    />

    <CustomerDetailModal
      v-if="detailCustomer"
      :customer="detailCustomer"
      :can-edit="!!currentStore?.is_active"
      @close="detailCustomer = null"
      @edit="onDetailEdit"
    />

    <ConfirmDialog
      v-if="deletingCustomer"
      title="Delete Customer"
      :message="`Are you sure you want to delete '${deletingCustomer.name}'? This action cannot be undone.`"
      confirm-text="Yes, delete"
      cancel-text="Cancel"
      type="danger"
      @confirm="handleDelete"
      @cancel="deletingCustomer = null"
    />

    <ConfirmDialog
      v-if="showBulkDeleteConfirm"
      title="Delete Customers"
      :message="`Are you sure you want to delete ${selectedIds.size} customer${selectedIds.size === 1 ? '' : 's'}? This action cannot be undone.`"
      confirm-text="Yes, delete"
      cancel-text="Cancel"
      type="danger"
      @confirm="handleBulkDelete"
      @cancel="showBulkDeleteConfirm = false"
    />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount, inject } from 'vue'
import { graphql, rest } from '@/api'
import { useSortCriteria } from '@/composables/useSortCriteria'
import SearchBar from '@/components/common/SearchBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import CustomerFormModal from '@/components/customer/CustomerFormModal.vue'
import CustomerDetailModal from '@/components/customer/CustomerDetailModal.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'

const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const customers = ref([])
const loading = ref(false)
const showForm = ref(false)
const editingCustomer = ref(null)
const detailCustomer = ref(null)
const deletingCustomer = ref(null)
const searchQuery = ref('')
const storeFilter = ref('store')

const columns = [
  { key: 'select',   label: '',         sortable: false },
  { key: 'id',       label: 'ID',       sortable: true  },
  { key: 'name',     label: 'Name',     sortable: true  },
  { key: 'tax_code', label: 'Tax Code', sortable: true  },
  { key: 'email',    label: 'Email',    sortable: true  },
  { key: 'phone',    label: 'Phone',    sortable: true  },
  { key: 'address',  label: 'Address',  sortable: true  },
  { key: 'actions',  label: '',         sortable: false },
]

const { sortCriteria, getSortInfo, sortRank, toggleSort, clearSort, sortItems } = useSortCriteria()

const getSortValue = (c, key) => {
  if (key === 'id') return Number(c.id)
  const v = c[key]
  return v == null ? '' : String(v).toLowerCase()
}

const colWidths = ref([40, 70, 160, 120, 190, 130, 220, 80])

const isResizing = ref(false)
let resizeState = null

const startResize = (e, index) => {
  resizeState = {
    index,
    startX: e.clientX,
    startWidth: colWidths.value[index],
  }
  isResizing.value = true

  const onMove = (e) => {
    if (!resizeState) return
    const delta = e.clientX - resizeState.startX
    colWidths.value[resizeState.index] = Math.max(50, resizeState.startWidth + delta)
  }

  const onUp = () => {
    resizeState = null
    isResizing.value = false
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('mouseup', onUp)
  }

  window.addEventListener('mousemove', onMove)
  window.addEventListener('mouseup', onUp)
}

onBeforeUnmount(() => {
  isResizing.value = false
  resizeState = null
})

const baseCustomers = computed(() => {
  const storeId = String(currentStore.value?.id)
  if (storeFilter.value === 'store') {
    return customers.value.filter(c => String(c.store_id) === storeId)
  }
  return [...customers.value].sort((a, b) => {
    const aOwn = String(a.store_id) === storeId
    const bOwn = String(b.store_id) === storeId
    if (aOwn !== bOwn) return aOwn ? -1 : 1
    return Number(a.id) - Number(b.id)
  })
})

const filteredCustomers = computed(() => {
  if (!searchQuery.value.trim()) return baseCustomers.value
  const q = searchQuery.value.toLowerCase()
  return baseCustomers.value.filter(c =>
    c.name.toLowerCase().includes(q) ||
    c.email?.toLowerCase().includes(q) ||
    c.tax_code?.toLowerCase().includes(q) ||
    c.address?.toLowerCase().includes(q) ||
    c.phone?.includes(q)
  )
})

const sortedCustomers = computed(() => sortItems(filteredCustomers.value, getSortValue))

const selectedIds = ref(new Set())
const isSelected = (id) => selectedIds.value.has(String(id))
const toggleRow = (id) => {
  const key = String(id)
  const next = new Set(selectedIds.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  selectedIds.value = next
}

const visibleIds = computed(() => sortedCustomers.value.map(c => String(c.id)))
const selectedVisibleCount = computed(() => visibleIds.value.filter(id => selectedIds.value.has(id)).length)
const allVisibleSelected = computed(() =>
  visibleIds.value.length > 0 && selectedVisibleCount.value === visibleIds.value.length
)
const someVisibleSelected = computed(() =>
  selectedVisibleCount.value > 0 && selectedVisibleCount.value < visibleIds.value.length
)

const toggleSelectAll = () => {
  if (allVisibleSelected.value) {
    const next = new Set(selectedIds.value)
    for (const id of visibleIds.value) next.delete(id)
    selectedIds.value = next
  } else {
    const next = new Set(selectedIds.value)
    for (const id of visibleIds.value) next.add(id)
    selectedIds.value = next
  }
}

const clearSelection = () => { selectedIds.value = new Set() }

watch([storeFilter, searchQuery, () => currentStore.value?.id], () => {
  clearSelection()
})

const fetchCustomers = async () => {
  if (!currentStore.value?.id || !currentBusiness.value?.id) return
  loading.value = true
  try {
    const data = await graphql(
      `query Customers($store_id: ID!, $business_id: ID!) {
        customers(store_id: $store_id, business_id: $business_id) { id store_id name email phone address tax_code created_at }
      }`,
      { store_id: currentStore.value.id, business_id: currentBusiness.value.id }
    )
    customers.value = data.customers
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loading.value = false
  }
}

const openCreate = () => { editingCustomer.value = null; showForm.value = true }
const openEdit = (c) => { editingCustomer.value = { ...c }; showForm.value = true }
const openDetail = (c) => { detailCustomer.value = c }
const onDetailEdit = (c) => { detailCustomer.value = null; openEdit(c) }

const onSaved = () => {
  showForm.value = false
  fetchCustomers()
  showToast(editingCustomer.value ? 'Customer updated successfully!' : 'Customer created successfully!')
}

const confirmDelete = (c) => { deletingCustomer.value = c }

const handleDelete = async () => {
  try {
    await graphql(
      `mutation DeleteCustomer($id: ID!, $store_id: ID!, $business_id: ID!) {
        deleteCustomer(id: $id, store_id: $store_id, business_id: $business_id)
      }`,
      {
        id: deletingCustomer.value.id,
        store_id: currentStore.value?.id,
        business_id: currentBusiness.value?.id,
      }
    )
    deletingCustomer.value = null
    fetchCustomers()
    showToast('Customer deleted successfully!')
  } catch (err) {
    showToast(err.message, 'error')
  }
}

watch(() => currentStore.value?.id, (id) => {
  if (id && currentBusiness.value?.id) fetchCustomers()
}, { immediate: true })

const exporting   = ref(false)
const POLL_MS     = 1500
const POLL_MAX_MS = 5 * 60 * 1000
let pollTimer = null
let pollStartAt = 0

const cancelPoll = () => {
  if (pollTimer) {
    clearTimeout(pollTimer)
    pollTimer = null
  }
}

onBeforeUnmount(cancelPoll)

const triggerBlobDownload = (blob, filename) => {
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  window.URL.revokeObjectURL(url)
}

const pollExport = async (exportId) => {
  try {
    const status = await rest('get', `/api/exports/${exportId}`)
    if (status.status === 'completed') {
      const blob = await rest('get', `/api/exports/${exportId}/download`, { responseType: 'blob' })
      triggerBlobDownload(blob, status.filename || `customers-${exportId}.xlsx`)
      exporting.value = false
      showToast('Customer export ready.', 'success')
      return
    }
    if (status.status === 'failed') {
      exporting.value = false
      showToast(status.error_message || 'Export failed. Please try again.', 'error')
      return
    }
    if (Date.now() - pollStartAt > POLL_MAX_MS) {
      exporting.value = false
      showToast('Export is taking too long. Please try again later.', 'error')
      return
    }
    pollTimer = setTimeout(() => pollExport(exportId), POLL_MS)
  } catch (err) {
    exporting.value = false
    showToast(err.message, 'error')
  }
}

const startExport = async () => {
  if (exporting.value) return
  if (!currentBusiness.value?.id) return

  exporting.value = true
  pollStartAt = Date.now()

  try {
    const params = {
      search: searchQuery.value.trim() || undefined,
    }
    if (storeFilter.value === 'store' && currentStore.value?.id) {
      params.store_id = currentStore.value.id
    }
    if (selectedIds.value.size > 0) {
      params.ids = Array.from(selectedIds.value)
    }
    const response = await rest('post', `/api/exports/customers/${currentBusiness.value.id}`, { params })
    pollExport(response.id)
  } catch (err) {
    exporting.value = false
    showToast(err.message, 'error')
  }
}

const bulkDeleting = ref(false)
const showBulkDeleteConfirm = ref(false)

const confirmBulkDelete = () => { showBulkDeleteConfirm.value = true }

const handleBulkDelete = async () => {
  if (bulkDeleting.value) return
  bulkDeleting.value = true
  try {
    const variables = {
      ids: Array.from(selectedIds.value),
      business_id: currentBusiness.value?.id,
    }
    if (storeFilter.value === 'store' && currentStore.value?.id) {
      variables.store_id = currentStore.value.id
    }
    const data = await graphql(
      `mutation DeleteCustomers($ids: [ID!]!, $business_id: ID!, $store_id: ID) {
        deleteCustomers(ids: $ids, business_id: $business_id, store_id: $store_id)
      }`,
      variables
    )
    const count = data.deleteCustomers ?? 0
    showBulkDeleteConfirm.value = false
    clearSelection()
    fetchCustomers()
    showToast(`Deleted ${count} customer${count === 1 ? '' : 's'}.`)
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    bulkDeleting.value = false
  }
}
</script>

<style scoped>
.customer-page { padding: 32px; max-width: 1100px; margin: 0 auto; }

.inactive-banner { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; font-size: 13px; color: #92400e; margin-bottom: 16px; }

.filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.filter-bar > :first-child { flex: 1; margin-bottom: 0; }
.store-filter { display: flex; background: #f3f4f6; border-radius: 8px; padding: 3px; gap: 2px; flex-shrink: 0; }
.store-filter button { padding: 5px 14px; border: none; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; background: none; color: #6b7280; transition: all 0.15s; white-space: nowrap; }
.store-filter button.active { background: #fff; color: #111; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; }
.page-header h1 { font-size: 22px; font-weight: 700; color: #111; }
.subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }

.btn-create { padding: 10px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s; white-space: nowrap; }
.btn-create:hover { background: #333; }

.btn-export { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border: 1px solid #111; border-radius: 7px; font-size: 12.5px; font-weight: 600; color: #fff; background: #111; cursor: pointer; transition: background 0.2s, opacity 0.2s; white-space: nowrap; flex-shrink: 0; margin-left: auto; }
.btn-export:hover:not(:disabled) { background: #000; }
.btn-export:disabled { opacity: 0.55; cursor: not-allowed; }

.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px 0; color: #6b7280; font-size: 14px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { width: 72px; height: 72px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #9ca3af; }
.empty-state h3 { font-size: 16px; font-weight: 600; color: #111; margin-bottom: 6px; }
.empty-state p { font-size: 14px; color: #6b7280; }

.table-wrapper { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow-x: auto; }
.table-wrapper.resizing { cursor: col-resize; user-select: none; }

table { width: 100%; border-collapse: collapse; font-size: 13.5px; table-layout: fixed; }

thead { background: #f9fafb; }
th { position: relative; padding: 11px 16px; text-align: left; font-size: 11.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e5e7eb; white-space: nowrap; overflow: hidden; }

td { padding: 13px 16px; color: #374151; border-bottom: 1px solid #f3f4f6; vertical-align: middle; overflow: hidden; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #fafafa; }

.btn-clear-sort { display: flex; align-items: center; gap: 5px; padding: 5px 10px; border: 1px solid #e5e7eb; background: #fff; color: #6b7280; border-radius: 7px; font-size: 12px; font-weight: 500; cursor: pointer; white-space: nowrap; transition: all 0.15s; flex-shrink: 0; }
.btn-clear-sort:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }

.resize-handle { position: absolute; top: 0; right: -6px; width: 12px; height: 100%; cursor: col-resize; z-index: 1; display: flex; align-items: stretch; justify-content: center; }
.resize-handle::after { content: ''; width: 2px; border-radius: 2px; background: #d1d5db; transition: background 0.15s; }
.resize-handle:hover::after { background: #6b7280; }
.resizing .resize-handle::after { background: #111; }

.id-badge { font-size: 12px; font-weight: 600; color: #9ca3af; font-family: monospace; }
.name-text { font-weight: 600; color: #111; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; }
.name-link:hover { color: #2563eb; text-decoration: underline; }
.mono { font-family: monospace; font-size: 13px; }
.empty-val { color: #d1d5db; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.row-check { width: 15px; height: 15px; cursor: pointer; accent-color: #111; }
tbody tr.row-selected { background: #f0f7ff; }
tbody tr.row-selected:hover { background: #e6f0fb; }

.selection-bar { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; margin-bottom: 12px; background: #111; color: #fff; border-radius: 10px; font-size: 13px; }
.selection-count { font-weight: 600; }
.selection-actions { display: flex; gap: 8px; }
.btn-selection-action { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border: 1px solid rgba(255,255,255,0.2); border-radius: 7px; font-size: 12.5px; font-weight: 500; color: #fff; background: transparent; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.btn-selection-action:hover:not(:disabled) { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.4); }
.btn-selection-action.danger:hover:not(:disabled) { background: #dc2626; border-color: #dc2626; }
.btn-selection-action:disabled { opacity: 0.5; cursor: not-allowed; }

.row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.action-btn { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
