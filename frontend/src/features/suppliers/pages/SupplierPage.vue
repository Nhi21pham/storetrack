<template>
  <PageContainer :maxWidth="1100">
    <PageHeader :title="$t('suppliers.title')" :subtitle="$t('suppliers.subtitle')">
      <template v-if="currentBusiness && currentStore?.is_active" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ $t('suppliers.newSupplier') }}
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      :title="$t('shared.noBusinessTitle')"
      :description="$t('suppliers.noBusinessDesc')"
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
      :title="$t('shared.noStoreTitle')"
      :description="$t('suppliers.noStoreDesc')"
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
        {{ $t('suppliers.inactiveStoreBanner') }}
      </InactiveBanner>

      <SupplierFilterBar
        v-model:searchQuery="searchQuery"
        v-model:storeFilter="storeFilter"
        :hasSort="sort.sortCriteria.length > 0"
        :exporting="exporting"
        :canExport="sortedSuppliers.length > 0"
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
      </SupplierFilterBar>

      <DateRangeFilters
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        v-model:date-field="dateField"
      />

      <LoadingState v-if="loading">{{ $t('suppliers.loadingSuppliers') }}</LoadingState>

      <EmptyState
        v-else-if="baseSuppliers.length === 0 && suppliers.length === 0"
        :title="$t('suppliers.noSuppliersTitle')"
        :description="$t('suppliers.noSuppliersDesc')"
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
        :title="$t('suppliers.noSuppliersAtStoreTitle')"
        :description="$t('suppliers.noSuppliersAtStoreDesc', { store: currentStore.name })"
      >
        <template #icon>
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
          </svg>
        </template>
      </EmptyState>

      <EmptyState
        v-else-if="filteredSuppliers.length === 0"
        :description="$t('suppliers.noSuppliersMatching', { query: searchQuery })"
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
          :key="tableKey"
          :suppliers="paginatedSuppliers"
          :row-offset="(currentPage - 1) * perPage"
          :columns="columnVisibility.visibleColumns.value"
          :initial-widths="visibleWidths"
          :is-visible="columnVisibility.isVisible"
          :sort="sort"
          :isSelected="isSelected"
          :isRecent="isRecent"
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

      <ImportModal
        v-if="showImport && currentStore.is_active"
        :title="$t('suppliers.importTitle')"
        template-filename="suppliers-import-template.xlsx"
        :required-headers="['Name']"
        :optional-headers="['Phone', 'Email', 'Address', 'Tax Code']"
        :instructions="supplierImportInstructions"
        :download-template="() => downloadSuppliersImportTemplate({ storeId: currentStore.id })"
        :preview="(file) => previewSuppliersImport({ storeId: currentStore.id, file })"
        :revalidate="(rows) => revalidateSuppliersImport({ storeId: currentStore.id, rows })"
        :start="(rows, originalFilename) => startSuppliersImport({ storeId: currentStore.id, rows, originalFilename })"
        :status="(id) => fetchImportStatus({ importId: id })"
        @close="showImport = false"
        @imported="onImported"
      />

      <HistoryModal
        v-if="showHistory"
        :title="$t('suppliers.historyTitle')"
        :tabs="historyTabs"
        @close="showHistory = false"
      />

      <SupplierDetailModal
        v-if="detailSupplier"
        :supplier="detailSupplier"
        :can-edit="!!currentStore?.is_active && canManageRow(detailSupplier)"
        @close="detailSupplier = null"
        @edit="onDetailEdit"
      />

      <ConfirmDialog
        v-if="deletingSupplier"
        :title="$t('suppliers.deleteTitle')"
        :message="$t('suppliers.deleteMessage', { name: deletingSupplier.name })"
        :confirm-text="$t('suppliers.confirmDelete')"
        :cancel-text="$t('common.cancel')"
        type="danger"
        @confirm="handleDelete"
        @cancel="deletingSupplier = null"
      />

      <ConfirmDialog
        v-if="showBulkDeleteConfirm"
        :title="$t('suppliers.bulkDeleteTitle')"
        :message="$t('suppliers.bulkDeleteMessage', selectedIds.size)"
        :confirm-text="$t('suppliers.confirmDelete')"
        :cancel-text="$t('common.cancel')"
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
import ImportButton from '@/components/common/ImportButton.vue'
import HistoryButton from '@/components/common/HistoryButton.vue'
import ImportModal from '@/components/common/ImportModal.vue'
import HistoryModal from '@/components/common/HistoryModal.vue'
import ImportHistoryPanel from '@/components/common/ImportHistoryPanel.vue'
import ExportHistoryPanel from '@/components/common/ExportHistoryPanel.vue'
import DateRangeFilters from '@/components/common/DateRangeFilters.vue'
import { useSuppliers } from '@/features/suppliers/composables/useSuppliers'
import { useExport } from '@/composables/useExport'
import { useRowSelection } from '@/composables/useRowSelection'
import { useRowHighlight } from '@/composables/useRowHighlight'
import { useClientPagination } from '@/composables/useClientPagination'
import {
  startSupplierExport,
  downloadSuppliersImportTemplate, previewSuppliersImport, revalidateSuppliersImport, startSuppliersImport,
} from '@/features/suppliers/services/supplierService'
import { fetchImportStatus } from '@/features/imports/services/importService'
import { SUPPLIER_COLUMNS, SUPPLIER_INITIAL_COL_WIDTHS } from '@/features/suppliers/constants'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

