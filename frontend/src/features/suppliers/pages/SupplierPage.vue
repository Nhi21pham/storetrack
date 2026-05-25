<template>
  <PageContainer :maxWidth="1100">
    <PageHeader title="Suppliers" subtitle="Manage your supplier contacts and information.">
      <template v-if="currentBusiness && currentStore?.is_active" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Supplier
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      title="No business found"
      description="You need to create a business before managing suppliers."
    >
      <template #icon>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
      </template>
    </EmptyState>

    <EmptyState
      v-else-if="!currentStore"
      title="No store selected"
      description="Select a store to manage suppliers."
    >
      <template #icon>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
          <path d="M8 7v4"/><path d="M16 7v4"/>
        </svg>
      </template>
    </EmptyState>

    <template v-else-if="currentStore">
      <InactiveBanner v-if="!currentStore.is_active">
        This store is deactivated. Data is read-only until the store is reactivated.
      </InactiveBanner>

      <SupplierFilterBar
        v-model:searchQuery="searchQuery"
        v-model:storeFilter="storeFilter"
        :hasSort="sort.sortCriteria.length > 0"
        :exporting="exporting"
        :canExport="sortedSuppliers.length > 0"
        @clearSort="sort.clearSort"
        @export="run"
      />

      <LoadingState v-if="loading">Loading suppliers...</LoadingState>

      <EmptyState
        v-else-if="baseSuppliers.length === 0 && suppliers.length === 0"
        title="No suppliers yet"
        description="Add your first supplier to get started."
      >
        <template #icon>
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
          </svg>
        </template>
      </EmptyState>

      <EmptyState
        v-else-if="baseSuppliers.length === 0 && storeFilter === 'store'"
        title="No suppliers at this store"
        :description="`No suppliers were created at ${currentStore.name}. Switch to &quot;All stores&quot; to see business-wide suppliers.`"
      >
        <template #icon>
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
          </svg>
        </template>
      </EmptyState>

      <EmptyState
        v-else-if="filteredSuppliers.length === 0"
        :description="`No suppliers matching &quot;${searchQuery}&quot;`"
      />

      <template v-else>
        <SupplierSelectionBar
          v-if="selectedIds.size > 0"
          :count="selectedIds.size"
          :exporting="exporting"
          :bulkDeleting="bulkDeleting"
          :canDelete="!!currentStore?.is_active && canDelete"
          @clear="clearSelection"
          @export="run"
          @delete="confirmBulkDelete"
        />

        <SupplierTable
          :suppliers="paginatedSuppliers"
          :columns="SUPPLIER_COLUMNS"
          :sort="sort"
          :isSelected="isSelected"
          :canManageRow="canManageRow"
          :canDelete="canDelete"
          :rowActionsEnabled="!!currentStore?.is_active"
          :allVisibleSelected="allVisibleSelected"
          :someVisibleSelected="someVisibleSelected"
          @toggleSelectAll="toggleSelectAll"
          @toggleRow="toggleRow"
          @openDetail="openDetail"
          @edit="openEdit"
          @delete="confirmDelete"
        />
        <Pagination
          v-if="total > 0"
          :current-page="currentPage"
          :total-pages="totalPages"
          :total="total"
          :per-page="perPage"
          @update:current-page="currentPage = $event"
          @update:per-page="setPerPage"
        />
      </template>

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

      <ConfirmDialog
        v-if="showBulkDeleteConfirm"
        title="Delete Suppliers"
        :message="`Are you sure you want to delete ${selectedIds.size} supplier${selectedIds.size === 1 ? '' : 's'}? This action cannot be undone.`"
        confirm-text="Yes, delete"
        cancel-text="Cancel"
        type="danger"
        @confirm="handleBulkDelete"
        @cancel="showBulkDeleteConfirm = false"
      />
    </template>
  </PageContainer>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import InactiveBanner from '@/components/common/InactiveBanner.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import Pagination from '@/components/common/Pagination.vue'
