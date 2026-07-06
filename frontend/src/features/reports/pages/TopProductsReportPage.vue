<template>
  <PageContainer :maxWidth="1200">
    <PageHeader :title="$t('reports.top.title')" :subtitle="$t('reports.top.subtitle')" />

    <EmptyState
      v-if="scope === 'store' && !currentStore"
      :title="$t('audit.noStoreTitle')"
      :description="$t('reports.top.noStoreDesc')"
    />

    <EmptyState
      v-else-if="scope === 'business' && !currentBusiness"
      :title="$t('audit.noBusinessTitle')"
      :description="$t('reports.top.noBusinessDesc')"
    />

    <template v-else>
      <div class="rank-toggle">
        <span class="rank-label">{{ $t('reports.rankBy') }}</span>
        <SegmentedToggle v-model="rankBy" :options="rankMetrics()" />
      </div>

      <div class="toolbar">
        <SearchBar v-model="searchQuery" :placeholder="$t('reports.top.search')" />
        <DateRangeFilter v-model:startDate="startDate" v-model:endDate="endDate" />
        <div class="filter-field">
          <SearchableSelect
            :modelValue="tagFilter"
            :options="tagOptions"
            :all-label="$t('shared.allParen')"
            :search-placeholder="$t('reports.filters.filterTag')"
            multiple
            @update:modelValue="tagFilter = $event"
          />
        </div>
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

      <LoadingState v-if="loading">{{ $t('reports.top.loading') }}</LoadingState>

      <EmptyState
        v-else-if="rows.length === 0"
        :title="$t('reports.noSalesTitle')"
        :description="$t('reports.top.emptyDesc')"
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

          <tr v-if="sortedRows.length === 0">
            <td :colspan="tableColumns.length" class="empty-row">{{ $t('reports.top.noMatch') }}</td>
          </tr>
          <tr v-for="(row, idx) in paginatedRows" :key="row.product_id" :class="{ 'row-selected': isSelected(row.product_id) }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(row.product_id)" @change="toggleRow(row.product_id)" />
            </td>
            <td v-if="columnVisibility.isVisible('order_number')" class="num rank">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
            <td v-if="columnVisibility.isVisible('product_name')">
              <button v-if="row.product_id" class="name-link" @click="openProductDetail(row)">{{ row.product_name }}</button>
              <span v-else>{{ row.product_name }}</span>
            </td>
            <td v-if="columnVisibility.isVisible('product_code')">
              <button v-if="row.product_code" class="code-link" @click="openProductDetail(row)">{{ row.product_code }}</button>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('tags')">
              <span v-if="row.tags && row.tags.length" class="tags-list">
                <TagChip v-for="(t, i) in row.tags" :key="i" :tag-name="t.tag_name" :value="t.value" />
              </span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('qty_sold')" class="num" :class="metricClass('qty_sold')">{{ formatQuantity(row.qty_sold) }}</td>
            <td v-if="columnVisibility.isVisible('revenue')" class="num" :class="metricClass('revenue')">{{ formatMoney(row.revenue) }}</td>
            <td v-if="columnVisibility.isVisible('profit')" class="num" :class="metricClass('profit')">{{ formatMoney(row.profit) }}</td>
            <td v-if="columnVisibility.isVisible('orders')" class="num" :class="metricClass('orders')">{{ row.orders }}</td>
          </tr>
        </ResizableTable>

        <TotalsBar
          :items="[
            { label: $t('reports.top.sumProducts'), value: totals.productCount },
            { label: $t('reports.top.sumQtySold'), value: formatQuantity(totals.qty), strong: rankBy === 'qty_sold' },
            { label: $t('reports.top.sumRevenue'), value: formatMoney(totals.revenue), strong: rankBy === 'revenue' },
            { label: $t('reports.top.sumProfit'), value: formatMoney(totals.profit), strong: rankBy === 'profit' },
            { label: $t('reports.col.numOrders'), value: totals.orders, strong: rankBy === 'orders' },
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
    </template>

    <HistoryModal
      v-if="showHistory"
      :title="$t('reports.top.exportHistoryTitle')"
      :tabs="historyTabs"
      @close="showHistory = false"
    />
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
import SegmentedToggle from '@/components/common/SegmentedToggle.vue'
import ExportButton from '@/components/common/ExportButton.vue'
import HistoryButton from '@/components/common/HistoryButton.vue'
import HistoryModal from '@/components/common/HistoryModal.vue'
import ExportHistoryPanel from '@/components/common/ExportHistoryPanel.vue'
import Pagination from '@/components/common/Pagination.vue'
import TagChip from '@/components/common/TagChip.vue'
import TotalsBar from '@/components/common/TotalsBar.vue'
import ReportSelectionBar from '@/features/reports/components/ReportSelectionBar.vue'
import ProductDetailModal from '@/features/products/components/ProductDetailModal.vue'
import ProductFormModal from '@/features/products/components/ProductFormModal.vue'
import { useTopProductsReport } from '@/features/reports/composables/useTopProductsReport'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useRowSelection } from '@/composables/useRowSelection'
import { useClientPagination } from '@/composables/useClientPagination'
import { useExport } from '@/composables/useExport'
import { startTopProductsReportExport, startTopProductsReportBusinessExport } from '@/features/reports/services/reportService'
import { fetchProduct } from '@/features/products/services/productService'
import {
  TOP_PRODUCTS_COLUMNS, TOP_PRODUCTS_INITIAL_COL_WIDTHS, topProductsRankMetrics as rankMetrics,
  formatMoney, formatQuantity,
} from '@/features/reports/constants'
import { t } from '@/i18n'

