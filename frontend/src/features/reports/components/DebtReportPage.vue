<template>
  <PageContainer :maxWidth="1200">
    <PageHeader :title="title" :subtitle="subtitle" />

    <EmptyState
      v-if="scope === 'store' && !currentStore"
      :title="$t('audit.noStoreTitle')"
      :description="$t('reports.debt.noStoreDesc')"
    />

    <EmptyState
      v-else-if="scope === 'business' && !currentBusiness"
      :title="$t('audit.noBusinessTitle')"
      :description="$t('reports.debt.noBusinessDesc')"
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" :placeholder="$t('reports.debt.search', { party: partyLabel.toLowerCase() })" />
        <DateRangeFilter v-model:startDate="startDate" v-model:endDate="endDate" />
        <ClearFiltersButton v-if="hasActiveFilters" @click="clearFilters" />
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
        <ExportButton :exporting="exporting" :disabled="sortedRows.length === 0" @click="run" />
        <HistoryButton :label="$t('reports.exportHistory')" :title="$t('reports.viewExportHistory')" @click="showHistory = true" />
      </div>

      <ReportSelectionBar
        v-if="selectedIds.size > 0"
        :count="selectedIds.size"
        :exporting="exporting"
        @clear="clearSelection"
        @export="run"
      />

      <LoadingState v-if="loading">{{ $t('reports.debt.loading') }}</LoadingState>

      <EmptyState
        v-else-if="rows.length === 0"
        :title="$t('reports.debt.emptyTitle', { party: partyLabel.toLowerCase() })"
        :description="emptyDescription"
      />

      <div v-else class="table-wrap">
        <ResizableTable :key="tableKey" :columns="tableColumns" :initial-widths="tableWidths" sticky-header>
          <template v-for="col in tableColumns" :key="col.key" #[`header-${col.key}`]="{ col: c }">
            <SelectCheckbox
              v-if="c.key === 'select'"
              :checked="allVisibleSelected"
              :indeterminate="someVisibleSelected"
              :title="$t('shared.selectAll')"
              @change="toggleSelectAll"
            />
            <SortableHeader
              v-else-if="c.sortable"
              :label="c.labelKey ? $t(c.labelKey) : c.label"
              :sort-info="sort.getSortInfo(c.key)"
              :rank="sort.sortCriteria.length > 1 && sort.getSortInfo(c.key) ? sort.sortRank(c.key) : null"
              @sort="(dir) => sort.toggleSort(c.key, dir)"
            />
            <template v-else>{{ c.labelKey ? $t(c.labelKey) : (c.label || '') }}</template>
          </template>

          <template v-for="(row, idx) in paginatedRows" :key="row.id">
            <tr :class="{ 'row-selected': isSelected(row.id) }">
              <td v-if="columnVisibility.isVisible('select')">
                <SelectCheckbox :checked="isSelected(row.id)" @change="toggleRow(row.id)" />
              </td>
              <td v-if="columnVisibility.isVisible('order_number')" class="num">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
              <td v-if="columnVisibility.isVisible('name')">
                <button class="expand-btn" :class="{ open: isExpanded(row.id) }" @click="toggleExpand(row.id)" :title="isExpanded(row.id) ? $t('reports.debt.collapse') : $t('reports.debt.expand')">▸</button>
                <button class="name-link" @click="openPartyDetail(row)">{{ row.name }}</button>
              </td>
              <td v-if="columnVisibility.isVisible('phone')">
                <span v-if="row.phone">{{ row.phone }}</span>
                <span v-else class="empty-val">—</span>
              </td>
              <td v-if="columnVisibility.isVisible('email')">
                <span v-if="row.email">{{ row.email }}</span>
                <span v-else class="empty-val">—</span>
              </td>
              <td v-if="columnVisibility.isVisible('invoice_count')" class="num">{{ row.invoice_count }}</td>
              <td v-if="columnVisibility.isVisible('spent')" class="num">{{ formatMoney(row.spent) }}</td>
              <td v-if="columnVisibility.isVisible('paid')" class="num">{{ formatMoney(row.paid) }}</td>
              <td v-if="columnVisibility.isVisible('owe')" class="num strong" :class="{ owed: row.owe > 0, credit: row.owe < 0 }">{{ oweDisplay(row.owe) }}</td>
            </tr>
            <tr v-if="isExpanded(row.id)" class="detail-row">
              <td :colspan="tableColumns.length">
                <div class="detail">
                  <div class="detail-col invoices">
                    <h4>{{ invoiceSectionLabel }} <span class="muted">({{ row.rangeInvoices.length }})</span></h4>
                    <table v-if="row.rangeInvoices.length" class="detail-table">
                      <thead>
                        <tr><th>{{ $t('reports.debt.code') }}</th><th>{{ $t('reports.debt.date') }}</th><th v-if="scope === 'business'">{{ $t('reports.col.store') }}</th><th class="num">{{ $t('reports.debt.amount') }}</th></tr>
                      </thead>
                      <tbody>
                        <tr v-for="inv in row.rangeInvoices" :key="inv.id">
                          <td><button class="code-link" @click="openInvoiceDetail(inv.id)">{{ inv.code }}</button></td>
                          <td>{{ formatDate(inv.invoice_date) }}</td>
                          <td v-if="scope === 'business'"><span v-if="inv.store_name" class="store-tag">{{ inv.store_name }}</span></td>
                          <td class="num">{{ formatMoney(inv.grand_total) }}</td>
                        </tr>
                      </tbody>
                    </table>
                    <p v-else class="detail-empty">{{ $t('reports.debt.noInvoicesInRange') }}</p>
                  </div>

                  <div class="detail-col payments">
                    <h4>{{ $t('reports.debt.paymentsInRange') }} <span class="muted">({{ row.rangePayments.length }})</span></h4>
                    <table v-if="row.rangePayments.length" class="detail-table">
                      <thead>
                        <tr><th>{{ $t('reports.debt.date') }}</th><th>{{ $t('reports.debt.method') }}</th><th v-if="scope === 'business'">{{ $t('reports.col.store') }}</th><th>{{ $t('reports.debt.appliedTo') }}</th><th class="num">{{ $t('reports.debt.amount') }}</th></tr>
                      </thead>
                      <tbody>
                        <tr v-for="p in row.rangePayments" :key="p.id">
                          <td>{{ formatDate(p.paid_at) }}</td>
                          <td>{{ paymentMethodLabel(p.method) }}</td>
                          <td v-if="scope === 'business'"><span v-if="p.store_name" class="store-tag">{{ p.store_name }}</span></td>
                          <td class="applied">
                            <span v-for="a in p.allocations" :key="a.invoice_id" class="alloc">
                              <button class="code-link" @click="openInvoiceDetail(a.invoice_id)">{{ a.invoice_code }}</button>
                            </span>
                          </td>
                          <td class="num">{{ formatMoney(p.amount) }}</td>
                        </tr>
                      </tbody>
                    </table>
                    <p v-else class="detail-empty">{{ $t('reports.debt.noPaymentsInRange') }}</p>
                  </div>
                </div>
              </td>
            </tr>
          </template>

          <tr v-if="sortedRows.length === 0">
            <td :colspan="tableColumns.length" class="empty-row">
              {{ $t('reports.debt.noMatch') }}
            </td>
          </tr>
        </ResizableTable>

        <TotalsBar
          :items="[
            { label: partyLabel, value: totals.partyCount },
            { label: spentLabel, value: formatMoney(totals.spent) },
            { label: $t('reports.debt.sumTotalPaid'), value: formatMoney(totals.paid) },
            { label: $t('reports.debt.sumTotalOwe'), value: oweDisplay(totals.owe), strong: true },
          ]"
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
      </div>

      <InvoiceDetailModal
        v-if="detailInvoice"
        :invoice="detailInvoice"
        :can-manage="false"
        :can-edit="canManage"
        @close="detailInvoice = null"
        @edit="onInvoiceEdit"
      />

      <component
        :is="detailComponent"
        v-if="detailParty"
        v-bind="{ [partyType]: detailParty }"
        :can-edit="canManage"
        @close="detailParty = null"
        @edit="onPartyEdit"
      />

      <component
        :is="formComponent"
        v-if="editingParty"
        v-bind="{ [partyType]: editingParty }"
        @close="editingParty = null"
        @saved="onPartySaved"
      />
    </template>

    <HistoryModal
      v-if="showHistory"
      :title="$t('reports.debt.exportHistoryTitle')"
      :tabs="historyTabs"
      @close="showHistory = false"
    />
  </PageContainer>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue'
