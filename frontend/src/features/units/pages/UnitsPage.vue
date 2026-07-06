<template>
  <PageContainer :maxWidth="1100">
    <PageHeader :title="$t('units.title')" :subtitle="$t('units.subtitle')">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ $t('units.newUnit') }}
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      :title="$t('shared.noBusinessTitle')"
      :description="$t('units.noBusinessDesc')"
    />

    <EmptyState
      v-else-if="!currentStore"
      :title="$t('shared.noStoreTitle')"
      :description="$t('units.noStoreDesc')"
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
        <ExportButton :exporting="exporting" :disabled="sortedUnits.length === 0" @click="runExport" />
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
        :exporting="exporting"
        @clear="clearSelection"
        @export="runExport"
        @activate="requestBulk('activate')"
        @deactivate="requestBulk('deactivate')"
        @delete="requestBulk('delete')"
      />

      <LoadingState v-if="loading">{{ $t('units.loadingUnits') }}</LoadingState>

      <EmptyState
        v-else-if="units.length === 0"
        :title="$t('units.noUnitsTitle')"
        :description="$t('units.noUnitsDesc')"
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

          <tr v-if="filteredUnits.length === 0" class="empty-tr">
            <td :colspan="columnVisibility.visibleColumns.value.length" class="empty-row">
              {{ $t('shared.noResults') }}
            </td>
          </tr>
          <tr v-for="(unit, idx) in paginatedUnits" :key="unit.id" :class="{ inactive: !unit.is_active, 'row-edited': isRecent(unit.id) }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(unit.id)" @change="toggleRow(unit.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('stt')" class="stt-col">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
            <td v-if="columnVisibility.isVisible('name')">
              <button class="name-link" v-tooltip="unit.name" @click="detailUnit = unit"><HighlightText :text="unit.name" :query="searchQuery" /></button>
            </td>
            <td v-if="columnVisibility.isVisible('status')">
              <ToggleSwitch
                :model-value="unit.is_active"
                :title="unit.is_active ? $t('shared.clickToDeactivate') : $t('shared.clickToActivate')"
                @change="onToggleActive(unit)"
              />
            </td>
            <td v-if="columnVisibility.isVisible('created_at')" class="date-col">{{ formatDateTime(unit.created_at) }}</td>
            <td v-if="columnVisibility.isVisible('updated_at')" class="date-col">{{ formatDateTime(unit.updated_at) }}</td>
            <td class="actions-col">
              <button class="action-btn" @click="openEdit(unit)" :title="$t('common.edit')">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(unit)" :title="$t('common.delete')">
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

    <UnitFormModal
      v-if="showForm"
      :unit="editingUnit"
      :store-id="currentStore?.id"
      @close="closeForm"
      @saved="onSaved"
      @pick-existing="onPickExisting"
    />

    <ImportModal
      v-if="showImport"
      :title="$t('units.importTitle')"
      template-filename="units-import-template.xlsx"
      :required-headers="['Name']"
      :download-template="() => downloadUnitsImportTemplate({ storeId: currentStore.id })"
      :preview="(file) => previewUnitsImport({ storeId: currentStore.id, file })"
      :revalidate="(rows) => revalidateUnitsImport({ storeId: currentStore.id, rows })"
      :start="(rows, originalFilename) => startUnitsImport({ storeId: currentStore.id, rows, originalFilename })"
      :status="(id) => fetchImportStatus({ importId: id })"
      @close="showImport = false"
      @imported="onImported"
    />

    <HistoryModal
      v-if="showHistory"
      :title="$t('units.historyTitle')"
      :tabs="historyTabs"
      @close="showHistory = false"
    />

    <UnitDetailModal
      v-if="detailUnit"
      :unit="detailUnit"
      :can-edit="true"
      @close="detailUnit = null"
      @edit="onDetailEdit"
    />

    <ConfirmDialog
      v-if="deleteTarget"
      :title="$t('units.deleteTitle', { name: deleteTarget.name })"
      :message="$t('units.deleteMessage')"
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
      :title="$t('units.inUseTitle')"
      :message="$t('units.inUseMessage', { name: deactivateTarget.name })"
      :confirm-text="$t('common.deactivate')"
      :cancel-text="$t('common.cancel')"
      @confirm="performDeactivate"
      @cancel="deactivateTarget = null"
    />

    <ConfirmDialog
      v-if="togglingUnit"
      :title="togglingUnit.is_active ? $t('units.toggleDeactivateTitle') : $t('units.toggleReactivateTitle')"
      :message="togglingUnit.is_active
        ? $t('units.toggleDeactivateMessage', { name: togglingUnit.name })
        : $t('units.toggleReactivateMessage', { name: togglingUnit.name })"
      :confirm-text="togglingUnit.is_active ? $t('units.toggleDeactivateConfirm') : $t('units.toggleReactivateConfirm')"
      :cancel-text="$t('common.cancel')"
      :type="togglingUnit.is_active ? 'warning' : 'success'"
      @confirm="handleToggle"
      @cancel="togglingUnit = null"
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
import HighlightText from '@/components/common/HighlightText.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import Icon from '@/components/common/Icon.vue'
import ToggleSwitch from '@/components/common/ToggleSwitch.vue'
import Pagination from '@/components/common/Pagination.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import ExportButton from '@/components/common/ExportButton.vue'
import ImportButton from '@/components/common/ImportButton.vue'
import ImportModal from '@/components/common/ImportModal.vue'
import HistoryModal from '@/components/common/HistoryModal.vue'
import ImportHistoryPanel from '@/components/common/ImportHistoryPanel.vue'
import ExportHistoryPanel from '@/components/common/ExportHistoryPanel.vue'
import HistoryButton from '@/components/common/HistoryButton.vue'
import ClearFiltersButton from '@/components/common/ClearFiltersButton.vue'
import DateRangeFilters from '@/components/common/DateRangeFilters.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import BulkStatusBar from '@/components/common/BulkStatusBar.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import UnitFormModal from '@/features/units/components/UnitFormModal.vue'
import UnitDetailModal from '@/features/units/components/UnitDetailModal.vue'
import { useClientPagination } from '@/composables/useClientPagination'
import { useSessionOrder } from '@/composables/useSessionOrder'
import { useRowHighlight } from '@/composables/useRowHighlight'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { useRowSelection } from '@/composables/useRowSelection'
import { useBulkActions } from '@/composables/useBulkActions'
import { useExport } from '@/composables/useExport'
import { useDateRangeFilter } from '@/composables/useDateRangeFilter'
import {
  fetchUnits, deleteUnit, updateUnit, startUnitExport,
  downloadUnitsImportTemplate, previewUnitsImport, revalidateUnitsImport, startUnitsImport,
} from '@/features/units/services/unitService'
import { fetchImportStatus } from '@/features/imports/services/importService'
import { UNIT_COLUMNS, UNIT_INITIAL_COL_WIDTHS, statusOptions } from '@/features/units/constants'
import { ErrorCode } from '@/utils/errorCodes'
import { normalizeText } from '@/utils/textNormalizer'
import { formatDateTime } from '@/utils/datetime'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

