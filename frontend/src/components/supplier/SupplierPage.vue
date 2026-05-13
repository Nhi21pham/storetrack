<template>
  <div class="supplier-page">
    <div class="page-header">
      <div>
        <h1>Suppliers</h1>
        <p class="subtitle">Manage your supplier contacts and information.</p>
      </div>
      <button v-if="currentBusiness && currentStore?.is_active" class="btn-create" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Supplier
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
      <p>You need to create a business before managing suppliers.</p>
    </div>

    <div v-else-if="!currentStore" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
          <path d="M8 7v4"/><path d="M16 7v4"/>
        </svg>
      </div>
      <h3>No store selected</h3>
      <p>Select a store to manage suppliers.</p>
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
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Loading suppliers...</span>
    </div>

    <div v-else-if="baseSuppliers.length === 0 && suppliers.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
          <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
        </svg>
      </div>
      <h3>No suppliers yet</h3>
      <p>Add your first supplier to get started.</p>
    </div>

    <div v-else-if="baseSuppliers.length === 0 && storeFilter === 'store'" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
        </svg>
      </div>
      <h3>No suppliers at this store</h3>
      <p>No suppliers were created at {{ currentStore.name }}. Switch to "All stores" to see business-wide suppliers.</p>
    </div>

    <div v-else-if="filteredSuppliers.length === 0" class="empty-state">
      <p>No suppliers matching "{{ searchQuery }}"</p>
    </div>

    <div v-else class="table-wrapper" :class="{ resizing: isResizing }">
      <table>
        <colgroup>
          <col v-for="(w, i) in colWidths" :key="i" :style="{ width: w + 'px' }" />
        </colgroup>
        <thead>
          <tr>
            <th v-for="(col, i) in columns" :key="col.key">
              <SortableHeader
                v-if="col.sortable"
                :label="col.label"
                :sort-info="getSortInfo(col.key)"
                :rank="sortCriteria.length > 1 && getSortInfo(col.key) ? sortRank(col.key) : null"
                @sort="(dir) => toggleSort(col.key, dir)"
              />
              <template v-else>{{ col.label }}</template>
              <div class="resize-handle" @mousedown.prevent="startResize($event, i)"></div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="supplier in sortedSuppliers" :key="supplier.id">
            <td><span class="id-badge">#{{ supplier.id }}</span></td>
            <td><button class="name-link" @click="openDetail(supplier)">{{ supplier.name }}</button></td>
            <td>
              <span v-if="supplier.tax_code" class="mono">{{ supplier.tax_code }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <span v-if="supplier.email">{{ supplier.email }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <span v-if="supplier.phone" class="mono">{{ supplier.phone }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <span v-if="supplier.address" :title="supplier.address" class="truncate">{{ supplier.address }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <div v-if="currentStore?.is_active" class="row-actions">
                <button class="action-btn" @click="openEdit(supplier)" title="Edit">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="action-btn danger" @click="confirmDelete(supplier)" title="Delete">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <SupplierFormModal
      v-if="showForm"
      :supplier="editingSupplier"
      @close="showForm = false"
      @saved="onSaved"
    />

    <SupplierDetailModal
      v-if="detailSupplier"
      :supplier="detailSupplier"
      :can-edit="!!currentStore?.is_active"
      @close="detailSupplier = null"
      @edit="onDetailEdit"
    />

    <ConfirmDialog
      v-if="deletingSupplier"
      title="Delete Supplier"
      :message="`Are you sure you want to delete '${deletingSupplier.name}'? This action cannot be undone.`"
      confirm-text="Yes, delete"
      cancel-text="Cancel"
      type="danger"
      @confirm="handleDelete"
      @cancel="deletingSupplier = null"
    />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount, inject } from 'vue'
import { graphql } from '@/api'
import { useSortCriteria } from '@/composables/useSortCriteria'
import SearchBar from '@/components/common/SearchBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import SupplierFormModal from '@/components/supplier/SupplierFormModal.vue'
import SupplierDetailModal from '@/components/supplier/SupplierDetailModal.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'

const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const storeFilter = ref('store')

const suppliers = ref([])
const loading = ref(false)
const showForm = ref(false)
const editingSupplier = ref(null)
const detailSupplier = ref(null)
const deletingSupplier = ref(null)
const searchQuery = ref('')

const columns = [
  { key: 'id',       label: 'ID',       sortable: true  },
  { key: 'name',     label: 'Name',     sortable: true  },
  { key: 'tax_code', label: 'Tax Code', sortable: true  },
  { key: 'email',    label: 'Email',    sortable: true  },
  { key: 'phone',    label: 'Phone',    sortable: true  },
  { key: 'address',  label: 'Address',  sortable: true  },
  { key: 'actions',  label: '',         sortable: false },
]

const { sortCriteria, getSortInfo, sortRank, toggleSort, clearSort, sortItems } = useSortCriteria()

const getSortValue = (s, key) => {
  if (key === 'id') return Number(s.id)
  const v = s[key]
  return v == null ? '' : String(v).toLowerCase()
}

const colWidths = ref([70, 160, 120, 190, 130, 220, 80])

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

const baseSuppliers = computed(() => {
  const storeId = String(currentStore.value?.id)
  if (storeFilter.value === 'store') {
    return suppliers.value.filter(s => String(s.store_id) === storeId)
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
  const q = searchQuery.value.toLowerCase()
  return baseSuppliers.value.filter(s =>
    s.name.toLowerCase().includes(q) ||
    s.email?.toLowerCase().includes(q) ||
    s.tax_code?.toLowerCase().includes(q) ||
    s.address?.toLowerCase().includes(q) ||
    s.phone?.includes(q)
  )
})

const sortedSuppliers = computed(() => sortItems(filteredSuppliers.value, getSortValue))

const fetchSuppliers = async () => {
  if (!currentStore.value?.id || !currentBusiness.value?.id) return
  loading.value = true
  try {
    const data = await graphql(
      `query Suppliers($store_id: ID!, $business_id: ID!) {
        suppliers(store_id: $store_id, business_id: $business_id) { id store_id name email phone address tax_code created_at }
      }`,
      { store_id: currentStore.value.id, business_id: currentBusiness.value.id }
    )
    suppliers.value = data.suppliers
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loading.value = false
  }
}

const openCreate = () => { editingSupplier.value = null; showForm.value = true }
const openEdit = (s) => { editingSupplier.value = { ...s }; showForm.value = true }
const openDetail = (s) => { detailSupplier.value = s }
const onDetailEdit = (s) => { detailSupplier.value = null; openEdit(s) }

const onSaved = () => {
  showForm.value = false
  fetchSuppliers()
  showToast(editingSupplier.value ? 'Supplier updated successfully!' : 'Supplier created successfully!')
}

const confirmDelete = (s) => { deletingSupplier.value = s }

const handleDelete = async () => {
  try {
    await graphql(
      `mutation DeleteSupplier($id: ID!, $store_id: ID!, $business_id: ID!) {
        deleteSupplier(id: $id, store_id: $store_id, business_id: $business_id)
      }`,
      {
        id: deletingSupplier.value.id,
        store_id: currentStore.value?.id,
        business_id: currentBusiness.value?.id,
      }
    )
    deletingSupplier.value = null
    fetchSuppliers()
    showToast('Supplier deleted successfully!')
  } catch (err) {
    showToast(err.message, 'error')
  }
}

watch(() => currentStore.value?.id, (id) => {
  if (id && currentBusiness.value?.id) fetchSuppliers()
}, { immediate: true })
</script>

<style scoped>
.supplier-page { padding: 32px; max-width: 1100px; margin: 0 auto; }

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

.row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.action-btn { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
