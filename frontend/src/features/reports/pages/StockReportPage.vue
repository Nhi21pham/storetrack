<template>
  <PageContainer :maxWidth="1200">
    <PageHeader title="Stock Report" subtitle="Inventory on hand, one row per purchase lot." />

    <EmptyState
      v-if="!currentStore"
      title="No store selected"
      description="Select a store to view its stock report."
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" placeholder="Search product, supplier, or invoice..." />
        <DateRangeFilter v-model:startDate="startDate" v-model:endDate="endDate" />
        <label class="oos-toggle">
          <input type="checkbox" v-model="includeOutOfStock" />
          Show out-of-stock
        </label>
        <ClearFiltersButton v-if="hasActiveFilters" @click="clearFilters" />
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
        <ExportButton :exporting="exporting" :disabled="sortedRows.length === 0" @click="run" />
      </div>

      <StockReportSelectionBar
        v-if="selectedIds.size > 0"
        :count="selectedIds.size"
        :exporting="exporting"
        @clear="clearSelection"
        @export="run"
      />

      <LoadingState v-if="loading">Loading stock report…</LoadingState>

      <EmptyState
        v-else-if="rows.length === 0"
        title="No stock yet"
        description="Record a purchase invoice to start tracking inventory on hand."
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

          <template #filter-supplier_name>
            <SearchableSelect
              :modelValue="supplierFilter"
              :options="supplierOptions"
              all-label="(All suppliers)"
              search-placeholder="Filter supplier..."
              teleport
              @update:modelValue="supplierFilter = $event"
            />
          </template>
          <template #filter-quantity_remaining>
            <div class="qty-range">
              <input v-model="minQty" type="number" min="0" placeholder="Min" class="qty-input" />
              <input v-model="maxQty" type="number" min="0" placeholder="Max" class="qty-input" />
            </div>
          </template>

          <tr v-if="sortedRows.length === 0">
            <td :colspan="columnVisibility.visibleColumns.value.length" class="empty-row">
              No stock matches the current filters.
            </td>
          </tr>
          <tr v-for="row in paginatedRows" :key="row.id" :class="{ 'row-selected': isSelected(row.id) }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(row.id)" @change="toggleRow(row.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('product_name')">{{ row.product_name }}</td>
            <td v-if="columnVisibility.isVisible('product_code')">
              <button v-if="row.product_code" class="code-link" @click="openProductDetail(row)">{{ row.product_code }}</button>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('supplier_name')">
              <span v-if="row.supplier_name">{{ row.supplier_name }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('invoice_code')">
              <button v-if="row.invoice_code" class="code-link" @click="openInvoiceDetail(row)">{{ row.invoice_code }}</button>
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

        <div class="totals-bar">
          <span class="total-item"><span class="total-label">Products</span> {{ totals.productCount }}</span>
          <span class="total-item"><span class="total-label">Purchased</span> {{ formatQuantity(totals.totalPurchased) }}</span>
          <span class="total-item"><span class="total-label">In stock</span> {{ formatQuantity(totals.totalRemaining) }}</span>
          <span class="total-item strong"><span class="total-label">Total cost</span> {{ formatMoney(totals.totalCost) }}</span>
        </div>

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
        :can-edit="false"
        @close="detailProduct = null"
      />

      <InvoiceDetailModal
        v-if="detailInvoice"
        :invoice="detailInvoice"
        :can-manage="false"
        @close="detailInvoice = null"
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
import SearchBar from '@/components/common/SearchBar.vue'
import ClearFiltersButton from '@/components/common/ClearFiltersButton.vue'
import DateRangeFilter from '@/components/common/DateRangeFilter.vue'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import ExportButton from '@/components/common/ExportButton.vue'
import Pagination from '@/components/common/Pagination.vue'
import StockReportSelectionBar from '@/features/reports/components/StockReportSelectionBar.vue'
import ProductDetailModal from '@/features/products/components/ProductDetailModal.vue'
import InvoiceDetailModal from '@/features/invoices/components/InvoiceDetailModal.vue'
import { useStockReport } from '@/features/reports/composables/useStockReport'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useRowSelection } from '@/composables/useRowSelection'
import { useClientPagination } from '@/composables/useClientPagination'
import { useExport } from '@/composables/useExport'
import { startStockReportExport } from '@/features/reports/services/reportService'
import { fetchProduct } from '@/features/products/services/productService'
import { fetchInvoice } from '@/features/invoices/services/invoiceService'
import {
  STOCK_REPORT_COLUMNS, STOCK_REPORT_INITIAL_COL_WIDTHS,
  formatMoney, formatQuantity, formatDate,
} from '@/features/reports/constants'

