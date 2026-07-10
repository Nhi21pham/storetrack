<template>
  <PageContainer :maxWidth="1200">
    <PageHeader :title="$t('reports.sale.title')" :subtitle="$t('reports.sale.subtitle')" />

    <EmptyState
      v-if="scope === 'store' && !currentStore"
      :title="$t('audit.noStoreTitle')"
      :description="$t('reports.sale.noStoreDesc')"
    />

    <EmptyState
      v-else-if="scope === 'business' && !currentBusiness"
      :title="$t('audit.noBusinessTitle')"
      :description="$t('reports.sale.noBusinessDesc')"
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" :placeholder="$t('reports.sale.search')" />
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

      <LoadingState v-if="loading">{{ $t('reports.sale.loading') }}</LoadingState>

      <EmptyState
        v-else-if="rows.length === 0"
        :title="$t('reports.noSalesTitle')"
        :description="$t('reports.sale.emptyDesc')"
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

          <template #filter-store_name>
            <SearchableSelect
              :modelValue="storeFilters"
              :options="storeOptions"
              :all-label="$t('reports.filters.allStores')"
              :search-placeholder="$t('reports.filters.filterStore')"
              multiple
              teleport
              @update:modelValue="storeFilters = $event"
            />
          </template>
          <template #filter-tags>
            <SearchableSelect
              :modelValue="tagFilter"
              :options="tagOptions"
              :all-label="$t('shared.allParen')"
              :search-placeholder="$t('reports.filters.filterTag')"
              multiple
              teleport
              @update:modelValue="tagFilter = $event"
            />
          </template>
          <template #filter-customer_name>
            <SearchableSelect
              :modelValue="customerFilters"
              :options="customerOptions"
              :all-label="$t('reports.filters.allCustomers')"
              :search-placeholder="$t('reports.filters.filterCustomer')"
              multiple
              teleport
              @update:modelValue="customerFilters = $event"
            />
          </template>
          <template #filter-quantity>
            <div class="qty-range">
              <input v-model="minQty" type="number" min="0" :placeholder="$t('reports.filters.min')" class="qty-input" />
              <input v-model="maxQty" type="number" min="0" :placeholder="$t('reports.filters.max')" class="qty-input" />
            </div>
          </template>

          <tr v-if="sortedRows.length === 0">
            <td :colspan="tableColumns.length" class="empty-row">
              {{ $t('reports.sale.noMatch') }}
            </td>
          </tr>
          <tr v-for="(row, idx) in paginatedRows" :key="row.id" :class="{ 'row-selected': isSelected(row.id) }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(row.id)" @change="toggleRow(row.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('order_number')" class="num">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
            <td v-if="scope === 'business'">
              <span v-if="row.store_name"><HighlightText :text="row.store_name" :query="searchQuery" /></span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('product_name')">
              <button v-if="row.product_id" class="name-link" @click="openProductDetail(row)"><HighlightText :text="row.product_name" :query="searchQuery" /></button>
              <span v-else><HighlightText :text="row.product_name" :query="searchQuery" /></span>
            </td>
            <td v-if="columnVisibility.isVisible('product_code')">
              <button v-if="row.product_code" class="code-link" @click="openProductDetail(row)"><HighlightText :text="row.product_code" :query="searchQuery" /></button>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('unit_name')">
              <span v-if="row.unit_name">{{ row.unit_name }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('tags')">
              <span v-if="row.tags && row.tags.length" class="tags-list">
                <TagChip v-for="(t, i) in row.tags" :key="i" :tag-name="t.tag_name" :value="t.value" :highlighted="isTagChipHit(t, tagFilter)" />
              </span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('customer_name')">
              <button v-if="row.customer_id" class="name-link" @click="openCustomerDetail(row)"><HighlightText :text="row.customer_name" :query="searchQuery" /></button>
              <span v-else-if="row.customer_name"><HighlightText :text="row.customer_name" :query="searchQuery" /></span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('purchase_invoice_code')">
              <button v-if="row.purchase_invoice_code" class="code-link" @click="openInvoiceDetail(row.purchase_invoice_id)"><HighlightText :text="row.purchase_invoice_code" :query="searchQuery" /></button>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('purchase_date')">{{ formatDate(row.purchase_date) }}</td>
            <td v-if="columnVisibility.isVisible('invoice_code')">
              <button v-if="row.invoice_code" class="code-link" @click="openInvoiceDetail(row.invoice_id)"><HighlightText :text="row.invoice_code" :query="searchQuery" /></button>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('invoice_date')">{{ formatDate(row.invoice_date) }}</td>
            <td v-if="columnVisibility.isVisible('quantity')" class="num">{{ formatQuantity(row.quantity) }}</td>
            <td v-if="columnVisibility.isVisible('unit_price')" class="num">{{ formatMoney(row.unit_price) }}</td>
            <td v-if="columnVisibility.isVisible('total_sale')" class="num strong">{{ formatMoney(row.total_sale) }}</td>
          </tr>
        </ResizableTable>

        <TotalsBar
          :items="[
            { label: $t('reports.sale.sumLines'), value: totals.lineCount },
            { label: $t('reports.sale.sumQtySold'), value: formatQuantity(totals.totalQty) },
            { label: $t('reports.sale.sumTotalSale'), value: formatMoney(totals.totalSale), strong: true },
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

      <ProductDetailModal
        v-if="detailProduct"
        :product="detailProduct"
        :can-edit="canManage"
        @close="detailProduct = null"
        @edit="onProductEdit"
      />

      <ProductFormModal
        v-if="editingProduct"
        :product="editingProduct"
        :store-id="currentStore?.id"
        @close="editingProduct = null"
        @saved="onProductSaved"
      />

      <CustomerDetailModal
        v-if="detailCustomer"
        :customer="detailCustomer"
        :can-edit="canManage"
        @close="detailCustomer = null"
        @edit="onCustomerEdit"
      />

      <CustomerFormModal
        v-if="editingCustomer"
        :customer="editingCustomer"
        @close="editingCustomer = null"
        @saved="onCustomerSaved"
      />

      <InvoiceDetailModal
        v-if="detailInvoice"
        :invoice="detailInvoice"
        :can-edit="canManage"
        @close="detailInvoice = null"
        @edit="onInvoiceEdit"
      />
    </template>

    <HistoryModal
      v-if="showHistory"
      :title="$t('reports.sale.exportHistoryTitle')"
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
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import ExportButton from '@/components/common/ExportButton.vue'
import HistoryButton from '@/components/common/HistoryButton.vue'
import HistoryModal from '@/components/common/HistoryModal.vue'
import ExportHistoryPanel from '@/components/common/ExportHistoryPanel.vue'
import Pagination from '@/components/common/Pagination.vue'
import TagChip from '@/components/common/TagChip.vue'
import HighlightText from '@/components/common/HighlightText.vue'
import TotalsBar from '@/components/common/TotalsBar.vue'
import ReportSelectionBar from '@/features/reports/components/ReportSelectionBar.vue'
import ProductDetailModal from '@/features/products/components/ProductDetailModal.vue'
import ProductFormModal from '@/features/products/components/ProductFormModal.vue'
import CustomerDetailModal from '@/features/customers/components/CustomerDetailModal.vue'
import CustomerFormModal from '@/features/customers/components/CustomerFormModal.vue'
import InvoiceDetailModal from '@/features/invoices/components/InvoiceDetailModal.vue'
import { useSaleReport } from '@/features/reports/composables/useSaleReport'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useRowSelection } from '@/composables/useRowSelection'
import { useClientPagination } from '@/composables/useClientPagination'
import { useExport } from '@/composables/useExport'
import { startSaleReportExport, startSaleReportBusinessExport } from '@/features/reports/services/reportService'
import { fetchProduct } from '@/features/products/services/productService'
import { fetchCustomer } from '@/features/customers/services/customerService'
import { fetchInvoice } from '@/features/invoices/services/invoiceService'
import { INVOICE_TYPE } from '@/features/invoices/constants'
import {
  SALE_REPORT_COLUMNS, SALE_REPORT_INITIAL_COL_WIDTHS,
  REPORT_STORE_COLUMN, REPORT_STORE_COLUMN_WIDTH,
  formatMoney, formatQuantity, formatDate,
} from '@/features/reports/constants'
import { isTagChipHit } from '@/utils/tagFilter'
import { t } from '@/i18n'

