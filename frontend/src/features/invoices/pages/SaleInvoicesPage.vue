<template>
  <PageContainer :maxWidth="1100">
    <PageHeader title="Sale Invoices" subtitle="Sales recorded to your customers.">
      <template v-if="currentStore?.is_active" #actions>
        <button class="btn-create" @click="goToCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          New invoice
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentStore"
      title="No store selected"
      description="Select a store to view its sale invoices."
    />

    <template v-else>
      <InactiveBanner v-if="!currentStore.is_active">
        This store is deactivated. Data is read-only until the store is reactivated.
      </InactiveBanner>

      <div class="toolbar">
        <SearchBar v-model="searchQuery" placeholder="Search by code or customer..." />
        <DateRangeFilter v-model:startDate="startDate" v-model:endDate="endDate" />
        <ClearFiltersButton v-if="hasActiveFilters" @click="clearFilters" />
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
        <ExportButton :exporting="exporting" :disabled="sortedInvoices.length === 0" @click="run" />
      </div>

      <InvoiceSelectionBar
        v-if="selectedIds.size > 0"
        :count="selectedIds.size"
        :exporting="exporting"
        :bulkDeleting="bulkDeleting"
        :canDelete="!!currentStore?.is_active && canDelete"
        @clear="clearSelection"
        @export="run"
        @delete="confirmBulkDelete"
      />

      <LoadingState v-if="loading">Loading invoices…</LoadingState>

      <EmptyState
        v-else-if="invoices.length === 0"
        title="No sale invoices yet"
        description="Record your first sale to start tracking revenue and profit."
      />

      <div v-else class="table-wrap">
        <ResizableTable :key="tableKey" :columns="columnVisibility.visibleColumns.value" :initial-widths="visibleWidths">
          <template v-for="col in columnVisibility.visibleColumns.value" :key="col.key" #[`header-${col.key}`]="{ col: c }">
            <SelectCheckbox
              v-if="c.key === 'select'"
              :checked="allVisibleSelected"
              :indeterminate="someVisibleSelected"
              title="Select all"
              @change="toggleSelectAll"
            />
            <SortableHeader
              v-else-if="c.sortable"
              :label="c.label"
              :sort-info="sort.getSortInfo(c.key)"
              :rank="sort.sortCriteria.length > 1 && sort.getSortInfo(c.key) ? sort.sortRank(c.key) : null"
              @sort="(dir) => sort.toggleSort(c.key, dir)"
            />
            <template v-else>{{ c.label }}</template>
          </template>

          <template #filter-party_name>
            <SearchableSelect
              :modelValue="partyFilter"
              :options="partyOptions"
              all-label="(All customers)"
              search-placeholder="Filter customer..."
              teleport
              @update:modelValue="partyFilter = $event"
            />
          </template>
          <template #filter-payment_method>
            <SearchableSelect
              :modelValue="methodFilter"
              :options="PAYMENT_METHODS"
              all-label="(All)"
              search-placeholder="Filter payment..."
              teleport
              @update:modelValue="methodFilter = $event"
            />
          </template>
          <template #filter-payment_status>
            <SearchableSelect
              :modelValue="statusFilter"
              :options="PAYMENT_STATUSES"
              all-label="(All)"
              search-placeholder="Filter status..."
              teleport
              @update:modelValue="statusFilter = $event"
            />
          </template>

          <tr v-if="sortedInvoices.length === 0">
            <td :colspan="columnVisibility.visibleColumns.value.length" class="empty-row">
              No invoices match the current filters.
            </td>
          </tr>
          <tr v-for="inv in paginatedInvoices" :key="inv.id" :class="{ 'row-selected': isSelected(inv.id) }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(inv.id)" @change="toggleRow(inv.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('code')">
              <button class="code-link" @click="openDetail(inv)">{{ inv.code }}</button>
            </td>
            <td v-if="columnVisibility.isVisible('invoice_date')">{{ formatInvoiceDate(inv.invoice_date) }}</td>
            <td v-if="columnVisibility.isVisible('party_name')">
              <span v-if="inv.party_name">{{ inv.party_name }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('payment_method')">{{ paymentMethodLabel(inv.payment_method) }}</td>
            <td v-if="columnVisibility.isVisible('payment_status')"><PaymentStatusBadge :status="inv.payment_status" /></td>
            <td v-if="columnVisibility.isVisible('subtotal')" class="num">{{ formatMoney(inv.subtotal) }}</td>
            <td v-if="columnVisibility.isVisible('tax_total')" class="num">{{ formatMoney(inv.tax_total) }}</td>
            <td v-if="columnVisibility.isVisible('grand_total')" class="num strong">{{ formatMoney(inv.grand_total) }}</td>
            <td class="actions-col">
              <div v-if="currentStore.is_active" class="row-actions">
                <button class="action-btn" @click="openEdit(inv)" title="Edit"><Icon name="edit" :size="14" /></button>
                <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(inv)" title="Delete"><Icon name="delete" :size="14" /></button>
              </div>
            </td>
          </tr>
        </ResizableTable>

        <Pagination
          v-if="total > 0"
          :current-page="currentPage"
          :total-pages="totalPages"
          :total="total"
          :per-page="perPage"
          @update:current-page="currentPage = $event"
          @update:per-page="setPerPage"
        />
      </div>

      <InvoiceDetailModal
        v-if="detailInvoice"
        :invoice="detailInvoice"
        :can-manage="!!currentStore?.is_active"
        @edit="onDetailEdit(detailInvoice)"
        @delete="onDetailDelete(detailInvoice)"
        @close="detailInvoice = null"
      />

      <ConfirmDialog
        v-if="deletingInvoice"
        title="Delete invoice?"
        :message="`This permanently deletes ${deletingInvoice.code} and returns the stock it consumed to inventory. This can't be undone.`"
        confirm-text="Yes, delete"
        cancel-text="Cancel"
        type="danger"
        @confirm="handleDelete"
        @cancel="deletingInvoice = null"
      />

      <ConfirmDialog
        v-if="showBulkDeleteConfirm"
        title="Delete invoices?"
        :message="`Delete ${selectedIds.size} invoice${selectedIds.size === 1 ? '' : 's'} and return the stock they consumed to inventory? This can't be undone.`"
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
import { useRouter } from 'vue-router'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import InactiveBanner from '@/components/common/InactiveBanner.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import ClearFiltersButton from '@/components/common/ClearFiltersButton.vue'
import DateRangeFilter from '@/components/common/DateRangeFilter.vue'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import ExportButton from '@/components/common/ExportButton.vue'
import Icon from '@/components/common/Icon.vue'
import InvoiceSelectionBar from '@/features/invoices/components/InvoiceSelectionBar.vue'
import InvoiceDetailModal from '@/features/invoices/components/InvoiceDetailModal.vue'
import PaymentStatusBadge from '@/features/invoices/components/PaymentStatusBadge.vue'
import { useInvoices } from '@/features/invoices/composables/useInvoices'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useRowSelection } from '@/composables/useRowSelection'
import { useClientPagination } from '@/composables/useClientPagination'
import { useExport } from '@/composables/useExport'
import { fetchInvoice, startInvoiceExport } from '@/features/invoices/services/invoiceService'
import {
  makeInvoiceColumns, INVOICE_INITIAL_COL_WIDTHS, INVOICE_TYPE,
  PAYMENT_METHODS, PAYMENT_STATUSES,
  formatMoney, formatInvoiceDate, paymentMethodLabel,
} from '@/features/invoices/constants'