const columnVisibility = useColumnVisibility({
  storageKey: 'suppliers',
  columns: SUPPLIER_COLUMNS,
  lockedKeys: ['select', 'stt', 'actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(SUPPLIER_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const {
  suppliers, loading, searchQuery, storeFilter,
  startDate, endDate, dateField,
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

const selectableIds = computed(() =>
  sortedSuppliers.value.filter(canManageRow).map((s) => String(s.id)),
)
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: selectableIds, scopeToEligible: true })

const { mark, isRecent } = useRowHighlight()

const showForm        = ref(false)
const editingSupplier = ref(null)
const detailSupplier  = ref(null)
const deletingSupplier = ref(null)
const bulkDeleting    = ref(false)
const showBulkDeleteConfirm = ref(false)

const showImport  = ref(false)
const showHistory = ref(false)

const historyTabs = computed(() => [
  { key: 'imports', label: t('shared.imports'), component: ImportHistoryPanel, props: { scope: 'store', scopeId: currentStore.value?.id, type: 'suppliers' } },
  { key: 'exports', label: t('shared.exports'), component: ExportHistoryPanel, props: { scope: 'business', scopeId: currentBusiness.value?.id, types: ['suppliers'] } },
])

// Explains the supplier import rules in the import dialog (kept in sync with
// SupplierImporter on the backend).
const supplierImportInstructions = computed(() => [
  t('suppliers.import.required'),
  t('suppliers.import.unique'),
  t('suppliers.import.formats'),
  t('suppliers.import.store'),
])

const openCreate    = () => { editingSupplier.value = null; showForm.value = true }
const openEdit      = (s) => { editingSupplier.value = { ...s }; showForm.value = true }
const openDetail    = (s) => { detailSupplier.value = s }
const onDetailEdit  = (s) => { detailSupplier.value = null; openEdit(s) }

const onSaved = async () => {
  const editedId = editingSupplier.value?.id
  showForm.value = false
  await load()
  if (editedId) mark(editedId)
  showToast(editedId ? t('suppliers.updateSuccess') : t('suppliers.createSuccess'))
}

const onImported = () => {
  load()
}

const confirmDelete = (s) => { deletingSupplier.value = s }

const handleDelete = async () => {
  try {
    await removeOne(deletingSupplier.value)
    deletingSupplier.value = null
    showToast(t('suppliers.deleteSuccess'))
  } catch (err) {
    showToast(translateError(err), 'error')
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
    showToast(t('suppliers.deletedCount', count))
  } catch (err) {
    showToast(translateError(err), 'error')
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
    return startSupplierExport({ businessId: currentBusiness.value.id, params })
  },
  defaultFilename: (id) => `suppliers-${id}.xlsx`,
  onSuccess: () => showToast(t('suppliers.exportReady'), 'success'),
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
