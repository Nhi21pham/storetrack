<template>
  <PageContainer :maxWidth="1100">
    <PageHeader title="Banks" subtitle="Manage the master list of banks used across bank accounts.">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Bank
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      title="No business found"
      description="You need to create or select a business before managing banks."
    />

    <EmptyState
      v-else-if="!currentStore"
      title="No store selected"
      description="Select a store to manage banks."
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

      <LoadingState v-if="loading">Loading banks...</LoadingState>

      <EmptyState
        v-else-if="filteredBanks.length === 0 && banks.length === 0"
        title="No banks yet"
        description="Add your first bank to get started."
      />

      <EmptyState
        v-else-if="filteredBanks.length === 0"
        :description="`No banks matching &quot;${searchQuery}&quot;`"
      />

      <div v-else class="table-wrap">
        <ResizableTable :key="tableKey" :columns="columnVisibility.visibleColumns.value" :initial-widths="visibleWidths">
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

          <tr v-for="bank in paginatedBanks" :key="bank.id" :class="{ inactive: !bank.is_active }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(bank.id)" @change="toggleRow(bank.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('short_name')">
              <button class="name-link" @click="detailBank = bank">{{ bank.short_name }}</button>
            </td>
            <td v-if="columnVisibility.isVisible('full_name_vi')"><span class="truncate" :title="bank.full_name_vi">{{ bank.full_name_vi }}</span></td>
            <td v-if="columnVisibility.isVisible('full_name_en')"><span class="truncate" :title="bank.full_name_en">{{ bank.full_name_en }}</span></td>
            <td v-if="columnVisibility.isVisible('status')">
              <ToggleSwitch
                :model-value="bank.is_active"
                :title="bank.is_active ? 'Click to deactivate' : 'Click to activate'"
                @change="onToggleActive(bank)"
              />
            </td>
            <td class="actions-col">
              <button class="action-btn" @click="openEdit(bank)" title="Edit">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(bank)" title="Delete">
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

    <BankFormModal
      v-if="showForm"
      :bank="editingBank"
      :business-id="currentBusiness?.id"
      @close="closeForm"
      @saved="onSaved"
      @pick-existing="onPickExisting"
    />

    <BankDetailModal
      v-if="detailBank"
      :bank="detailBank"
      :can-edit="true"
      @close="detailBank = null"
      @edit="onDetailEdit"
    />

    <ConfirmDialog
      v-if="deleteTarget"
      :title="`Delete ${deleteTarget.short_name}?`"
      message="This will permanently remove the bank from the master list."
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
      :title="`Bank is in use`"
      :message="`${deactivateTarget.short_name} is referenced by existing bank accounts and cannot be deleted. Deactivate it instead? Inactive banks stay linked to existing accounts but won't appear in new-account pickers.`"
      confirm-text="Deactivate"
      cancel-text="Cancel"
      @confirm="performDeactivate"
      @cancel="deactivateTarget = null"
    />

    <ConfirmDialog
      v-if="togglingBank"
      :title="togglingBank.is_active ? 'Deactivate Bank' : 'Reactivate Bank'"
      :message="togglingBank.is_active
        ? `Are you sure you want to deactivate '${togglingBank.short_name}'? Existing bank accounts will stay linked, but this bank won't appear in new-account pickers.`
        : `Are you sure you want to reactivate '${togglingBank.short_name}'?`"
      :confirm-text="togglingBank.is_active ? 'Yes, deactivate' : 'Yes, reactivate'"
      cancel-text="Cancel"
      :type="togglingBank.is_active ? 'warning' : 'success'"
      @confirm="handleToggle"
      @cancel="togglingBank = null"
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
import BulkStatusBar from '@/components/common/BulkStatusBar.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import BankFormModal from '@/features/banking/components/BankFormModal.vue'
import BankDetailModal from '@/features/banking/components/BankDetailModal.vue'
import { useClientPagination } from '@/composables/useClientPagination'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { useRowSelection } from '@/composables/useRowSelection'
import { useBulkActions } from '@/composables/useBulkActions'
import { fetchBanks, deleteBank, updateBank } from '@/features/banking/services/bankService'
import { BANK_COLUMNS, BANK_INITIAL_COL_WIDTHS } from '@/features/banking/constants'