import SupplierFilterBar from '@/features/suppliers/components/SupplierFilterBar.vue'
import SupplierSelectionBar from '@/features/suppliers/components/SupplierSelectionBar.vue'
import SupplierTable from '@/features/suppliers/components/SupplierTable.vue'
import SupplierFormModal from '@/features/suppliers/components/SupplierFormModal.vue'
import SupplierDetailModal from '@/features/suppliers/components/SupplierDetailModal.vue'
import { useSuppliers } from '@/features/suppliers/composables/useSuppliers'
import { useExport } from '@/composables/useExport'
import { useRowSelection } from '@/composables/useRowSelection'
import { useClientPagination } from '@/composables/useClientPagination'
import { startSupplierExport } from '@/features/suppliers/services/supplierService'
import { SUPPLIER_COLUMNS } from '@/features/suppliers/constants'

const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const {
  suppliers, loading, searchQuery, storeFilter,
  canDelete, canManageRow,
  baseSuppliers, filteredSuppliers, sortedSuppliers,
  sort,
  load, removeOne, removeMany,
} = useSuppliers({
  currentStore,
  currentBusiness,
  onError: (msg) => showToast(msg, 'error'),
})

const {
  currentPage,
  perPage,
  total,
  totalPages,
  paginated: paginatedSuppliers,
  setPerPage,
  resetPage,
} = useClientPagination(sortedSuppliers)

const visibleIds = computed(() =>
  paginatedSuppliers.value.filter(canManageRow).map((s) => String(s.id)),
)
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: visibleIds })

const showForm        = ref(false)
const editingSupplier = ref(null)
const detailSupplier  = ref(null)
const deletingSupplier = ref(null)
const bulkDeleting    = ref(false)
const showBulkDeleteConfirm = ref(false)

const openCreate    = () => { editingSupplier.value = null; showForm.value = true }
const openEdit      = (s) => { editingSupplier.value = { ...s }; showForm.value = true }
const openDetail    = (s) => { detailSupplier.value = s }
const onDetailEdit  = (s) => { detailSupplier.value = null; openEdit(s) }

const onSaved = () => {
  const wasEdit = !!editingSupplier.value
  showForm.value = false
  load()
  showToast(wasEdit ? 'Supplier updated successfully!' : 'Supplier created successfully!')
}

const confirmDelete = (s) => { deletingSupplier.value = s }

const handleDelete = async () => {
  try {
    await removeOne(deletingSupplier.value)
    deletingSupplier.value = null
    showToast('Supplier deleted successfully!')
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const confirmBulkDelete = () => { showBulkDeleteConfirm.value = true }

const handleBulkDelete = async () => {
  if (bulkDeleting.value) return
  bulkDeleting.value = true
  try {
    const count = await removeMany(Array.from(selectedIds.value))
    showBulkDeleteConfirm.value = false
    clearSelection()
    showToast(`Deleted ${count} supplier${count === 1 ? '' : 's'}.`)
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    bulkDeleting.value = false
  }
}

const { exporting, run } = useExport({
  start: () => {
    const params = { search: searchQuery.value.trim() || undefined }
    if (storeFilter.value === 'store' && currentStore.value?.id) {
      params.store_id = currentStore.value.id
    }
    if (selectedIds.value.size > 0) {
      params.ids = Array.from(selectedIds.value)
    }
    return startSupplierExport({ businessId: currentBusiness.value.id, params })
  },
  defaultFilename: (id) => `suppliers-${id}.xlsx`,
  onSuccess: () => showToast('Supplier export ready.', 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

watch([storeFilter, searchQuery, () => currentStore.value?.id], () => {
  clearSelection()
  resetPage()
})
</script>

<style scoped>
.btn-create { padding: 10px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s; white-space: nowrap; }
.btn-create:hover { background: #333; }
</style>
