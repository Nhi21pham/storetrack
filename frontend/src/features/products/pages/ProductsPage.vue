<template>
  <PageContainer :maxWidth="1100">
    <PageHeader :title="$t('products.title')" :subtitle="$t('products.subtitle')">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ $t('products.newProduct') }}
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      :title="$t('shared.noBusinessTitle')"
      :description="$t('products.noBusinessDesc')"
    />

    <EmptyState
      v-else-if="!currentStore"
      :title="$t('shared.noStoreTitle')"
      :description="$t('products.noStoreDesc')"
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" :placeholder="$t('shared.searchByName')" />
        <ClearFiltersButton v-if="hasActiveFilters" @click="clearFilters" />
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
        <HistoryButton @click="showHistory = true" />
        <ImportButton @click="showImport = true" />
        <ExportButton :exporting="exporting" :disabled="sortedProducts.length === 0" @click="runExport" />
      </div>

      <DateRangeFilters
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        v-model:date-field="dateField"
      />

      <BulkStatusBar
        v-if="selectedIds.size > 0"
        :count="selectedIds.size"
        :busy="bulkBusy"
        :can-delete="canDelete"
        show-export
        :show-tags="canCreateUpdate"
        :show-remove-tags="canCreateUpdate"
        :exporting="exporting"
        @clear="clearSelection"
        @export="runExport"
        @tags="openBulkTags"
        @remove-tags="openBulkRemoveTags"
        @activate="requestBulk('activate')"
        @deactivate="requestBulk('deactivate')"
        @delete="requestBulk('delete')"
      />

      <LoadingState v-if="loading">{{ $t('products.loadingProducts') }}</LoadingState>

      <EmptyState
        v-else-if="products.length === 0"
        :title="$t('products.noProductsTitle')"
        :description="$t('products.noProductsDesc')"
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

          <template #filter-category>
            <SearchableSelect
              :modelValue="categoryFilter"
              :options="categoryOptions"
              :all-label="$t('products.allCategories')"
              :search-placeholder="$t('products.filterCategory')"
              teleport
              @update:modelValue="categoryFilter = $event"
            />
          </template>
          <template #filter-unit>
            <SearchableSelect
              :modelValue="unitFilter"
              :options="unitOptions"
              :all-label="$t('products.allUnits')"
              :search-placeholder="$t('products.filterUnit')"
              teleport
              @update:modelValue="unitFilter = $event"
            />
          </template>
          <template #filter-tags>
            <SearchableSelect
              :modelValue="tagFilter"
              :options="tagOptions"
              :all-label="$t('shared.allParen')"
              :search-placeholder="$t('products.filterTag')"
              multiple
              teleport
              @update:modelValue="onTagFilterChange"
            />
          </template>
          <template #filter-status>
            <SearchableSelect
              :modelValue="statusFilter"
              :options="statusOptions()"
              :all-label="$t('shared.allParen')"
              :search-placeholder="$t('shared.filterStatus')"
              teleport
              @update:modelValue="statusFilter = $event"
            />
          </template>

          <tr v-if="filteredProducts.length === 0">
            <td :colspan="columnVisibility.visibleColumns.value.length" class="empty-row">
              {{ $t('shared.noResults') }}
            </td>
          </tr>
          <tr v-for="(product, idx) in paginatedProducts" :key="product.id" :class="{ inactive: !product.is_active }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(product.id)" @change="toggleRow(product.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('stt')" class="stt-col">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
            <td v-if="columnVisibility.isVisible('code')" class="code-col">
              <button class="code-link" @click="detailProduct = product">{{ product.code }}</button>
            </td>
            <td v-if="columnVisibility.isVisible('name')">
              <button class="name-link" v-tooltip="product.name" @click="detailProduct = product">{{ product.name }}</button>
            </td>
            <td v-if="columnVisibility.isVisible('category')">
              <span v-if="product.category">{{ displayCategoryName(product.category) }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('unit')">
              <span v-if="product.unit?.name">{{ product.unit.name }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('tags')">
              <div v-if="product.tags && product.tags.length" class="tags-cell">
                <span v-for="(t, i) in product.tags" :key="i" class="chip-wrap">
                  <TagChip :tag-name="t.tag_name" :value="t.value" />
                  <ChipRemoveButton v-if="canCreateUpdate" :title="$t('products.detachTagTitle')" @click="detachTag(product, i)" />
                </span>
              </div>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('status')">
              <ToggleSwitch
                :model-value="product.is_active"
                :title="product.is_active ? $t('shared.clickToDeactivate') : $t('shared.clickToActivate')"
                @change="onToggleActive(product)"
              />
            </td>
            <td v-if="columnVisibility.isVisible('created_at')" class="date-col">{{ formatDateTime(product.created_at) }}</td>
            <td v-if="columnVisibility.isVisible('updated_at')" class="date-col">{{ formatDateTime(product.updated_at) }}</td>
            <td class="actions-col">
              <button class="action-btn" @click="openEdit(product)" :title="$t('common.edit')">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(product)" :title="$t('common.delete')">
                <Icon name="delete" :size="14" />
              </button>
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
    </template>

    <ProductFormModal
      v-if="showForm"
      :product="editingProduct"
      :store-id="currentStore?.id"
      @close="closeForm"
      @saved="onSaved"
      @pick-existing="onPickExisting"
    />

    <ProductDetailModal
      v-if="detailProduct"
      :product="detailProduct"
      :can-edit="true"
      @close="detailProduct = null"
      @edit="onDetailEdit"
    />

    <ImportModal
      v-if="showImport"
      :title="$t('products.importTitle')"
      template-filename="products-import-template.xlsx"
      :required-headers="['Category', 'Name', 'Unit']"
      :optional-headers="['Tags']"
      :instructions="importInstructions"
      :download-template="() => downloadProductsImportTemplate({ storeId: currentStore.id })"
      :preview="(file) => previewProductsImport({ storeId: currentStore.id, file })"
      :revalidate="(rows) => revalidateProductsImport({ storeId: currentStore.id, rows })"
      :start="(rows, originalFilename) => startProductsImport({ storeId: currentStore.id, rows, originalFilename })"
      :status="(id) => fetchImportStatus({ importId: id })"
      @close="showImport = false"
      @imported="onImported"
    >
      <template #review-banner="{ rows, resolveReference }">
        <MissingReferencesImportBanner
          :rows="rows"
          :store-id="currentStore.id"
          :resolve-reference="resolveReference"
        />
      </template>
    </ImportModal>

    <HistoryModal
      v-if="showHistory"
      :title="$t('products.historyTitle')"
      :tabs="historyTabs"
      @close="showHistory = false"
    />

    <ConfirmDialog
      v-if="deleteTarget"
      :title="$t('products.deleteTitle', { name: deleteTarget.name })"
      :message="$t('products.deleteMessage')"
      :confirm-text="$t('common.delete')"
      :cancel-text="$t('common.cancel')"
      @confirm="performDelete"
      @cancel="deleteTarget = null"
    />

    <ConfirmDialog
      v-if="pendingAction"
      :title="confirmConfig.title"
      :message="confirmConfig.message"
      :confirm-text="confirmConfig.confirmText"
      :cancel-text="$t('common.cancel')"
      :type="confirmConfig.type"
      @confirm="confirmBulk"
      @cancel="cancelBulk"
    />

    <ConfirmDialog
      v-if="deactivateTarget"
      :title="$t('products.inUseTitle')"
      :message="$t('products.inUseMessage', { name: deactivateTarget.name })"
      :confirm-text="$t('common.deactivate')"
      :cancel-text="$t('common.cancel')"
      @confirm="performDeactivate"
      @cancel="deactivateTarget = null"
    />

    <ConfirmDialog
      v-if="detachTarget"
      :title="$t('products.detachConfirmTitle')"
      :message="$t('products.detachConfirmMessage', {
        tag: detachTarget.tag.value ? `${detachTarget.tag.tag_name}: ${detachTarget.tag.value}` : detachTarget.tag.tag_name,
        product: detachTarget.product.name,
      })"
      :confirm-text="$t('products.detachAction')"
      :cancel-text="$t('common.cancel')"
      type="warning"
      @confirm="performDetach"
      @cancel="detachTarget = null"
    />

    <ConfirmDialog
      v-if="togglingProduct"
      :title="togglingProduct.is_active ? $t('products.toggleDeactivateTitle') : $t('products.toggleReactivateTitle')"
      :message="togglingProduct.is_active
        ? $t('products.toggleDeactivateMessage', { name: togglingProduct.name })
        : $t('products.toggleReactivateMessage', { name: togglingProduct.name })"
      :confirm-text="togglingProduct.is_active ? $t('products.toggleDeactivateConfirm') : $t('products.toggleReactivateConfirm')"
      :cancel-text="$t('common.cancel')"
      :type="togglingProduct.is_active ? 'warning' : 'success'"
      @confirm="handleToggle"
      @cancel="togglingProduct = null"
    />

    <BulkAddTagsModal
      v-if="tagsModalOpen"
      :store-id="currentStore?.id"
      :count="selectedIds.size"
      :noun="bulkNoun(selectedIds.size)"
      :busy="bulkBusy"
      @apply="applyBulkTags"
      @close="closeBulkTags"
    />

    <BulkRemoveTagsModal
      v-if="removeTagsModalOpen"
      :available-tags="selectedTagUnion"
      :count="selectedIds.size"
      :noun="bulkNoun(selectedIds.size)"
      :busy="bulkBusy"
      @apply="applyBulkRemoveTags"
      @close="closeBulkRemoveTags"
    />

    <ConfirmDialog
      v-if="removeTagsPending"
      :title="removeTagsConfirmConfig.title"
      :message="removeTagsConfirmConfig.message"
      :confirm-text="removeTagsConfirmConfig.confirmText"
      :cancel-text="$t('common.cancel')"
      :type="removeTagsConfirmConfig.type"
      @confirm="confirmBulkRemoveTags"
      @cancel="cancelBulkRemoveTags"
    />
  </PageContainer>