const columnVisibility = useColumnVisibility({
  storageKey: 'banks',
  columns: BANK_COLUMNS,
  lockedKeys: ['select', 'actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(BANK_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))
import { ErrorCode } from '@/utils/errorCodes'
import { normalizeText } from '@/utils/textNormalizer'

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')

const banks = ref([])
const loading = ref(false)
const searchQuery = ref('')
const includeInactive = ref(true)

const showForm = ref(false)
const editingBank = ref(null)
const detailBank = ref(null)
const deleteTarget = ref(null)
const deactivateTarget = ref(null)
const togglingBank = ref(null)

const onDetailEdit = (bank) => {
  detailBank.value = null
  openEdit(bank)
}

const canDelete = computed(() => {
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return role === 'owner' || role === 'accountant'
})

const filteredBanks = computed(() => {
  const needle = normalizeText(searchQuery.value)
  return banks.value.filter(b => {
    if (!includeInactive.value && !b.is_active) return false
    if (!needle) return true
    return (
      normalizeText(b.short_name).includes(needle) ||
      normalizeText(b.full_name_vi).includes(needle) ||
      normalizeText(b.full_name_en).includes(needle)
    )
  })
})

const sort = useSortCriteria()
const sortedBanks = computed(() =>
  sort.sortItems(filteredBanks.value, (bank, key) => {
    if (key === 'status') return bank.is_active ? 1 : 0
    const v = bank[key]
    return typeof v === 'string' ? normalizeText(v) : (v ?? '')
  })
)

const {
  currentPage,
  perPage,
  total,
  totalPages,
  paginated: paginatedBanks,
  setPerPage,
  resetPage,
} = useClientPagination(sortedBanks)

const visibleIds = computed(() => paginatedBanks.value.map(b => String(b.id)))
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: visibleIds })

const { bulkBusy, pendingAction, request: requestBulk, confirm: confirmBulk, cancel: cancelBulk, confirmConfig } = useBulkActions({
  selectedIds, clearSelection, reload: () => load(), noun: 'bank',
  setActive: (id, isActive) => updateBank({ id, input: { is_active: isActive } }),
  remove: (id) => deleteBank({ id }),
})

watch([searchQuery, includeInactive, () => sort.sortCriteria.value], resetPage, { deep: true })

const load = async () => {
  if (!currentBusiness?.value?.id) {
    banks.value = []
    return
  }
  loading.value = true
  try {
    banks.value = await fetchBanks({ businessId: currentBusiness.value.id, includeInactive: true })
  } finally {
    loading.value = false
  }
}

onMounted(load)

watch(() => currentBusiness?.value?.id, () => { clearSelection(); load() })

const openCreate = () => {
  editingBank.value = null
  showForm.value = true
}

const openEdit = (bank) => {
  editingBank.value = bank
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingBank.value = null
}

const onSaved = async () => {
  closeForm()
  await load()
}

const onPickExisting = (bank) => {
  editingBank.value = bank
  showForm.value = true
}

const confirmDelete = (bank) => {
  deleteTarget.value = bank
}

const performDelete = async () => {
  const bank = deleteTarget.value
  deleteTarget.value = null
  try {
    await deleteBank({ id: bank.id })
    await load()
  } catch (err) {
    if (err.code === ErrorCode.BANK_IN_USE) {
      deactivateTarget.value = bank
    } else {
      alert(err.message)
    }
  }
}

const performDeactivate = async () => {
  const bank = deactivateTarget.value
  deactivateTarget.value = null
  try {
    await updateBank({ id: bank.id, input: { is_active: false } })
    await load()
  } catch (err) {
    alert(err.message)
  }
}

const onToggleActive = (bank) => {
  togglingBank.value = bank
}

const handleToggle = async () => {
  const bank = togglingBank.value
  togglingBank.value = null
  if (!bank) return
  const nextValue = !bank.is_active
  const previous = bank.is_active
  bank.is_active = nextValue
  try {
    await updateBank({ id: bank.id, input: { is_active: nextValue } })
  } catch (err) {
    bank.is_active = previous
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
tbody tr.inactive td.actions-col { background: #fafafa; }
.short { font-weight: 600; color: #111; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.name-link:hover { color: #2563eb; text-decoration: underline; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.actions-col { text-align: right; white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; margin-left: 4px; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
