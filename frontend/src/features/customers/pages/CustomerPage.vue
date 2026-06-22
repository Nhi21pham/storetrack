<template>
  <PageContainer :maxWidth="1100">
    <PageHeader title="Customers" subtitle="Manage your customer contacts and information.">
      <template v-if="currentBusiness && currentStore?.is_active" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Customer
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      title="No business found"
      description="You need to create a business before managing customers."
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
      description="Select a store to manage customers."
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

      <CustomerFilterBar
        v-model:searchQuery="searchQuery"
        v-model:storeFilter="storeFilter"
        :hasSort="sort.sortCriteria.length > 0"
        :exporting="exporting"
        :canExport="sortedCustomers.length > 0"
        @clearSort="sort.clearSort"
        @export="run"
      >
        <template #extra>
          <ColumnSelector
            :togglable-columns="columnVisibility.togglableColumns"
            :is-visible="columnVisibility.isVisible"
            :toggle-column="columnVisibility.toggleColumn"
            :reset-columns="columnVisibility.resetColumns"
          />
          <HistoryButton @click="showHistory = true" />
          <ImportButton v-if="currentStore.is_active" @click="showImport = true" />
        </template>
      </CustomerFilterBar>

      <DateRangeFilters
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        v-model:date-field="dateField"
      />

      <LoadingState v-if="loading">Loading customers...</LoadingState>

      <EmptyState
        v-else-if="baseCustomers.length === 0 && customers.length === 0"
        title="No customers yet"
        description="Add your first customer to get started."
      >
        <template #icon>
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </template>
      </EmptyState>

      <EmptyState
        v-else-if="baseCustomers.length === 0 && storeFilter === 'store'"
        title="No customers at this store"
        :description="`No customers were created at ${currentStore.name}. Switch to &quot;All stores&quot; to see business-wide customers.`"
      >
        <template #icon>
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
          </svg>
        </template>
      </EmptyState>

      <EmptyState
        v-else-if="filteredCustomers.length === 0"
        :description="`No customers matching &quot;${searchQuery}&quot;`"
      />

      <template v-else>
        <CustomerSelectionBar
          v-if="selectedIds.size > 0"
          :count="selectedIds.size"
          :exporting="exporting"
          :bulkDeleting="bulkDeleting"
          :canDelete="!!currentStore?.is_active && canDelete"
          @clear="clearSelection"
          @export="run"
          @delete="confirmBulkDelete"
        />

        <CustomerTable
          :key="tableKey"
          :customers="paginatedCustomers"
          :row-offset="(currentPage - 1) * perPage"
          :columns="columnVisibility.visibleColumns.value"
          :initial-widths="visibleWidths"
          :is-visible="columnVisibility.isVisible"
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

      <CustomerFormModal
        v-if="showForm"
        :customer="editingCustomer"
        @close="showForm = false"
        @saved="onSaved"
      />

      <ImportModal
        v-if="showImport && currentStore.is_active"
        title="Import Customers"
        template-filename="customers-import-template.xlsx"
        :required-headers="['Name', 'Phone']"
        :optional-headers="['Email', 'Address', 'Tax Code']"
        :instructions="customerImportInstructions"
        :download-template="() => downloadCustomersImportTemplate({ storeId: currentStore.id })"
        :preview="(file) => previewCustomersImport({ storeId: currentStore.id, file })"
        :revalidate="(rows) => revalidateCustomersImport({ storeId: currentStore.id, rows })"
        :start="(rows, originalFilename) => startCustomersImport({ storeId: currentStore.id, rows, originalFilename })"
        :status="(id) => fetchImportStatus({ importId: id })"
        @close="showImport = false"
        @imported="onImported"
      />

      <HistoryModal
        v-if="showHistory"
        title="Customer History"
        :tabs="historyTabs"
        @close="showHistory = false"
      />

      <CustomerDetailModal
        v-if="detailCustomer"
        :customer="detailCustomer"
        :can-edit="!!currentStore?.is_active && canManageRow(detailCustomer)"
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
import CustomerFilterBar from '@/features/customers/components/CustomerFilterBar.vue'
import CustomerSelectionBar from '@/features/customers/components/CustomerSelectionBar.vue'
import CustomerTable from '@/features/customers/components/CustomerTable.vue'
import CustomerFormModal from '@/features/customers/components/CustomerFormModal.vue'
import CustomerDetailModal from '@/features/customers/components/CustomerDetailModal.vue'
import ImportButton from '@/components/common/ImportButton.vue'
import HistoryButton from '@/components/common/HistoryButton.vue'
import ImportModal from '@/components/common/ImportModal.vue'
import HistoryModal from '@/components/common/HistoryModal.vue'
import ImportHistoryPanel from '@/components/common/ImportHistoryPanel.vue'
import ExportHistoryPanel from '@/components/common/ExportHistoryPanel.vue'
import DateRangeFilters from '@/components/common/DateRangeFilters.vue'
import { useCustomers } from '@/features/customers/composables/useCustomers'
import { useExport } from '@/composables/useExport'
import { useRowSelection } from '@/composables/useRowSelection'
import { useClientPagination } from '@/composables/useClientPagination'
import {
  startCustomerExport,
  downloadCustomersImportTemplate, previewCustomersImport, revalidateCustomersImport, startCustomersImport,
} from '@/features/customers/services/customerService'
import { fetchImportStatus } from '@/features/imports/services/importService'
import { CUSTOMER_COLUMNS, CUSTOMER_INITIAL_COL_WIDTHS } from '@/features/customers/constants'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import { useColumnVisibility } from '@/composables/useColumnVisibility'