</template>

<script setup>
import { ref, computed, watch, inject, onMounted } from 'vue'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import Icon from '@/components/common/Icon.vue'
import ToggleSwitch from '@/components/common/ToggleSwitch.vue'
import Pagination from '@/components/common/Pagination.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import ExportButton from '@/components/common/ExportButton.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import TagChip from '@/components/common/TagChip.vue'
import ChipRemoveButton from '@/components/common/ChipRemoveButton.vue'
import ClearFiltersButton from '@/components/common/ClearFiltersButton.vue'
import DateRangeFilters from '@/components/common/DateRangeFilters.vue'
import BulkStatusBar from '@/components/common/BulkStatusBar.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import HistoryButton from '@/components/common/HistoryButton.vue'
import ImportButton from '@/components/common/ImportButton.vue'
import ImportModal from '@/components/common/ImportModal.vue'
import HistoryModal from '@/components/common/HistoryModal.vue'
import ImportHistoryPanel from '@/components/common/ImportHistoryPanel.vue'
import ExportHistoryPanel from '@/components/common/ExportHistoryPanel.vue'
import MissingReferencesImportBanner from '@/components/common/MissingReferencesImportBanner.vue'
import ProductFormModal from '@/features/products/components/ProductFormModal.vue'
import ProductDetailModal from '@/features/products/components/ProductDetailModal.vue'
import BulkAddTagsModal from '@/features/tags/components/BulkAddTagsModal.vue'
import BulkRemoveTagsModal from '@/features/tags/components/BulkRemoveTagsModal.vue'
import { useClientPagination } from '@/composables/useClientPagination'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { useRowSelection } from '@/composables/useRowSelection'
import { useBulkActions } from '@/composables/useBulkActions'
import { useExport } from '@/composables/useExport'
import { useDateRangeFilter } from '@/composables/useDateRangeFilter'
import {
  fetchProducts, deleteProduct, updateProduct, startProductExport, bulkAttachProductTags, bulkDetachProductTags,
  downloadProductsImportTemplate, previewProductsImport, revalidateProductsImport, startProductsImport,
} from '@/features/products/services/productService'
import { fetchImportStatus } from '@/features/imports/services/importService'
import { fetchTags } from '@/features/tags/services/tagService'
import { fetchUnits } from '@/features/units/services/unitService'
import { fetchProductCategories } from '@/features/productCategories/services/productCategoryService'
import { displayCategoryName } from '@/features/productCategories/constants'
import { PRODUCT_COLUMNS, PRODUCT_INITIAL_COL_WIDTHS, statusOptions } from '@/features/products/constants'
import { ErrorCode } from '@/utils/errorCodes'
import { normalizeText } from '@/utils/textNormalizer'
import { formatDateTime } from '@/utils/datetime'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

