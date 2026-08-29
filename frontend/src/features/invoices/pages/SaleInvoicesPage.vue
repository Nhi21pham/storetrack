<template>
  <PageContainer :maxWidth="1100">
    <PageHeader :title="$t('invoices.saleTitle')" :subtitle="$t('invoices.saleSubtitle')">
      <template v-if="currentStore" #actions>
        <HistoryButton :label="$t('common.history')" :title="$t('invoices.viewScanExportHistory')" @click="showScanHistory = true" />
        <template v-if="currentStore.is_active">
          <ScanInvoiceButton to="/sale-invoices/scan" />
          <button class="btn-create" @click="goToCreate">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            {{ $t('invoices.newInvoice') }}
          </button>
        </template>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentStore"
      :title="$t('shared.noStoreTitle')"
      :description="$t('invoices.noStoreSaleDesc')"
    />

    <template v-else>
      <InactiveBanner v-if="!currentStore.is_active">
        {{ $t('invoices.inactiveStoreBanner') }}
      </InactiveBanner>

      <div class="toolbar">
        <SearchBar v-model="searchQuery" :placeholder="$t('invoices.searchSale')" />
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

      <LoadingState v-if="loading">{{ $t('invoices.loadingInvoices') }}</LoadingState>

      <EmptyState
        v-else-if="invoices.length === 0"
        :title="$t('invoices.noSaleInvoices')"
        :description="$t('invoices.noSaleInvoicesDescAlt')"
      />

      <div v-else class="table-wrap">
        <ResizableTable :key="tableKey" :columns="columnVisibility.visibleColumns.value" :initial-widths="visibleWidths" sticky-header>
          <template v-for="col in columnVisibility.visibleColumns.value" :key="col.key" #[`header-${col.key}`]="{ col: c }">
            <SelectCheckbox
              v-if="c.key === 'select'"
              :checked="allVisibleSelected"
              :indeterminate="someVisibleSelected"
              :title="$t('shared.selectAll')"
              @change="toggleSelectAll"
            />
            <SortableHeader
              v-else-if="c.sortable"
              :label="$t(c.labelKey)"
              :sort-info="sort.getSortInfo(c.key)"
              :rank="sort.sortCriteria.length > 1 && sort.getSortInfo(c.key) ? sort.sortRank(c.key) : null"
              @sort="(dir) => sort.toggleSort(c.key, dir)"
            />
            <template v-else>{{ c.labelKey ? $t(c.labelKey) : '' }}</template>
          </template>

          <template #filter-party_name>
            <SearchableSelect
              :modelValue="partyFilter"
              :options="partyOptions"
              :all-label="$t('invoices.allCustomers')"
              :search-placeholder="$t('invoices.filterCustomer')"
              teleport
              @update:modelValue="partyFilter = $event"
            />
          </template>
          <template #filter-payment_method>
            <SearchableSelect
              :modelValue="methodFilter"
              :options="paymentMethodOptions()"
              :all-label="$t('shared.allParen')"
              :search-placeholder="$t('invoices.filterPayment')"
              teleport
              @update:modelValue="methodFilter = $event"
            />
          </template>
          <template #filter-payment_status>
            <SearchableSelect
              :modelValue="statusFilter"
              :options="paymentStatusOptions()"
              :all-label="$t('shared.allParen')"
              :search-placeholder="$t('shared.filterStatus')"
              teleport
              @update:modelValue="statusFilter = $event"
            />
          </template>

          <tr v-if="sortedInvoices.length === 0">
            <td :colspan="columnVisibility.visibleColumns.value.length" class="empty-row">
              {{ $t('invoices.noMatch') }}
            </td>
          </tr>
          <tr v-for="(inv, idx) in paginatedInvoices" :key="inv.id" :class="{ 'row-selected': isSelected(inv.id) }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(inv.id)" @change="toggleRow(inv.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('stt')" class="stt-col">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
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
                <button class="action-btn" @click="openEdit(inv)" :title="$t('common.edit')"><Icon name="edit" :size="14" /></button>
                <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(inv)" :title="$t('common.delete')"><Icon name="delete" :size="14" /></button>
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
        :title="$t('invoices.deleteInvoiceTitle')"
        :message="$t('invoices.deleteSaleMessage', { code: deletingInvoice.code })"
        :confirm-text="$t('invoices.confirmDelete')"
        :cancel-text="$t('common.cancel')"
        type="danger"
        @confirm="handleDelete"
        @cancel="deletingInvoice = null"
      />

      <ConfirmDialog
        v-if="showBulkDeleteConfirm"
        :title="$t('invoices.deleteInvoicesTitle')"
        :message="$t('invoices.deleteSaleBulkMessage', selectedIds.size, { count: selectedIds.size })"
        :confirm-text="$t('invoices.confirmDelete')"
        :cancel-text="$t('common.cancel')"
        type="danger"
        @confirm="handleBulkDelete"
        @cancel="showBulkDeleteConfirm = false"
      />

      <HistoryModal
        v-if="showScanHistory"
        :title="$t('invoices.historyTitle')"
        :tabs="historyTabs"
        @close="showScanHistory = false"
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
import HistoryButton from '@/components/common/HistoryButton.vue'
import InvoiceSelectionBar from '@/features/invoices/components/InvoiceSelectionBar.vue'
import InvoiceDetailModal from '@/features/invoices/components/InvoiceDetailModal.vue'
import PaymentStatusBadge from '@/features/invoices/components/PaymentStatusBadge.vue'
import ScanInvoiceButton from '@/features/invoices/components/ScanInvoiceButton.vue'
import HistoryModal from '@/components/common/HistoryModal.vue'
import ScanHistoryPanel from '@/features/invoices/components/ScanHistoryPanel.vue'
import ExportHistoryPanel from '@/components/common/ExportHistoryPanel.vue'
import { useInvoices } from '@/features/invoices/composables/useInvoices'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useRowSelection } from '@/composables/useRowSelection'
import { useClientPagination } from '@/composables/useClientPagination'
import { useExport } from '@/composables/useExport'
import { t } from '@/i18n'
import { translateError } from '@/utils/translateError'
import { fetchInvoice, startInvoiceExport } from '@/features/invoices/services/invoiceService'
import {
  makeInvoiceColumns, INVOICE_INITIAL_COL_WIDTHS, INVOICE_TYPE,
  paymentMethodOptions, paymentStatusOptions,
  formatMoney, formatInvoiceDate, paymentMethodLabel,
} from '@/features/invoices/constants'