const router = useRouter()
const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')
const canManage = computed(() => !!currentStore.value?.is_active)

const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')
// Scope follows the store switcher: a selected store → that store's report;
// "Business level" (owner, no store selected) → consolidated all-stores report.
const scope = computed(() => (!currentStore.value && isBusinessOwner.value) ? 'business' : 'store')

const showHistory = ref(false)
const historyTabs = computed(() => {
  const isBiz = scope.value === 'business'
  return [{
    key: 'exports', label: t('shared.exports'), component: ExportHistoryPanel,
    props: {
      scope: isBiz ? 'business' : 'store',
      scopeId: isBiz ? currentBusiness.value?.id : currentStore.value?.id,
      types: isBiz ? ['sale-report-business'] : ['sale-report'],
    },
  }]
})

const columnVisibility = useColumnVisibility({
  storageKey: 'sale-report',
  columns: SALE_REPORT_COLUMNS,
  lockedKeys: ['select', 'order_number'],
})
const visibleWidths = computed(() => columnVisibility.filterWidths(SALE_REPORT_INITIAL_COL_WIDTHS))

// In business scope the Store column is inserted right after the order-number
// column (it is not user-togglable — it's what disambiguates rows across stores).
const tableColumns = computed(() => {
  const cols = columnVisibility.visibleColumns.value
  if (scope.value !== 'business') return cols
  const out = [...cols]
  const idx = out.findIndex((c) => c.key === 'order_number')
  out.splice(idx + 1, 0, REPORT_STORE_COLUMN)
  return out
})
const tableWidths = computed(() => {
  if (scope.value !== 'business') return visibleWidths.value
  const cols = columnVisibility.visibleColumns.value
  const idx = cols.findIndex((c) => c.key === 'order_number')
  const out = [...visibleWidths.value]
  out.splice(idx + 1, 0, REPORT_STORE_COLUMN_WIDTH)
  return out
})
const tableKey = computed(() => [scope.value, ...columnVisibility.visibleColumnKeys.value].join('|'))