const router = useRouter()
const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const SALE_COLUMNS = makeInvoiceColumns('Customer')

const columnVisibility = useColumnVisibility({
  storageKey: 'sale-invoices',
  columns: SALE_COLUMNS,
  lockedKeys: ['select', 'actions'],
})
const visibleWidths = computed(() => columnVisibility.filterWidths(INVOICE_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const {
  invoices, loading, searchQuery, statusFilter, methodFilter, partyFilter, startDate, endDate,
  canDelete, hasActiveFilters, clearFilters,
  sortedInvoices, partyOptions, sort,
  removeOne, removeMany,
} = useInvoices({
  currentStore,
  currentBusiness,
  onError: (msg) => showToast(msg, 'error'),
  type: INVOICE_TYPE.SALE,
})

const {
  currentPage, perPage, total, totalPages,
  paginated: paginatedInvoices, setPerPage, resetPage,
} = useClientPagination(sortedInvoices)

const selectableIds = computed(() => sortedInvoices.value.map((i) => String(i.id)))
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: selectableIds, scopeToEligible: true })

const detailInvoice = ref(null)
const deletingInvoice = ref(null)
const bulkDeleting = ref(false)
const showBulkDeleteConfirm = ref(false)

const goToCreate = () => router.push('/sale-invoices/new')
const openEdit = (inv) => { detailInvoice.value = null; router.push(`/sale-invoices/${inv.id}/edit`) }