const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')
const canManage = computed(() => !!currentStore.value?.is_active)

const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')
const scope = computed(() => (!currentStore.value && isBusinessOwner.value) ? 'business' : 'store')

const showHistory = ref(false)
const historyTabs = computed(() => {
  const isBiz = scope.value === 'business'
  return [{
    key: 'exports', label: t('shared.exports'), component: ExportHistoryPanel,
    props: {
      scope: isBiz ? 'business' : 'store',
      scopeId: isBiz ? currentBusiness.value?.id : currentStore.value?.id,
      types: isBiz ? ['top-products-report-business'] : ['top-products-report'],
    },
  }]
})

const columnVisibility = useColumnVisibility({
  storageKey: 'top-products-report',
  columns: TOP_PRODUCTS_COLUMNS,
  lockedKeys: ['select', 'order_number'],
})
const tableColumns = computed(() => columnVisibility.visibleColumns.value)
const tableWidths = computed(() => columnVisibility.filterWidths(TOP_PRODUCTS_INITIAL_COL_WIDTHS))
const tableKey = computed(() => [scope.value, ...columnVisibility.visibleColumnKeys.value].join('|'))

const {
  rows, loading, searchQuery, tagFilter, startDate, endDate, rankBy,
  hasActiveFilters, clearFilters,
  sortedRows, tagOptions, totals, sort,
} = useTopProductsReport({
  currentStore,
  currentBusiness,
  scope,
  onError: (msg) => showToast(msg, 'error'),
})

// Highlight the column that matches the active "Rank by" metric.
const metricClass = (key) => (rankBy.value === key ? 'metric-active' : '')

const {
  currentPage, perPage, total, totalPages,
  paginated: paginatedRows, setPerPage, resetPage,
} = useClientPagination(sortedRows)

const selectableIds = computed(() => sortedRows.value.map((r) => String(r.product_id)))
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: selectableIds, scopeToEligible: true })

const detailProduct = ref(null)
const editingProduct = ref(null)

const openProductDetail = async (row) => {
  try {
    detailProduct.value = await fetchProduct({ id: row.product_id })
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
}

const { exporting, run } = useExport({
  start: () => {
    // The export mirrors exactly what's on screen: the ranked/filtered products
    // in their on-screen order (scoped to the selection when any), as an id list.
    const params = {
      ids: sortedRows.value
        .map((r) => String(r.product_id))
        .filter((id) => selectedIds.value.size === 0 || selectedIds.value.has(id)),
      columns: columnVisibility.togglableColumns
        .filter((col) => columnVisibility.isVisible(col.key))
        .map((col) => col.key),
    }
    if (scope.value === 'business') {
      return startTopProductsReportBusinessExport({ businessId: currentBusiness.value.id, params })
    }
    return startTopProductsReportExport({ storeId: currentStore.value.id, params })
  },
  defaultFilename: (id) => `top-products-report-${id}.xlsx`,
  onSuccess: () => showToast(t('reports.top.exportReady'), 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

watch([searchQuery, tagFilter, startDate, endDate, () => scope.value, rankBy, () => sort.sortCriteria.value], resetPage, { deep: true })
watch([() => currentStore.value?.id, () => currentBusiness.value?.id], clearSelection)
</script>

<style scoped>
.toolbar { display: flex; align-items: flex-end; gap: 14px; margin-bottom: 16px; flex-wrap: wrap; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; min-width: 180px; }
.filter-field { min-width: 180px; }

.rank-toggle { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.rank-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }
.empty-row { padding: 24px 16px; text-align: center; color: #9ca3af; font-size: 13px; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; }
.name-link:hover { color: #4338ca; text-decoration: underline; }
.code-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #4338ca; cursor: pointer; text-align: left; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.code-link:hover { text-decoration: underline; }
.tags-list { display: flex; flex-wrap: wrap; gap: 4px; }
.empty-val { color: #d1d5db; }
.num { text-align: right; font-variant-numeric: tabular-nums; }
.num.rank { font-weight: 700; color: #4338ca; }
/* The active "Rank by" metric column reads as a highlighted column. */
.num.metric-active { font-weight: 700; color: #111; background: #eef2ff; }

tbody tr.row-selected { background: #f0f7ff; }
tbody tr.row-selected:hover { background: #e6f0fb; }
</style>