import { useRouter } from 'vue-router'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import ClearFiltersButton from '@/components/common/ClearFiltersButton.vue'
import DateRangeFilter from '@/components/common/DateRangeFilter.vue'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import ExportButton from '@/components/common/ExportButton.vue'
import HistoryButton from '@/components/common/HistoryButton.vue'
import HistoryModal from '@/components/common/HistoryModal.vue'
import ExportHistoryPanel from '@/components/common/ExportHistoryPanel.vue'
import Pagination from '@/components/common/Pagination.vue'
import TotalsBar from '@/components/common/TotalsBar.vue'
import ReportSelectionBar from '@/features/reports/components/ReportSelectionBar.vue'
import InvoiceDetailModal from '@/features/invoices/components/InvoiceDetailModal.vue'
import CustomerDetailModal from '@/features/customers/components/CustomerDetailModal.vue'
import SupplierDetailModal from '@/features/suppliers/components/SupplierDetailModal.vue'
import CustomerFormModal from '@/features/customers/components/CustomerFormModal.vue'
import SupplierFormModal from '@/features/suppliers/components/SupplierFormModal.vue'
import { useDebtReport } from '@/features/reports/composables/useDebtReport'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useRowSelection } from '@/composables/useRowSelection'
import { useClientPagination } from '@/composables/useClientPagination'
import { useExport } from '@/composables/useExport'
import { fetchInvoice } from '@/features/invoices/services/invoiceService'
import { fetchCustomer } from '@/features/customers/services/customerService'
import { fetchSupplier } from '@/features/suppliers/services/supplierService'
import { INVOICE_TYPE, paymentMethodLabel } from '@/features/invoices/constants'
import {
  makeDebtReportColumns, DEBT_REPORT_INITIAL_COL_WIDTHS, DEBT_REPORT_DEFAULT_HIDDEN,
  formatMoney, formatDate,
} from '@/features/reports/constants'
import { t } from '@/i18n'

