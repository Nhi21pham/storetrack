<template>
  <PageContainer :maxWidth="1100">
    <PageHeader title="Product Categories" subtitle="Group products and services. System categories cannot be edited or deleted.">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Category
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      title="No business found"
      description="You need to create or select a business before managing categories."
    />

    <EmptyState
      v-else-if="!currentStore"
      title="No store selected"
      description="Select a store to manage categories."
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" placeholder="Search by code or name..." />
        <ClearFiltersButton v-if="hasActiveFilters" @click="clearFilters" />
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
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
        @clear="clearSelection"
        @activate="requestBulk('activate')"
        @deactivate="requestBulk('deactivate')"
        @delete="requestBulk('delete')"
      />

      <LoadingState v-if="loading">Loading categories...</LoadingState>

      <EmptyState
        v-else-if="categories.length === 0"
        title="No categories yet"
        description="Add your first category to get started."
      />

      <div v-else class="table-wrap">
        <ResizableTable :key="tableKey" :columns="columnVisibility.visibleColumns.value" :initial-widths="visibleWidths" sticky-header>
          <template v-for="col in columnVisibility.visibleColumns.value" :key="col.key" #[`header-${col.key}`]="{ col: c }">
            <SelectCheckbox
              v-if="c.key === 'select'"
              :checked="allVisibleSelected"
              :indeterminate="someVisibleSelected"
              title="Select all on this page"
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

          <template #filter-status>
            <SearchableSelect
              :modelValue="statusFilter"
              :options="STATUS_OPTIONS"
              all-label="(All)"
              search-placeholder="Filter status..."
              teleport
              @update:modelValue="statusFilter = $event"
            />
          </template>

          <tr v-if="filteredCategories.length === 0">
            <td :colspan="columnVisibility.visibleColumns.value.length" class="empty-row">
              No categories match the current filters.
            </td>
          </tr>
          <tr v-for="(category, idx) in paginatedCategories" :key="category.id" :class="{ inactive: !category.is_active, system: category.is_system }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox
                v-if="!category.is_system"
                :checked="isSelected(category.id)"
                @change="toggleRow(category.id)"
              />
            </td>
            <td v-if="columnVisibility.isVisible('stt')" class="stt-col">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
            <td v-if="columnVisibility.isVisible('code')" class="code-col">{{ category.code }}</td>
            <td v-if="columnVisibility.isVisible('name')">
              <button class="name-link" @click="detailCategory = category">{{ displayCategoryName(category) }}</button>
              <span v-if="category.is_system" class="system-badge">System</span>
            </td>
            <td v-if="columnVisibility.isVisible('description')">
              <span v-if="category.description" class="truncate" :title="category.description">{{ category.description }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('status')">
              <ToggleSwitch
                :model-value="category.is_active"
                :disabled="category.is_system"
                :title="category.is_system ? 'System categories are always active' : (category.is_active ? 'Click to deactivate' : 'Click to activate')"
                @change="onToggleActive(category)"
              />
            </td>
            <td v-if="columnVisibility.isVisible('created_at')" class="date-col">{{ formatDateTime(category.created_at) }}</td>
            <td v-if="columnVisibility.isVisible('updated_at')" class="date-col">{{ formatDateTime(category.updated_at) }}</td>
            <td class="actions-col">
              <template v-if="!category.is_system">
                <button class="action-btn" @click="openEdit(category)" title="Edit">
                  <Icon name="edit" :size="14" />
                </button>
                <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(category)" title="Delete">
                  <Icon name="delete" :size="14" />
                </button>
              </template>
              <span v-else class="locked-label" title="System categories cannot be edited or deleted">Locked</span>
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

    <ProductCategoryFormModal
      v-if="showForm"
      :category="editingCategory"
      :store-id="currentStore?.id"
      @close="closeForm"
      @saved="onSaved"
      @pick-existing="onPickExisting"
    />

    <ProductCategoryDetailModal
      v-if="detailCategory"
      :category="detailCategory"
      :can-edit="true"
      @close="detailCategory = null"
      @edit="onDetailEdit"
    />

    <ConfirmDialog
      v-if="deleteTarget"
      :title="`Delete ${deleteTarget.code} - ${displayCategoryName(deleteTarget)}?`"
      message="This will permanently remove the category."
      confirm-text="Delete"
      cancel-text="Cancel"
      @confirm="performDelete"
      @cancel="deleteTarget = null"
    />

    <ConfirmDialog
      v-if="pendingAction"
      :title="confirmConfig.title"
      :message="confirmConfig.message"
      :confirm-text="confirmConfig.confirmText"
      cancel-text="Cancel"
      :type="confirmConfig.type"
      @confirm="confirmBulk"
      @cancel="cancelBulk"
    />

    <ConfirmDialog
      v-if="deactivateTarget"
      :title="`Category is in use`"
      :message="`${deactivateTarget.code} - ${displayCategoryName(deactivateTarget)} is referenced by existing products and cannot be deleted. Deactivate it instead?`"
      confirm-text="Deactivate"
      cancel-text="Cancel"
      @confirm="performDeactivate"
      @cancel="deactivateTarget = null"
    />

    <ConfirmDialog
      v-if="togglingCategory"
      :title="togglingCategory.is_active ? 'Deactivate Category' : 'Reactivate Category'"
      :message="togglingCategory.is_active
        ? `Are you sure you want to deactivate '${displayCategoryName(togglingCategory)}'? Existing products keep their category, but it won't appear in the picker for new products.`
        : `Are you sure you want to reactivate '${displayCategoryName(togglingCategory)}'?`"
      :confirm-text="togglingCategory.is_active ? 'Yes, deactivate' : 'Yes, reactivate'"
      cancel-text="Cancel"
      :type="togglingCategory.is_active ? 'warning' : 'success'"
      @confirm="handleToggle"
      @cancel="togglingCategory = null"
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
import ClearFiltersButton from '@/components/common/ClearFiltersButton.vue'
import DateRangeFilters from '@/components/common/DateRangeFilters.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import BulkStatusBar from '@/components/common/BulkStatusBar.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import ProductCategoryFormModal from '@/features/productCategories/components/ProductCategoryFormModal.vue'
import ProductCategoryDetailModal from '@/features/productCategories/components/ProductCategoryDetailModal.vue'
import { useClientPagination } from '@/composables/useClientPagination'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { useRowSelection } from '@/composables/useRowSelection'
import { useBulkActions } from '@/composables/useBulkActions'
import { useDateRangeFilter } from '@/composables/useDateRangeFilter'
import {
  fetchProductCategories,
  deleteProductCategory,
  updateProductCategory,
} from '@/features/productCategories/services/productCategoryService'
import {
  PRODUCT_CATEGORY_COLUMNS,
  PRODUCT_CATEGORY_INITIAL_COL_WIDTHS,
  STATUS_OPTIONS,
  displayCategoryName,
} from '@/features/productCategories/constants'
import { ErrorCode } from '@/utils/errorCodes'
import { normalizeText } from '@/utils/textNormalizer'
import { formatDateTime } from '@/utils/datetime'

const columnVisibility = useColumnVisibility({
  storageKey: 'product_categories',
  columns: PRODUCT_CATEGORY_COLUMNS,
  lockedKeys: ['select', 'stt', 'actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(PRODUCT_CATEGORY_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')

const categories = ref([])
const loading = ref(false)
const searchQuery = ref('')
const statusFilter = ref('')
const { startDate, endDate, dateField, isActive: dateRangeActive, inDateRange, clear: clearDateRange } = useDateRangeFilter()

const hasActiveFilters = computed(() => !!statusFilter.value || dateRangeActive.value)
const clearFilters = () => {
  statusFilter.value = ''
  clearDateRange()
}

const showForm = ref(false)
const editingCategory = ref(null)
const detailCategory = ref(null)
const deleteTarget = ref(null)
const deactivateTarget = ref(null)
const togglingCategory = ref(null)

const onDetailEdit = (category) => {
  detailCategory.value = null
  if (category.is_system) return
  openEdit(category)
}

const canDelete = computed(() => {
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return role === 'owner' || role === 'accountant'
})

const filteredCategories = computed(() => {
  const needle = normalizeText(searchQuery.value)
  return categories.value.filter(c => {
    if (statusFilter.value === 'active'   && !c.is_active) return false
    if (statusFilter.value === 'inactive' &&  c.is_active) return false
    if (!inDateRange(c)) return false
    if (!needle) return true
    return (
      normalizeText(displayCategoryName(c)).includes(needle) ||
      normalizeText(c.code).includes(needle) ||
      normalizeText(c.description || '').includes(needle)
    )
  })
})

// Most recently updated first by default, so the No. column reads newest-to-oldest.
const orderedCategories = computed(() =>
  [...filteredCategories.value].sort(
    (a, b) => new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0),
  )
)

const sort = useSortCriteria()
const sortedCategories = computed(() =>
  sort.sortItems(orderedCategories.value, (category, key) => {
    if (key === 'status') return category.is_active ? 1 : 0
    if (key === 'name')   return normalizeText(displayCategoryName(category))
    if (key === 'created_at' || key === 'updated_at') return new Date(category[key] || 0).getTime()
    const v = category[key]
    return typeof v === 'string' ? normalizeText(v) : (v ?? '')
  })
)

const {
  currentPage,
  perPage,
  total,
  totalPages,
  paginated: paginatedCategories,
  setPerPage,
  resetPage,
} = useClientPagination(sortedCategories)

const visibleIds = computed(() =>
  paginatedCategories.value.filter(c => !c.is_system).map(c => String(c.id))
)
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: visibleIds })