const columnVisibility = useColumnVisibility({
  storageKey: 'units',
  columns: UNIT_COLUMNS,
  lockedKeys: ['select', 'stt', 'actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(UNIT_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')
const showToast = inject('showToast')

const units = ref([])
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
const showImport = ref(false)
const showHistory = ref(false)

const historyTabs = computed(() => [
  { key: 'imports', label: t('shared.imports'), component: ImportHistoryPanel, props: { scope: 'store', scopeId: currentStore.value?.id, type: 'units' } },
  { key: 'exports', label: t('shared.exports'), component: ExportHistoryPanel, props: { scope: 'store', scopeId: currentStore.value?.id, types: ['units'] } },
])
const editingUnit = ref(null)
const detailUnit = ref(null)
const deleteTarget = ref(null)
const deactivateTarget = ref(null)
const togglingUnit = ref(null)

const onDetailEdit = (unit) => {
  detailUnit.value = null
  openEdit(unit)
}

const canDelete = computed(() => {
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return role === 'owner' || role === 'accountant'
})

const filteredUnits = computed(() => {
  const needle = normalizeText(searchQuery.value)
  return units.value.filter(u => {
    if (statusFilter.value === 'active'   && !u.is_active) return false
    if (statusFilter.value === 'inactive' &&  u.is_active) return false
    if (!inDateRange(u)) return false
    if (needle && !normalizeText(u.name).includes(needle)) return false
    return true
  })
})

// Newest-first on load, then held stable within the session so saving an edit
// doesn't bump the row to the top and cost you your place while editing down the list.
const newestFirstUnits = computed(() =>
  [...filteredUnits.value].sort(
    (a, b) => new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0),
  )
)
const { ordered: orderedUnits } = useSessionOrder(newestFirstUnits)

const sort = useSortCriteria()
const sortedUnits = computed(() =>
  sort.sortItems(orderedUnits.value, (unit, key) => {
    if (key === 'status') return unit.is_active ? 1 : 0
    if (key === 'created_at' || key === 'updated_at') return new Date(unit[key] || 0).getTime()
    const v = unit[key]
    return typeof v === 'string' ? normalizeText(v) : (v ?? '')
  })
)

const {
  currentPage,
  perPage,
  total,
  totalPages,
  paginated: paginatedUnits,
  setPerPage,
  resetPage,
} = useClientPagination(sortedUnits)

const { mark, isRecent } = useRowHighlight()

const selectableIds = computed(() => sortedUnits.value.map(u => String(u.id)))
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: selectableIds, scopeToEligible: true })