const oweDisplay = (owe) => (owe < 0 ? `${formatMoney(-owe)} ${t('reports.debt.credit')}` : formatMoney(owe))

const props = defineProps({
  ledger:        { type: String, required: true },   // 'receivable' | 'payable'
  title:         { type: String, required: true },
  subtitle:      { type: String, required: true },
  partyLabel:    { type: String, required: true },   // 'Customer' | 'Supplier'
  spentLabel:    { type: String, required: true },   // 'Total Spent' | 'Total Purchased'
  invoiceSectionLabel: { type: String, required: true }, // 'Invoices in range' wording
  emptyDescription: { type: String, required: true },
  fetchers:      { type: Object, required: true },   // { store, business }
  exporters:     { type: Object, required: true },   // { store, business }
  exportFilenamePrefix: { type: String, required: true },
})

const router = useRouter()
const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')
const canManage = computed(() => !!currentStore.value?.is_active)

const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')
// Scope follows the store switcher, mirroring the other reports.
const scope = computed(() => (!currentStore.value && isBusinessOwner.value) ? 'business' : 'store')

const showHistory = ref(false)
const historyTabs = computed(() => {
  const base = props.ledger === 'payable' ? 'payables-report' : 'receivables-report'
  const isBiz = scope.value === 'business'
  return [{
    key: 'exports', label: t('shared.exports'), component: ExportHistoryPanel,
    props: {
      scope: isBiz ? 'business' : 'store',
      scopeId: isBiz ? currentBusiness.value?.id : currentStore.value?.id,
      types: isBiz ? [`${base}-business`] : [base],
    },
  }]
})

const columns = makeDebtReportColumns({ partyLabel: props.partyLabel, spentLabel: props.spentLabel })
const columnVisibility = useColumnVisibility({
  storageKey: `${props.ledger}-debt-report`,
  columns,
  lockedKeys: ['select', 'order_number', 'name'],
  defaultHiddenKeys: DEBT_REPORT_DEFAULT_HIDDEN,
})

const tableColumns = computed(() => columnVisibility.visibleColumns.value)
const tableWidths = computed(() => columnVisibility.filterWidths(DEBT_REPORT_INITIAL_COL_WIDTHS))
const tableKey = computed(() => [scope.value, ...columnVisibility.visibleColumnKeys.value].join('|'))

const {
  rows, loading, searchQuery, startDate, endDate,
  hasActiveFilters, clearFilters,
  sortedRows, totals, sort, load,
} = useDebtReport({
  currentStore,
  currentBusiness,
  scope,
  fetchers: props.fetchers,
  onError: (msg) => showToast(msg, 'error'),
})

const {
  currentPage, perPage, total, totalPages,
  paginated: paginatedRows, setPerPage, resetPage,
} = useClientPagination(sortedRows)

const selectableIds = computed(() => sortedRows.value.map((r) => String(r.id)))
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: selectableIds, scopeToEligible: true })

