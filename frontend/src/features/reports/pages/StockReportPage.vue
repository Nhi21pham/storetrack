<template>
  <PageContainer :maxWidth="1200">
    <PageHeader :title="$t('reports.stock.title')" :subtitle="$t('reports.stock.subtitle')" />

    <EmptyState
      v-if="scope === 'store' && !currentStore"
      :title="$t('audit.noStoreTitle')"
      :description="$t('reports.stock.noStoreDesc')"
    />

    <EmptyState
      v-else-if="scope === 'business' && !currentBusiness"
      :title="$t('audit.noBusinessTitle')"
      :description="$t('reports.stock.noBusinessDesc')"
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" :placeholder="$t('reports.stock.search')" />
        <DateRangeFilter v-model:startDate="startDate" v-model:endDate="endDate" />
        <label class="oos-toggle">
          <input type="checkbox" v-model="includeOutOfStock" />
          {{ $t('reports.stock.showOutOfStock') }}
        </label>
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

      <LoadingState v-if="loading">{{ $t('reports.stock.loading') }}</LoadingState>

      <EmptyState
        v-else-if="rows.length === 0"
        :title="$t('reports.stock.emptyTitle')"
        :description="$t('reports.stock.emptyDesc')"
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
          <template #filter-supplier_name>
            <SearchableSelect
              :modelValue="supplierFilters"
              :options="supplierOptions"
              :all-label="$t('reports.filters.allSuppliers')"
              :search-placeholder="$t('reports.filters.filterSupplier')"
              multiple
              teleport
              @update:modelValue="supplierFilters = $event"
            />
          </template>
          <template #filter-quantity_remaining>
            <div class="qty-range">
              <input v-model="minQty" type="number" min="0" :placeholder="$t('reports.filters.min')" class="qty-input" />
              <input v-model="maxQty" type="number" min="0" :placeholder="$t('reports.filters.max')" class="qty-input" />
            </div>
          </template>

          <tr v-if="sortedRows.length === 0">
            <td :colspan="tableColumns.length" class="empty-row">
              {{ $t('reports.stock.noMatch') }}
            </td>
          </tr>
          <tr v-for="row in paginatedRows" :key="row.id" :class="{ 'row-selected': isSelected(row.id) }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(row.id)" @change="toggleRow(row.id)" />
            </td>
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
            <td v-if="columnVisibility.isVisible('supplier_name')">
              <button v-if="row.supplier_id" class="name-link" @click="openSupplierDetail(row)"><HighlightText :text="row.supplier_name" :query="searchQuery" /></button>
              <span v-else-if="row.supplier_name"><HighlightText :text="row.supplier_name" :query="searchQuery" /></span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('invoice_code')">
              <button v-if="row.invoice_code" class="code-link" @click="openInvoiceDetail(row)"><HighlightText :text="row.invoice_code" :query="searchQuery" /></button>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('purchase_date')">{{ formatDate(row.purchase_date) }}</td>
            <td v-if="columnVisibility.isVisible('quantity_received')" class="num">{{ formatQuantity(row.quantity_received) }}</td>
            <td v-if="columnVisibility.isVisible('quantity_remaining')" class="num">
              <span :class="{ 'out-of-stock': Number(row.quantity_remaining) === 0 }">{{ formatQuantity(row.quantity_remaining) }}</span>
            </td>
            <td v-if="columnVisibility.isVisible('unit_cost')" class="num">{{ formatMoney(row.unit_cost) }}</td>
            <td v-if="columnVisibility.isVisible('total_cost')" class="num strong">{{ formatMoney(row.total_cost) }}</td>
          </tr>
        </ResizableTable>

        <TotalsBar
          :items="[
            { label: $t('reports.stock.sumProducts'), value: totals.productCount },
            { label: $t('reports.stock.sumPurchased'), value: formatQuantity(totals.totalPurchased) },
            { label: $t('reports.stock.sumInStock'), value: formatQuantity(totals.totalRemaining) },
            { label: $t('reports.stock.sumTotalCost'), value: formatMoney(totals.totalCost), strong: true },
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

      <SupplierDetailModal
        v-if="detailSupplier"
        :supplier="detailSupplier"
        :can-edit="canManage"
        @close="detailSupplier = null"
        @edit="onSupplierEdit"
      />

      <SupplierFormModal
        v-if="editingSupplier"
        :supplier="editingSupplier"
        @close="editingSupplier = null"
        @saved="onSupplierSaved"
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
      :title="$t('reports.stock.exportHistoryTitle')"
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
import SupplierDetailModal from '@/features/suppliers/components/SupplierDetailModal.vue'
import SupplierFormModal from '@/features/suppliers/components/SupplierFormModal.vue'
import InvoiceDetailModal from '@/features/invoices/components/InvoiceDetailModal.vue'
import { useStockReport } from '@/features/reports/composables/useStockReport'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useRowSelection } from '@/composables/useRowSelection'
import { useClientPagination } from '@/composables/useClientPagination'
import { useExport } from '@/composables/useExport'
import { startStockReportExport, startStockReportBusinessExport } from '@/features/reports/services/reportService'
import { fetchProduct } from '@/features/products/services/productService'
import { fetchSupplier } from '@/features/suppliers/services/supplierService'
import { fetchInvoice } from '@/features/invoices/services/invoiceService'
import {
  STOCK_REPORT_COLUMNS, STOCK_REPORT_INITIAL_COL_WIDTHS,
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
      types: isBiz ? ['stock-report-business'] : ['stock-report'],
    },
  }]
})