const columnVisibility = useColumnVisibility({
  storageKey: 'products',
  columns: PRODUCT_COLUMNS,
  lockedKeys: ['select', 'stt', 'actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(PRODUCT_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')
const showToast = inject('showToast')

const products = ref([])
const units = ref([])
const categories = ref([])
const tags = ref([])
const loading = ref(false)
const searchQuery = ref('')
const statusFilter = ref('')
const unitFilter = ref('')
const categoryFilter = ref('')
const tagFilter = ref([])

const unitOptions = computed(() =>
  units.value.map(u => ({ value: String(u.id), label: u.name }))
)

const categoryOptions = computed(() =>
  categories.value.map(c => ({
    value: String(c.id),
    label: `${c.code} — ${displayCategoryName(c)}`,
  }))
)

// The "(All)" row of the selector (empty selection) shows every product; these two
// broad options narrow to products that have any tag / that have none.
const BROAD_TAG_FILTERS = ['tagged', 'none']

const tagOptions = computed(() => {
  const opts = [
    { value: 'tagged', label: t('products.allTags') },
    { value: 'none', label: t('products.noTagsOption') },
  ]
  const sortedTags = [...tags.value].sort((a, b) => a.name.localeCompare(b.name))
  for (const tag of sortedTags) {
    opts.push({ value: `tag:${tag.id}`, label: `${tag.name} (any)` })
    const sortedValues = [...(tag.values || [])].sort((a, b) => a.value.localeCompare(b.value))
    for (const v of sortedValues) {
      opts.push({ value: `val:${v.id}`, label: `${tag.name}: ${v.value}` })
    }
  }
  return opts
})

// "All tags" / "No tags" are broad, mutually-exclusive filters: each replaces any
// other selection, and picking a specific tag drops whichever broad one was active
// (combining them under the AND filter would always match nothing).
const onTagFilterChange = (next) => {
  const addedBroad = BROAD_TAG_FILTERS.find(v => next.includes(v) && !tagFilter.value.includes(v))
  if (addedBroad) {
    tagFilter.value = [addedBroad]
  } else if (next.length > 1) {
    tagFilter.value = next.filter(v => !BROAD_TAG_FILTERS.includes(v))
  } else {
    tagFilter.value = next
  }
}

const { startDate, endDate, dateField, isActive: dateRangeActive, inDateRange, clear: clearDateRange } = useDateRangeFilter()

const hasActiveFilters = computed(() =>
  !!(statusFilter.value || unitFilter.value || categoryFilter.value || tagFilter.value.length) || dateRangeActive.value
)

const clearFilters = () => {
  statusFilter.value = ''
  unitFilter.value = ''
  categoryFilter.value = ''
  tagFilter.value = []
  clearDateRange()
}

const showForm = ref(false)
const showImport = ref(false)
const showHistory = ref(false)

const historyTabs = computed(() => [
  { key: 'imports', label: t('shared.imports'), component: ImportHistoryPanel, props: { scope: 'store', scopeId: currentStore.value?.id, type: 'products' } },
  { key: 'exports', label: t('shared.exports'), component: ExportHistoryPanel, props: { scope: 'store', scopeId: currentStore.value?.id, types: ['products'] } },
])
const editingProduct = ref(null)
const detailProduct = ref(null)
const deleteTarget = ref(null)
const deactivateTarget = ref(null)
const detachTarget = ref(null)
const togglingProduct = ref(null)

const importInstructions = computed(() => [
  { text: t('products.import.category'), example: 'VPP  ·  Văn phòng phẩm' },
  t('products.import.name'),
  { text: t('products.import.unit'), example: 'Cái · Hộp · Thùng' },
  { text: t('products.import.tags'), example: 'Color: Blue, Size: M, Brand' },
  t('products.import.unknownTags'),
])

const onDetailEdit = (product) => {
  detailProduct.value = null
  openEdit(product)
}

const canDelete = computed(() => {
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return role === 'owner' || role === 'accountant'
})

const canCreateUpdate = computed(() => {
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return role === 'owner' || role === 'accountant' || role === 'staff'
})

const matchesTagFilter = (product) => {
  if (!tagFilter.value.length) return true
  const productTags = product.tags || []
  return tagFilter.value.every((selected) => {
    if (selected === 'none') return productTags.length === 0
    if (selected === 'tagged') return productTags.length > 0
    const [kind, id] = selected.split(':')
    if (kind === 'tag') return productTags.some(t => String(t.tag_id) === id)
    if (kind === 'val') return productTags.some(t => String(t.tag_value_id) === id)
    return true
  })
}

const filteredProducts = computed(() => {
  const needle = normalizeText(searchQuery.value)
  return products.value.filter(p => {
    if (statusFilter.value === 'active'   && !p.is_active) return false
    if (statusFilter.value === 'inactive' &&  p.is_active) return false
    if (unitFilter.value && String(p.unit_id) !== unitFilter.value) return false
    if (categoryFilter.value && String(p.product_category_id) !== categoryFilter.value) return false
    if (!matchesTagFilter(p)) return false
    if (!inDateRange(p)) return false
    if (!needle) return true
    return (
      normalizeText(p.code || '').includes(needle) ||
      normalizeText(p.name).includes(needle) ||
      normalizeText(p.unit?.name || '').includes(needle) ||
      normalizeText(displayCategoryName(p.category || {})).includes(needle) ||
      (p.tags || []).some(t =>
        normalizeText(t.tag_name || '').includes(needle) ||
        normalizeText(t.value || '').includes(needle)
      )
    )
  })
})

// Most recently updated first by default, so the No. column reads newest-to-oldest.
const orderedProducts = computed(() =>
  [...filteredProducts.value].sort(
    (a, b) => new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0),
  )
)

const sort = useSortCriteria()
const sortedProducts = computed(() =>
  sort.sortItems(orderedProducts.value, (product, key) => {
    if (key === 'status')   return product.is_active ? 1 : 0
    if (key === 'unit')     return normalizeText(product.unit?.name || '')
    if (key === 'category') return normalizeText(displayCategoryName(product.category || {}))
    if (key === 'created_at' || key === 'updated_at') return new Date(product[key] || 0).getTime()
    const v = product[key]
    return typeof v === 'string' ? normalizeText(v) : (v ?? '')
  })
)

const {
  currentPage,
  perPage,
  total,
  totalPages,
  paginated: paginatedProducts,
  setPerPage,
  resetPage,
} = useClientPagination(sortedProducts)

const selectableIds = computed(() => sortedProducts.value.map(p => String(p.id)))
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: selectableIds, scopeToEligible: true })