const expandedIds = ref(new Set())
const isExpanded = (id) => expandedIds.value.has(String(id))
const toggleExpand = (id) => {
  const key = String(id)
  const next = new Set(expandedIds.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  expandedIds.value = next
}

const detailInvoice = ref(null)
const openInvoiceDetail = async (id) => {
  try {
    detailInvoice.value = await fetchInvoice({ id })
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const onInvoiceEdit = () => {
  const invoice = detailInvoice.value
  detailInvoice.value = null
  const base = invoice.type === INVOICE_TYPE.SALE ? 'sale-invoices' : 'purchase-invoices'
  window.open(router.resolve(`/${base}/${invoice.id}/edit`).href, '_blank')
}

const partyType = computed(() => (props.ledger === 'payable' ? 'supplier' : 'customer'))
const detailComponent = computed(() => (partyType.value === 'supplier' ? SupplierDetailModal : CustomerDetailModal))
const formComponent = computed(() => (partyType.value === 'supplier' ? SupplierFormModal : CustomerFormModal))
const fetchParty = (id) => (partyType.value === 'supplier' ? fetchSupplier({ id }) : fetchCustomer({ id }))

const detailParty = ref(null)
const editingParty = ref(null)

const openPartyDetail = async (row) => {
  if (!row.party_record_id) return
  try {
    detailParty.value = await fetchParty(row.party_record_id)
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const onPartyEdit = (party) => {
  detailParty.value = null
  editingParty.value = { ...party }
}

const onPartySaved = async () => {
  editingParty.value = null
  showToast(t(partyType.value === 'supplier' ? 'suppliers.updateSuccess' : 'customers.updateSuccess'), 'success')
  await load()
}

const { exporting, run } = useExport({
  start: () => {
    const params = {
      search: searchQuery.value.trim() || undefined,
      start_date: startDate.value || undefined,
      end_date: endDate.value || undefined,
    }
    if (selectedIds.value.size > 0) {
      params.ids = Array.from(selectedIds.value)
    }
    params.columns = columnVisibility.togglableColumns
      .filter((col) => columnVisibility.isVisible(col.key))
      .map((col) => col.key)
    if (scope.value === 'business') {
      return props.exporters.business({ businessId: currentBusiness.value.id, params })
    }
    return props.exporters.store({ storeId: currentStore.value.id, params })
  },
  defaultFilename: (id) => `${props.exportFilenamePrefix}-${id}.xlsx`,
  onSuccess: () => showToast(t('reports.debt.exportReady'), 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

watch([searchQuery, startDate, endDate, () => scope.value, () => sort.sortCriteria.value], resetPage, { deep: true })
watch([() => currentStore.value?.id, () => currentBusiness.value?.id], () => {
  clearSelection()
  expandedIds.value = new Set()
})
</script>

<style scoped>
.toolbar { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }
.empty-row { padding: 24px 16px; text-align: center; color: #9ca3af; font-size: 13px; }

.expand-btn { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: #eef2ff; border: 1px solid #c7d2fe; color: #4f46e5; font-size: 10px; line-height: 1; cursor: pointer; margin-right: 8px; vertical-align: middle; transition: transform 0.15s, background 0.15s, color 0.15s, border-color 0.15s; }
.expand-btn:hover { background: #e0e7ff; }
.expand-btn.open { transform: rotate(90deg); background: #4f46e5; color: #fff; border-color: #4f46e5; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; vertical-align: middle; }
.name-link:hover { color: #4338ca; text-decoration: underline; }

.code-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #4338ca; cursor: pointer; text-align: left; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.code-link:hover { text-decoration: underline; }
.empty-val { color: #d1d5db; }
.num { text-align: right; font-variant-numeric: tabular-nums; }
.num.strong { font-weight: 700; color: #111; }
.num.owed { color: #b45309; }
.num.credit { color: #15803d; }

/* Only the outer colspan cell — a direct-child selector so it doesn't clobber the nested detail-table cell padding. */
.detail-row > td { background: #eef1f5; padding: 0 !important; }
.detail { display: flex; gap: 20px; padding: 18px 20px; flex-wrap: wrap; align-items: flex-start; }
/* Two solid, boxy blocks with distinct colors so they read as separate at a glance. */
.detail-col { flex: 1; min-width: 300px; border: 2px solid; border-radius: 4px; overflow: hidden; background: #fff; }
.detail-col.invoices { border-color: #4f46e5; }
.detail-col.payments { border-color: #047857; }
.detail-col h4 { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 600; color: #fff; text-transform: uppercase; letter-spacing: 0.04em; margin: 0; padding: 11px 18px; }
.detail-col.invoices h4 { background: #4f46e5; }
.detail-col.payments h4 { background: #047857; }
.detail-col h4 .muted { color: rgba(255, 255, 255, 0.78); font-weight: 600; }
.detail-table { width: 100%; border-collapse: collapse; font-size: 13.5px; background: #fff; }
.detail-table th { text-align: left; padding: 10px 18px; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #eef0f2; background: #f8f9fb; }
.detail-table td { padding: 11px 18px; border-bottom: 1px solid #f3f4f6; color: #374151; }
.detail-table tr:last-child td { border-bottom: none; }
.detail-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.applied { display: flex; flex-wrap: wrap; gap: 8px; }
.alloc { display: inline-flex; }
.store-tag { display: inline-block; padding: 1px 7px; background: #eef2ff; color: #4338ca; border-radius: 6px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.detail-empty { padding: 18px; text-align: center; color: #9ca3af; font-size: 13.5px; background: #fff; }

tbody tr.row-selected { background: #f0f7ff; }
tbody tr.row-selected:hover { background: #e6f0fb; }
</style>