const { exporting, run: runExport } = useExport({
  start: () => {
    const params = {
      search: searchQuery.value.trim() || undefined,
      status: statusFilter.value || undefined,
    }
    if (selectedIds.value.size > 0) {
      params.ids = Array.from(selectedIds.value)
    }
    params.columns = columnVisibility.togglableColumns
      .filter((col) => columnVisibility.isVisible(col.key))
      .map((col) => col.key)
    return startUnitExport({ storeId: currentStore.value.id, params })
  },
  defaultFilename: (id) => `units-${id}.xlsx`,
  onSuccess: () => showToast(t('units.exportReady'), 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

const { bulkBusy, pendingAction, request: requestBulk, confirm: confirmBulk, cancel: cancelBulk, confirmConfig } = useBulkActions({
  selectedIds, clearSelection, reload: () => load(), nounKey: 'unit',
  setActive: (id, isActive) => updateUnit({ id, input: { is_active: isActive } }),
  remove: (id) => deleteUnit({ id }),
})

watch([searchQuery, statusFilter, startDate, endDate, dateField, () => sort.sortCriteria.value], resetPage, { deep: true })

const load = async () => {
  if (!currentStore?.value?.id) {
    units.value = []
    return
  }
  if (!units.value.length) loading.value = true
  try {
    units.value = await fetchUnits({ storeId: currentStore.value.id, includeInactive: true })
  } finally {
    loading.value = false
  }
}

onMounted(load)

watch(() => currentStore?.value?.id, () => { clearSelection(); load() })

const openCreate = () => {
  editingUnit.value = null
  showForm.value = true
}

const openEdit = (unit) => {
  editingUnit.value = unit
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingUnit.value = null
}

const onSaved = async () => {
  const editedId = editingUnit.value?.id
  closeForm()
  await load()
  if (editedId) mark(editedId)
}

const onImported = async () => {
  await load()
  showToast(t('units.imported'), 'success')
}

const onPickExisting = (unit) => {
  editingUnit.value = unit
  showForm.value = true
}

const confirmDelete = (unit) => {
  deleteTarget.value = unit
}

const performDelete = async () => {
  const unit = deleteTarget.value
  deleteTarget.value = null
  try {
    await deleteUnit({ id: unit.id })
    await load()
  } catch (err) {
    if (err.code === ErrorCode.UNIT_IN_USE) {
      deactivateTarget.value = unit
    } else {
      alert(translateError(err))
    }
  }
}

const performDeactivate = async () => {
  const unit = deactivateTarget.value
  deactivateTarget.value = null
  try {
    await updateUnit({ id: unit.id, input: { is_active: false } })
    await load()
    mark(unit.id)
  } catch (err) {
    alert(err.message)
  }
}

const onToggleActive = (unit) => {
  togglingUnit.value = unit
}

const handleToggle = async () => {
  const unit = togglingUnit.value
  togglingUnit.value = null
  if (!unit) return
  const nextValue = !unit.is_active
  const previous = unit.is_active
  unit.is_active = nextValue
  try {
    await updateUnit({ id: unit.id, input: { is_active: nextValue } })
    mark(unit.id)
  } catch (err) {
    unit.is_active = previous
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

.stt-col { color: #6b7280; font-variant-numeric: tabular-nums; }
.date-col { color: #6b7280; white-space: nowrap; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.name-link:hover { color: #2563eb; text-decoration: underline; }

.actions-col { text-align: right; white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; margin-left: 4px; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