const columnVisibility = useColumnVisibility({
  storageKey: 'stock-report',
  columns: STOCK_REPORT_COLUMNS,
  lockedKeys: ['select'],
})
const visibleWidths = computed(() => columnVisibility.filterWidths(STOCK_REPORT_INITIAL_COL_WIDTHS))

// In business scope the Store column is inserted right after the leading select
// column (it is not user-togglable — it's what disambiguates rows across stores).
const tableColumns = computed(() => {
  const cols = columnVisibility.visibleColumns.value
  if (scope.value !== 'business') return cols
  const out = [...cols]
  const idx = out.findIndex((c) => c.key === 'select')
  out.splice(idx + 1, 0, REPORT_STORE_COLUMN)
  return out
})
const tableWidths = computed(() => {
  if (scope.value !== 'business') return visibleWidths.value
  const cols = columnVisibility.visibleColumns.value
  const idx = cols.findIndex((c) => c.key === 'select')
  const out = [...visibleWidths.value]
  out.splice(idx + 1, 0, REPORT_STORE_COLUMN_WIDTH)
  return out
})
const tableKey = computed(() => [scope.value, ...columnVisibility.visibleColumnKeys.value].join('|'))

const {
  rows, loading, searchQuery, supplierFilters, storeFilters, tagFilter, minQty, maxQty, includeOutOfStock, startDate, endDate,
  hasActiveFilters, clearFilters,
  sortedRows, supplierOptions, storeOptions, tagOptions, totals, sort, load,
} = useStockReport({
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
const detailSupplier = ref(null)
const editingSupplier = ref(null)

const openProductDetail = async (row) => {
  try {
    detailProduct.value = await fetchProduct({ id: row.product_id })
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const openSupplierDetail = async (row) => {
  try {
    detailSupplier.value = await fetchSupplier({ id: row.supplier_id })
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const openInvoiceDetail = async (row) => {
  try {
    detailInvoice.value = await fetchInvoice({ id: row.invoice_id })
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

const onSupplierEdit = (supplier) => {
  detailSupplier.value = null
  editingSupplier.value = { ...supplier }
}

const onSupplierSaved = async () => {
  editingSupplier.value = null
  showToast(t('suppliers.updateSuccess'), 'success')
  await load()
}

// The purchase invoice has a full edit page, so open it in a new tab.
const onInvoiceEdit = () => {
  const invoice = detailInvoice.value
  detailInvoice.value = null
  window.open(router.resolve(`/purchase-invoices/${invoice.id}/edit`).href, '_blank')
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
      return startStockReportBusinessExport({ businessId: currentBusiness.value.id, params })
    }
    return startStockReportExport({ storeId: currentStore.value.id, params })
  },
  defaultFilename: (id) => `stock-report-${id}.xlsx`,
  onSuccess: () => showToast(t('reports.stock.exportReady'), 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

const exportOrderedIds = () => sortedRows.value
  .map((r) => String(r.id))
  .filter((id) => selectedIds.value.size === 0 || selectedIds.value.has(id))

watch([searchQuery, supplierFilters, storeFilters, tagFilter, minQty, maxQty, includeOutOfStock, startDate, endDate, () => scope.value, () => sort.sortCriteria.value], resetPage, { deep: true })
// Switching store/business (via the switcher) clears the cross-store filter and selection.
watch(scope, () => { storeFilters.value = [] })
watch([() => currentStore.value?.id, () => currentBusiness.value?.id], clearSelection)
</script>

<style scoped>
.toolbar { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; }

.oos-toggle { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; color: #374151; cursor: pointer; white-space: nowrap; padding-bottom: 8px; }
.oos-toggle input { width: 15px; height: 15px; cursor: pointer; accent-color: #111; }

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
.out-of-stock { color: #dc2626; font-weight: 600; }

.qty-range { display: flex; flex-direction: column; gap: 4px; }
.qty-input { width: 100%; min-width: 0; box-sizing: border-box; padding: 5px 8px; min-height: 26px; font-size: 12px; border: 1px solid #d1d5db; border-radius: 6px; }
.qty-input:focus { outline: none; border-color: #111; }
.qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.qty-input { -moz-appearance: textfield; appearance: textfield; }


tbody tr.row-selected { background: #f0f7ff; }
tbody tr.row-selected:hover { background: #e6f0fb; }
</style>