const showToast = inject('showToast')
const currentStore = inject('currentStore')

const columnVisibility = useColumnVisibility({
  storageKey: 'stock-report',
  columns: STOCK_REPORT_COLUMNS,
  lockedKeys: ['select'],
})
const visibleWidths = computed(() => columnVisibility.filterWidths(STOCK_REPORT_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const {
  rows, loading, searchQuery, supplierFilter, minQty, maxQty, includeOutOfStock, startDate, endDate,
  hasActiveFilters, clearFilters,
  sortedRows, supplierOptions, totals, sort,
} = useStockReport({
  currentStore,
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

const openProductDetail = async (row) => {
  try {
    detailProduct.value = await fetchProduct({ id: row.product_id })
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

const { exporting, run } = useExport({
  start: () => {
    const params = {
      search: searchQuery.value.trim() || undefined,
      supplier_id: supplierFilter.value || undefined,
      min_quantity: minQty.value !== '' ? minQty.value : undefined,
      max_quantity: maxQty.value !== '' ? maxQty.value : undefined,
      include_out_of_stock: includeOutOfStock.value ? '1' : undefined,
      start_date: startDate.value || undefined,
      end_date: endDate.value || undefined,
    }
    if (selectedIds.value.size > 0) {
      params.ids = Array.from(selectedIds.value)
    }
    params.columns = columnVisibility.togglableColumns
      .filter((col) => columnVisibility.isVisible(col.key))
      .map((col) => col.key)
    return startStockReportExport({ storeId: currentStore.value.id, params })
  },
  defaultFilename: (id) => `stock-report-${id}.xlsx`,
  onSuccess: () => showToast('Stock report export ready.', 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

watch([searchQuery, supplierFilter, minQty, maxQty, includeOutOfStock, startDate, endDate, () => sort.sortCriteria.value], resetPage, { deep: true })
watch(() => currentStore.value?.id, clearSelection)
</script>

<style scoped>
.toolbar { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; }

.oos-toggle { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; color: #374151; cursor: pointer; white-space: nowrap; padding-bottom: 8px; }
.oos-toggle input { width: 15px; height: 15px; cursor: pointer; accent-color: #111; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }
.empty-row { padding: 24px 16px; text-align: center; color: #9ca3af; font-size: 13px; }

.code-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #4338ca; cursor: pointer; text-align: left; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.code-link:hover { text-decoration: underline; }
.empty-val { color: #d1d5db; }
.num { text-align: right; font-variant-numeric: tabular-nums; }
.num.strong { font-weight: 700; color: #111; }
.out-of-stock { color: #dc2626; font-weight: 600; }

.qty-range { display: flex; flex-direction: column; gap: 4px; }
.qty-input { width: 100%; min-width: 0; box-sizing: border-box; padding: 5px 8px; min-height: 26px; font-size: 12px; border: 1px solid #d1d5db; border-radius: 6px; }
.qty-input:focus { outline: none; border-color: #111; }
.qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.qty-input { -moz-appearance: textfield; appearance: textfield; }

.totals-bar { display: flex; flex-wrap: wrap; gap: 24px; padding: 14px 18px; margin-top: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13.5px; color: #374151; }
.total-item { display: inline-flex; align-items: baseline; gap: 8px; font-variant-numeric: tabular-nums; }
.total-item.strong { font-weight: 700; color: #111; margin-left: auto; }
.total-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }

tbody tr.row-selected { background: #f0f7ff; }
tbody tr.row-selected:hover { background: #e6f0fb; }
</style>
