<template>
  <PageContainer :maxWidth="1100">
    <PageHeader title="Units" subtitle="Manage the master list of measurement units used on products.">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Unit
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      title="No business found"
      description="You need to create or select a business before managing units."
    />

    <EmptyState
      v-else-if="!currentStore"
      title="No store selected"
      description="Select a store to manage units."
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" placeholder="Search by name..." />
        <label class="toggle">
          <input v-model="includeInactive" type="checkbox" />
          Show inactive
        </label>
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
      </div>

      <LoadingState v-if="loading">Loading units...</LoadingState>

      <EmptyState
        v-else-if="filteredUnits.length === 0 && units.length === 0"
        title="No units yet"
        description="Add your first unit to get started."
      />

      <EmptyState
        v-else-if="filteredUnits.length === 0"
        :description="`No units matching &quot;${searchQuery}&quot;`"
      />

      <div v-else class="table-wrap">
        <ResizableTable :key="tableKey" :columns="columnVisibility.visibleColumns.value" :initial-widths="visibleWidths">
          <template v-for="col in columnVisibility.visibleColumns.value" :key="col.key" #[`header-${col.key}`]="{ col: c }">
            <SortableHeader
              v-if="c.sortable"
              :label="c.label"
              :sort-info="sort.getSortInfo(c.key)"
              :rank="sort.sortCriteria.length > 1 && sort.getSortInfo(c.key) ? sort.sortRank(c.key) : null"
              @sort="(dir) => sort.toggleSort(c.key, dir)"
            />
            <template v-else>{{ c.label }}</template>
          </template>

          <tr v-for="(unit, index) in paginatedUnits" :key="unit.id" :class="{ inactive: !unit.is_active }">
            <td v-if="columnVisibility.isVisible('no')" class="no-col">{{ (currentPage - 1) * perPage + index + 1 }}</td>
            <td v-if="columnVisibility.isVisible('name')">
              <button class="name-link" @click="detailUnit = unit">{{ unit.name }}</button>
            </td>
            <td v-if="columnVisibility.isVisible('status')">
              <ToggleSwitch
                :model-value="unit.is_active"
                :title="unit.is_active ? 'Click to deactivate' : 'Click to activate'"
                @change="onToggleActive(unit)"
              />
            </td>
            <td v-if="columnVisibility.isVisible('created_at')">{{ formatDateTime(unit.created_at) }}</td>
            <td v-if="columnVisibility.isVisible('updated_at')">{{ formatDateTime(unit.updated_at) }}</td>
            <td class="actions-col">
              <button class="action-btn" @click="openEdit(unit)" title="Edit">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(unit)" title="Delete">
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

    <UnitDetailModal
      v-if="detailUnit"
      :unit="detailUnit"
      :can-edit="true"
      @close="detailUnit = null"
      @edit="onDetailEdit"
    />

    <ConfirmDialog
      v-if="deleteTarget"
      :title="`Delete ${deleteTarget.name}?`"
      message="This will permanently remove the unit from the master list."
      confirm-text="Delete"
      cancel-text="Cancel"
      @confirm="performDelete"
      @cancel="deleteTarget = null"
    />

    <ConfirmDialog
      v-if="deactivateTarget"
      :title="`Unit is in use`"
      :message="`${deactivateTarget.name} is referenced by existing products and cannot be deleted. Deactivate it instead? Inactive units stay linked to existing products but won't appear in new-product pickers.`"
      confirm-text="Deactivate"
      cancel-text="Cancel"
      @confirm="performDeactivate"
      @cancel="deactivateTarget = null"
    />

    <ConfirmDialog
      v-if="togglingUnit"
      :title="togglingUnit.is_active ? 'Deactivate Unit' : 'Reactivate Unit'"
      :message="togglingUnit.is_active
        ? `Are you sure you want to deactivate '${togglingUnit.name}'? Existing products will stay linked, but this unit won't appear in new-product pickers.`
        : `Are you sure you want to reactivate '${togglingUnit.name}'?`"
      :confirm-text="togglingUnit.is_active ? 'Yes, deactivate' : 'Yes, reactivate'"
      cancel-text="Cancel"
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
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import Icon from '@/components/common/Icon.vue'
import ToggleSwitch from '@/components/common/ToggleSwitch.vue'
import Pagination from '@/components/common/Pagination.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import UnitFormModal from '@/features/units/components/UnitFormModal.vue'
import UnitDetailModal from '@/features/units/components/UnitDetailModal.vue'
import { useClientPagination } from '@/composables/useClientPagination'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { fetchUnits, deleteUnit, updateUnit } from '@/features/units/services/unitService'
import { UNIT_COLUMNS, UNIT_INITIAL_COL_WIDTHS } from '@/features/units/constants'
import { ErrorCode } from '@/utils/errorCodes'
import { normalizeText } from '@/utils/textNormalizer'
import { formatDateTime } from '@/utils/datetime'

const columnVisibility = useColumnVisibility({
  storageKey: 'units',
  columns: UNIT_COLUMNS,
  lockedKeys: ['actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(UNIT_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')

const units = ref([])
const loading = ref(false)
const searchQuery = ref('')
const includeInactive = ref(true)

const showForm = ref(false)
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
    if (!includeInactive.value && !u.is_active) return false
    if (!needle) return true
    return normalizeText(u.name).includes(needle)
  })
})

const sort = useSortCriteria()
const sortedUnits = computed(() =>
  sort.sortItems(filteredUnits.value, (unit, key) => {
    if (key === 'status') return unit.is_active ? 1 : 0
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

watch([searchQuery, includeInactive, () => sort.sortCriteria.value], resetPage, { deep: true })

const load = async () => {
  if (!currentStore?.value?.id) {
    units.value = []
    return
  }
  loading.value = true
  try {
    units.value = await fetchUnits({ storeId: currentStore.value.id, includeInactive: true })
  } finally {
    loading.value = false
  }
}

onMounted(load)

watch(() => currentStore?.value?.id, load)

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
  closeForm()
  await load()
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
      alert(err.message)
    }
  }
}

const performDeactivate = async () => {
  const unit = deactivateTarget.value
  deactivateTarget.value = null
  try {
    await updateUnit({ id: unit.id, input: { is_active: false } })
    await load()
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
  } catch (err) {
    unit.is_active = previous
    alert(err.message)
  }
}
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; }
.toggle { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #374151; cursor: pointer; flex-shrink: 0; }

.btn-create { display: flex; align-items: center; gap: 6px; padding: 9px 16px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-create:hover { background: #333; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }
tbody tr.inactive { background: #fafafa; }
tbody tr.inactive td { color: #6b7280; }

.no-col { color: #6b7280; font-variant-numeric: tabular-nums; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.name-link:hover { color: #2563eb; text-decoration: underline; }

.actions-col { text-align: right; white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; margin-left: 4px; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