const columnVisibility = useColumnVisibility({
  storageKey: 'customers',
  columns: CUSTOMER_COLUMNS,
  lockedKeys: ['select', 'stt', 'actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(CUSTOMER_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const {
  customers, loading, searchQuery, storeFilter,
  startDate, endDate, dateField,
  canDelete, canManageRow,
  baseCustomers, filteredCustomers, sortedCustomers,
  sort,
  load, removeOne, removeMany,
} = useCustomers({
  currentStore,
  currentBusiness,
  onError: (msg) => showToast(msg, 'error'),
})

const {
  currentPage,
  perPage,
  total,
  totalPages,
  paginated: paginatedCustomers,
  setPerPage,
  resetPage,
} = useClientPagination(sortedCustomers)

const selectableIds = computed(() =>
  sortedCustomers.value.filter(canManageRow).map((c) => String(c.id)),
)
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: selectableIds, scopeToEligible: true })

const showForm        = ref(false)
const editingCustomer = ref(null)
const detailCustomer  = ref(null)
const deletingCustomer = ref(null)
const bulkDeleting    = ref(false)
const showBulkDeleteConfirm = ref(false)

const showImport  = ref(false)
const showHistory = ref(false)

const historyTabs = computed(() => [
  { key: 'imports', label: 'Imports', component: ImportHistoryPanel, props: { scope: 'store', scopeId: currentStore.value?.id, type: 'customers' } },
  { key: 'exports', label: 'Exports', component: ExportHistoryPanel, props: { scope: 'business', scopeId: currentBusiness.value?.id, types: ['customers'] } },
])

// Explains the customer import rules in the import dialog (kept in sync with
// CustomerImporter on the backend).
const customerImportInstructions = [
  'Name and Phone are required. Email, Address and Tax Code are optional.',
  'Phone must be exactly 10 digits and is unique per business.',
  'Email and Tax Code, when given, must each be unique — a value already used by another customer must be fixed before importing.',
  'A row whose phone matches an existing customer with the same name is skipped (already imported); the same phone under a different name must be fixed.',
  'Imported customers are added to the current store.',
]

const openCreate    = () => { editingCustomer.value = null; showForm.value = true }
const openEdit      = (c) => { editingCustomer.value = { ...c }; showForm.value = true }
const openDetail    = (c) => { detailCustomer.value = c }
const onDetailEdit  = (c) => { detailCustomer.value = null; openEdit(c) }

const onSaved = () => {
  const wasEdit = !!editingCustomer.value
  showForm.value = false
  load()
  showToast(wasEdit ? 'Customer updated successfully!' : 'Customer created successfully!')
}

const onImported = () => {
  load()
}

const confirmDelete = (c) => { deletingCustomer.value = c }

const handleDelete = async () => {
  try {
    await removeOne(deletingCustomer.value)
    deletingCustomer.value = null
    showToast('Customer deleted successfully!')
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
    showToast(`Deleted ${count} customer${count === 1 ? '' : 's'}.`)
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
    params.columns = columnVisibility.togglableColumns
      .filter((col) => columnVisibility.isVisible(col.key))
      .map((col) => col.key)
    return startCustomerExport({ businessId: currentBusiness.value.id, params })
  },
  defaultFilename: (id) => `customers-${id}.xlsx`,
  onSuccess: () => showToast('Customer export ready.', 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

watch([storeFilter, searchQuery, startDate, endDate, dateField, () => currentStore.value?.id], () => {
  clearSelection()
  resetPage()
})
</script>

<style scoped>
.btn-create { padding: 10px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s; white-space: nowrap; }
.btn-create:hover { background: #333; }
</style>