const {
  rows, loading, searchQuery, customerFilters, storeFilters, tagFilter, minQty, maxQty, startDate, endDate,
  hasActiveFilters, clearFilters,
  sortedRows, customerOptions, storeOptions, tagOptions, totals, sort, load,
} = useSaleReport({
  currentStore,
  currentBusiness,
  scope,
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

const detailProduct = ref(null)
const detailInvoice = ref(null)
const editingProduct = ref(null)
const detailCustomer = ref(null)
const editingCustomer = ref(null)

const openProductDetail = async (row) => {
  try {
    detailProduct.value = await fetchProduct({ id: row.product_id })
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const openCustomerDetail = async (row) => {
  try {
    detailCustomer.value = await fetchCustomer({ id: row.customer_id })
  } catch (err) {
    showToast(err.message, 'error')
  }
}

// Both the sale invoice and the purchase invoice (the batch's origin) open in
// the same detail modal; it renders the right badge from the invoice's type.
const openInvoiceDetail = async (invoiceId) => {
  if (!invoiceId) return
  try {
    detailInvoice.value = await fetchInvoice({ id: invoiceId })
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const onProductEdit = (product) => {
  detailProduct.value = null
  editingProduct.value = product
}

const onProductSaved = async () => {
  editingProduct.value = null
  showToast(t('reports.productUpdated'), 'success')
  await load()
}

const onCustomerEdit = (customer) => {
  detailCustomer.value = null
  editingCustomer.value = { ...customer }
}

const onCustomerSaved = async () => {
  editingCustomer.value = null
  showToast(t('customers.updateSuccess'), 'success')
  await load()
}

// Invoices have full edit pages, so open the right one (sale or purchase) in a new tab.
const onInvoiceEdit = () => {
  const invoice = detailInvoice.value
  detailInvoice.value = null
  const base = invoice.type === INVOICE_TYPE.SALE ? 'sale-invoices' : 'purchase-invoices'
  window.open(router.resolve(`/${base}/${invoice.id}/edit`).href, '_blank')
}

const { exporting, run } = useExport({
  start: () => {
    // The export mirrors exactly what's on screen: the filtered rows in their
    // sorted order (scoped to the selection when any), sent as an ordered id list.
    const params = {
      ids: exportOrderedIds(),
      columns: columnVisibility.togglableColumns
        .filter((col) => columnVisibility.isVisible(col.key))
        .map((col) => col.key),
    }
    if (scope.value === 'business') {
      return startSaleReportBusinessExport({ businessId: currentBusiness.value.id, params })
    }
    return startSaleReportExport({ storeId: currentStore.value.id, params })
  },
  defaultFilename: (id) => `sale-report-${id}.xlsx`,
  onSuccess: () => showToast(t('reports.sale.exportReady'), 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

const exportOrderedIds = () => sortedRows.value
  .map((r) => String(r.id))
  .filter((id) => selectedIds.value.size === 0 || selectedIds.value.has(id))

watch([searchQuery, customerFilters, storeFilters, tagFilter, minQty, maxQty, startDate, endDate, () => scope.value, () => sort.sortCriteria.value], resetPage, { deep: true })
// Switching store/business (via the switcher) clears the cross-store filter and selection.
watch(scope, () => { storeFilters.value = [] })
watch([() => currentStore.value?.id, () => currentBusiness.value?.id], clearSelection)
</script>

<style scoped>
.toolbar { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }
.empty-row { padding: 24px 16px; text-align: center; color: #9ca3af; font-size: 13px; }

.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; }
.name-link:hover { color: #4338ca; text-decoration: underline; }
.code-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #4338ca; cursor: pointer; text-align: left; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.code-link:hover { text-decoration: underline; }
.tags-list { display: flex; flex-wrap: wrap; gap: 4px; }
.empty-val { color: #d1d5db; }
.num { text-align: right; font-variant-numeric: tabular-nums; }
.num.strong { font-weight: 700; color: #111; }

.qty-range { display: flex; flex-direction: column; gap: 4px; }
.qty-input { width: 100%; min-width: 0; box-sizing: border-box; padding: 5px 8px; min-height: 26px; font-size: 12px; border: 1px solid #d1d5db; border-radius: 6px; }
.qty-input:focus { outline: none; border-color: #111; }
.qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.qty-input { -moz-appearance: textfield; appearance: textfield; }


tbody tr.row-selected { background: #f0f7ff; }
tbody tr.row-selected:hover { background: #e6f0fb; }
</style>