const router = useRouter()
const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const SALE_COLUMNS = makeInvoiceColumns('invoices.customer')

const columnVisibility = useColumnVisibility({
  storageKey: 'sale-invoices',
  columns: SALE_COLUMNS,
  lockedKeys: ['select', 'stt', 'actions'],
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
const showScanHistory = ref(false)

const historyTabs = computed(() => {
  const storeId = currentStore.value?.id
  return [
    { key: 'scans', label: t('shared.scans'), component: ScanHistoryPanel, props: { storeId, type: 'sale' } },
    { key: 'exports', label: t('shared.exports'), component: ExportHistoryPanel, props: { scope: 'store', scopeId: storeId, types: ['invoices', 'invoice-documents'] } },
  ]
})

const goToCreate = () => router.push('/sale-invoices/new')
const openEdit = (inv) => { detailInvoice.value = null; router.push(`/sale-invoices/${inv.id}/edit`) }

const openDetail = async (inv) => {
  try {
    detailInvoice.value = await fetchInvoice({ id: inv.id })
  } catch (err) {
    showToast(translateError(err), 'error')
  }
}

const onDetailEdit = (inv) => openEdit(inv)
const onDetailDelete = (inv) => { detailInvoice.value = null; deletingInvoice.value = inv }

const confirmDelete = (inv) => { deletingInvoice.value = inv }

const handleDelete = async () => {
  const target = deletingInvoice.value
  try {
    await removeOne(target)
    showToast(t('invoices.invoiceDeletedCode', { code: target.code }))
    deletingInvoice.value = null
  } catch (err) {
    showToast(translateError(err), 'error')
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
      showToast(t('invoices.bulkDeletePartialSale', { deleted, failed }), 'error')
    } else {
      showToast(t('invoices.bulkDeleted', deleted, { count: deleted }))
    }
  } catch (err) {
    showToast(translateError(err), 'error')
  } finally {
    bulkDeleting.value = false
  }
}

// Shared filter set for both exports: the active filters, plus the selected
// ids when a selection is active (otherwise the whole filtered view).
const baseExportParams = () => {
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
  return params
}

const { exporting, run } = useExport({
  start: () => {
    const params = baseExportParams()
    params.columns = columnVisibility.togglableColumns
      .filter((col) => columnVisibility.isVisible(col.key))
      .map((col) => col.key)
    return startInvoiceExport({ storeId: currentStore.value.id, params })
  },
  defaultFilename: (id) => `sale-invoices-${id}.xlsx`,
  onSuccess: () => showToast(t('invoices.exportReady'), 'success'),
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
.stt-col { color: #6b7280; font-variant-numeric: tabular-nums; }
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