const { bulkBusy, pendingAction, request: requestBulk, confirm: confirmBulk, cancel: cancelBulk, confirmConfig } = useBulkActions({
  selectedIds, clearSelection, reload: () => load(), noun: 'category',
  setActive: (id, isActive) => updateProductCategory({ id, input: { is_active: isActive } }),
  remove: (id) => deleteProductCategory({ id }),
})

watch([searchQuery, statusFilter, startDate, endDate, dateField, () => sort.sortCriteria.value], resetPage, { deep: true })

const load = async () => {
  if (!currentStore?.value?.id) {
    categories.value = []
    return
  }
  loading.value = true
  try {
    categories.value = await fetchProductCategories({ storeId: currentStore.value.id, includeInactive: true })
  } finally {
    loading.value = false
  }
}

onMounted(load)

watch(() => currentStore?.value?.id, () => { clearSelection(); load() })

const openCreate = () => {
  editingCategory.value = null
  showForm.value = true
}

const openEdit = (category) => {
  if (category.is_system) return
  editingCategory.value = category
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingCategory.value = null
}

const onSaved = async () => {
  closeForm()
  await load()
}

const onPickExisting = (category) => {
  editingCategory.value = category
  showForm.value = true
}

const confirmDelete = (category) => {
  if (category.is_system) return
  deleteTarget.value = category
}