const { exporting, run: runExport } = useExport({
  start: () => {
    const params = {
      search: searchQuery.value.trim() || undefined,
      status: statusFilter.value || undefined,
      unit_id: unitFilter.value || undefined,
      category_id: categoryFilter.value || undefined,
    }
    if (tagFilter.value.includes('none')) {
      params.untagged = true
    } else if (tagFilter.value.includes('tagged')) {
      params.tagged = true
    } else if (tagFilter.value.length) {
      const tagIds = []
      const tagValueIds = []
      for (const selected of tagFilter.value) {
        const [kind, id] = selected.split(':')
        if (kind === 'tag') tagIds.push(id)
        else if (kind === 'val') tagValueIds.push(id)
      }
      if (tagIds.length) params.tag_ids = tagIds
      if (tagValueIds.length) params.tag_value_ids = tagValueIds
    }
    if (selectedIds.value.size > 0) {
      params.ids = Array.from(selectedIds.value)
    }
    params.columns = columnVisibility.togglableColumns
      .filter((col) => columnVisibility.isVisible(col.key))
      .map((col) => col.key)
    return startProductExport({ storeId: currentStore.value.id, params })
  },
  defaultFilename: (id) => `products-${id}.xlsx`,
  onSuccess: () => showToast(t('products.exportReady'), 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

const {
  bulkBusy, pendingAction, request: requestBulk, confirm: confirmBulk, cancel: cancelBulk, confirmConfig, noun: bulkNoun,
  tagsModalOpen, openTags: openBulkTags, closeTags: closeBulkTags, applyTags: applyBulkTags,
  removeTagsModalOpen, openRemoveTags: openBulkRemoveTags, closeRemoveTags: closeBulkRemoveTags, applyRemoveTags: applyBulkRemoveTags,
  removeTagsPending, cancelRemoveTags: cancelBulkRemoveTags, confirmRemoveTags: confirmBulkRemoveTags, removeTagsConfirmConfig,
} = useBulkActions({
  selectedIds, clearSelection, reload: () => load(), nounKey: 'product',
  setActive: (id, isActive) => updateProduct({ id, input: { is_active: isActive } }),
  remove: (id) => deleteProduct({ id }),
  attachTags: (ids, pairs) => bulkAttachProductTags({ storeId: currentStore.value.id, productIds: ids, tags: pairs }),
  onTagsAttached: () => showToast(t('products.bulkTagsSuccess'), 'success'),
  detachTags: (ids, pairs) => bulkDetachProductTags({ storeId: currentStore.value.id, productIds: ids, tags: pairs }),
  onTagsDetached: () => showToast(t('products.bulkTagsRemoved'), 'success'),
})

// Union of tags across the currently-selected products, with how many of the selection carry each.
const selectedTagUnion = computed(() => {
  const map = new Map()
  for (const product of sortedProducts.value) {
    if (!selectedIds.value.has(String(product.id))) continue
    for (const tag of (product.tags || [])) {
      const key = `${tag.tag_id}:${tag.tag_value_id != null ? tag.tag_value_id : ''}`
      const existing = map.get(key)
      if (existing) {
        existing.count += 1
      } else {
        map.set(key, {
          tag_id: tag.tag_id,
          tag_value_id: tag.tag_value_id != null ? tag.tag_value_id : null,
          tag_name: tag.tag_name,
          value: tag.value,
          count: 1,
        })
      }
    }
  }
  return Array.from(map.values()).sort((a, b) =>
    (a.tag_name || '').localeCompare(b.tag_name || '') || (a.value || '').localeCompare(b.value || ''),
  )
})

watch([searchQuery, statusFilter, unitFilter, categoryFilter, tagFilter, startDate, endDate, dateField, () => sort.sortCriteria.value], resetPage, { deep: true })

const load = async () => {
  if (!currentStore?.value?.id) {
    products.value = []
    units.value = []
    categories.value = []
    tags.value = []
    return
  }
  loading.value = true
  try {
    const [productList, unitList, categoryList, tagList] = await Promise.all([
      fetchProducts({ storeId: currentStore.value.id, includeInactive: true }),
      fetchUnits({ storeId: currentStore.value.id, includeInactive: true }),
      fetchProductCategories({ storeId: currentStore.value.id, includeInactive: true }),
      fetchTags({ storeId: currentStore.value.id }),
    ])
    products.value = productList
    units.value = unitList
    categories.value = categoryList
    tags.value = tagList
  } finally {
    loading.value = false
  }
}

onMounted(load)

watch(() => currentStore?.value?.id, () => { clearSelection(); load() })

const openCreate = () => {
  editingProduct.value = null
  showForm.value = true
}

const openEdit = (product) => {
  editingProduct.value = product
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingProduct.value = null
}

const onSaved = async () => {
  closeForm()
  await load()
}

const onImported = async () => {
  await load()
  showToast(t('products.imported'), 'success')
}

const onPickExisting = (product) => {
  editingProduct.value = product
  showForm.value = true
}

const confirmDelete = (product) => {
  deleteTarget.value = product
}

const performDelete = async () => {
  const product = deleteTarget.value
  deleteTarget.value = null
  try {
    await deleteProduct({ id: product.id })
    await load()
  } catch (err) {
    if (err.code === ErrorCode.PRODUCT_IN_USE) {
      deactivateTarget.value = product
    } else {
      alert(translateError(err))
    }
  }
}

const performDeactivate = async () => {
  const product = deactivateTarget.value
  deactivateTarget.value = null
  try {
    await updateProduct({ id: product.id, input: { is_active: false } })
    await load()
  } catch (err) {
    alert(translateError(err))
  }
}

const detachTag = (product, idx) => {
  detachTarget.value = { product, idx, tag: product.tags[idx] }
}

const performDetach = async () => {
  const { product, idx } = detachTarget.value
  detachTarget.value = null
  const tags = (product.tags || [])
    .filter((_, i) => i !== idx)
    .map(t => ({
      tag_id: String(t.tag_id),
      tag_value_id: t.tag_value_id != null ? String(t.tag_value_id) : null,
    }))
  try {
    await updateProduct({ id: product.id, input: { tags } })
    await load()
  } catch (err) {
    alert(translateError(err))
  }
}

const onToggleActive = (product) => {
  togglingProduct.value = product
}

const handleToggle = async () => {
  const product = togglingProduct.value
  togglingProduct.value = null
  if (!product) return
  const nextValue = !product.is_active
  const previous = product.is_active
  product.is_active = nextValue
  try {
    await updateProduct({ id: product.id, input: { is_active: nextValue } })
  } catch (err) {
    product.is_active = previous
    alert(translateError(err))
  }
}
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; }


.empty-row { padding: 24px 16px; text-align: center; color: #9ca3af; font-size: 13px; }

.btn-create { display: flex; align-items: center; gap: 6px; padding: 9px 16px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-create:hover { background: #333; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }
tbody tr.inactive { background: #fafafa; }
tbody tr.inactive td { color: #6b7280; }
tbody tr.inactive td.actions-col { background: #fafafa; }

.stt-col { color: #6b7280; font-variant-numeric: tabular-nums; }
.date-col { color: #6b7280; white-space: nowrap; }
.code-col { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #4338ca; }
.code-link { background: none; border: none; padding: 0; font: inherit; cursor: pointer; text-align: left; color: inherit; }
.code-link:hover { text-decoration: underline; }
.tags-cell { display: flex; flex-wrap: wrap; gap: 4px; }
.tags-cell .chip-wrap { display: inline-flex; align-items: center; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.name-link:hover { color: #2563eb; text-decoration: underline; }
.empty-val { color: #d1d5db; }

.actions-col { text-align: right; white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; margin-left: 4px; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