const openDetail = async (inv) => {
  try {
    detailInvoice.value = await fetchInvoice({ id: inv.id })
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const onDetailEdit = (inv) => openEdit(inv)
const onDetailDelete = (inv) => { detailInvoice.value = null; deletingInvoice.value = inv }

const confirmDelete = (inv) => { deletingInvoice.value = inv }

const handleDelete = async () => {
  const target = deletingInvoice.value
  try {
    await removeOne(target)
    showToast(`Invoice ${target.code} deleted.`)
    deletingInvoice.value = null
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const confirmBulkDelete = () => { showBulkDeleteConfirm.value = true }

const handleBulkDelete = async () => {
  if (bulkDeleting.value) return
  bulkDeleting.value = true
  try {
    const { deleted, failed } = await removeMany(Array.from(selectedIds.value))
    showBulkDeleteConfirm.value = false
    clearSelection()
    if (failed > 0) {
      showToast(`Deleted ${deleted}. ${failed} could not be deleted.`, 'error')
    } else {
      showToast(`Deleted ${deleted} invoice${deleted === 1 ? '' : 's'}.`)
    }
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    bulkDeleting.value = false
  }
}

const { exporting, run } = useExport({
  start: () => {
    const params = {
      type: INVOICE_TYPE.SALE,
      search: searchQuery.value.trim() || undefined,
      payment_method: methodFilter.value || undefined,
      payment_status: statusFilter.value || undefined,
      party_id: partyFilter.value || undefined,
      start_date: startDate.value || undefined,
      end_date: endDate.value || undefined,
    }
    if (selectedIds.value.size > 0) {
      params.ids = Array.from(selectedIds.value)
    }
    params.columns = columnVisibility.togglableColumns
      .filter((col) => columnVisibility.isVisible(col.key))
      .map((col) => col.key)
    return startInvoiceExport({ storeId: currentStore.value.id, params })
  },
  defaultFilename: (id) => `sale-invoices-${id}.xlsx`,
  onSuccess: () => showToast('Invoice export ready.', 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

watch([searchQuery, statusFilter, methodFilter, partyFilter, startDate, endDate, () => sort.sortCriteria.value], resetPage, { deep: true })
watch(() => currentStore.value?.id, clearSelection)
</script>

<style scoped>
.btn-create { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
.btn-create:hover { background: #333; }

.toolbar { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }
.empty-row { padding: 24px 16px; text-align: center; color: #9ca3af; font-size: 13px; }

.code-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #4338ca; cursor: pointer; text-align: left; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.code-link:hover { text-decoration: underline; }
.empty-val { color: #d1d5db; }
.num { text-align: right; font-variant-numeric: tabular-nums; }
.num.strong { font-weight: 700; color: #111; }

tbody tr.row-selected { background: #f0f7ff; }
tbody tr.row-selected:hover { background: #e6f0fb; }
tbody tr.row-selected td.actions-col { background: #f0f7ff; }
tbody tr.row-selected:hover td.actions-col { background: #e6f0fb; }

.actions-col { text-align: right; white-space: nowrap; }
.row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