const performDelete = async () => {
  const category = deleteTarget.value
  deleteTarget.value = null
  try {
    await deleteProductCategory({ id: category.id })
    await load()
  } catch (err) {
    if (err.code === ErrorCode.PRODUCT_CATEGORY_IN_USE) {
      deactivateTarget.value = category
    } else {
      alert(err.message)
    }
  }
}

const performDeactivate = async () => {
  const category = deactivateTarget.value
  deactivateTarget.value = null
  try {
    await updateProductCategory({ id: category.id, input: { is_active: false } })
    await load()
  } catch (err) {
    alert(err.message)
  }
}

const onToggleActive = (category) => {
  if (category.is_system) return
  togglingCategory.value = category
}

const handleToggle = async () => {
  const category = togglingCategory.value
  togglingCategory.value = null
  if (!category) return
  const nextValue = !category.is_active
  const previous = category.is_active
  category.is_active = nextValue
  try {
    await updateProductCategory({ id: category.id, input: { is_active: nextValue } })
  } catch (err) {
    category.is_active = previous
    alert(err.message)
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
tbody tr.system td { background: #fdf4ff; }
tbody tr.system td.actions-col { background: #fdf4ff; }

.stt-col { color: #6b7280; font-variant-numeric: tabular-nums; }
.date-col { color: #6b7280; white-space: nowrap; }
.code-col { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #4338ca; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.name-link:hover { color: #2563eb; text-decoration: underline; }
.system-badge { display: inline-block; margin-left: 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 6px; border-radius: 4px; background: #fae8ff; color: #a21caf; font-weight: 600; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.empty-val { color: #d1d5db; }

.actions-col { text-align: right; white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; margin-left: 4px; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.locked-label { display: inline-block; padding-right: 6px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; font-weight: 600; }
</style>
