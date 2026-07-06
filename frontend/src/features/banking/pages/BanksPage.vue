<template>
  <PageContainer :maxWidth="1100">
    <PageHeader :title="$t('banking.banksTitle')" :subtitle="$t('banking.banksSubtitle')">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ $t('banking.newBank') }}
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      :title="$t('shared.noBusinessTitle')"
      :description="$t('banking.noBanksBusinessDesc')"
    />

    <EmptyState
      v-else-if="!currentStore"
      :title="$t('shared.noStoreTitle')"
      :description="$t('banking.noBanksStoreDesc')"
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" :placeholder="$t('shared.searchByName')" />
        <label class="toggle">
          <input v-model="includeInactive" type="checkbox" />
          {{ $t('banking.showInactive') }}
        </label>
        <ClearFiltersButton v-if="hasActiveFilters" @click="clearFilters" />
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
        <HistoryButton @click="showHistory = true" />
        <ImportButton @click="showImport = true" />
        <ExportButton :exporting="exporting" :disabled="sortedBanks.length === 0" @click="runExport" />
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

      <LoadingState v-if="loading">{{ $t('banking.loadingBanks') }}</LoadingState>

      <EmptyState
        v-else-if="filteredBanks.length === 0 && banks.length === 0"
        :title="$t('banking.noBanksTitle')"
        :description="$t('banking.noBanksDesc')"
      />

      <EmptyState
        v-else-if="filteredBanks.length === 0"
        :description="$t('shared.noResults')"
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

          <tr v-for="(bank, idx) in paginatedBanks" :key="bank.id" :class="{ inactive: !bank.is_active, 'row-edited': isRecent(bank.id) }">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(bank.id)" @change="toggleRow(bank.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('stt')" class="stt-col">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
            <td v-if="columnVisibility.isVisible('short_name')">
              <button class="name-link" v-tooltip="bank.short_name" @click="detailBank = bank"><HighlightText :text="bank.short_name" :query="searchQuery" /></button>
            </td>
            <td v-if="columnVisibility.isVisible('full_name_vi')"><span class="truncate" :title="bank.full_name_vi"><HighlightText :text="bank.full_name_vi" :query="searchQuery" /></span></td>
            <td v-if="columnVisibility.isVisible('full_name_en')"><span class="truncate" :title="bank.full_name_en"><HighlightText :text="bank.full_name_en" :query="searchQuery" /></span></td>
            <td v-if="columnVisibility.isVisible('status')">
              <ToggleSwitch
                :model-value="bank.is_active"
                :title="bank.is_active ? $t('shared.clickToDeactivate') : $t('shared.clickToActivate')"
                @change="onToggleActive(bank)"
              />
            </td>
            <td v-if="columnVisibility.isVisible('created_at')" class="date-col">{{ formatDateTime(bank.created_at) }}</td>
            <td v-if="columnVisibility.isVisible('updated_at')" class="date-col">{{ formatDateTime(bank.updated_at) }}</td>
            <td class="actions-col">
              <button class="action-btn" @click="openEdit(bank)" :title="$t('common.edit')">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(bank)" :title="$t('common.delete')">
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

    <ImportModal
      v-if="showImport"
      :title="$t('banking.importBanksTitle')"
      template-filename="banks-import-template.xlsx"
      :required-headers="['Short Name', 'Vietnamese Name', 'English Name']"
      :download-template="() => downloadBanksImportTemplate({ businessId: currentBusiness.id })"
      :preview="(file) => previewBanksImport({ businessId: currentBusiness.id, file })"
      :revalidate="(rows) => revalidateBanksImport({ businessId: currentBusiness.id, rows })"
      :start="(rows, originalFilename) => startBanksImport({ businessId: currentBusiness.id, rows, originalFilename })"
      :status="(id) => fetchImportStatus({ importId: id })"
      @close="showImport = false"
      @imported="onImported"
    />

    <HistoryModal
      v-if="showHistory"
      :title="$t('banking.bankHistoryTitle')"
      :tabs="historyTabs"
      @close="showHistory = false"
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
      :title="$t('banking.deleteBankTitle', { name: deleteTarget.short_name })"
      :message="$t('banking.deleteBankMessage')"
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
      :title="$t('banking.bankInUseTitle')"
      :message="$t('banking.bankInUseMessage', { name: deactivateTarget.short_name })"
      :confirm-text="$t('common.deactivate')"
      :cancel-text="$t('common.cancel')"
      @confirm="performDeactivate"
      @cancel="deactivateTarget = null"
    />

    <ConfirmDialog
      v-if="togglingBank"
      :title="togglingBank.is_active ? $t('banking.toggleDeactivateBankTitle') : $t('banking.toggleReactivateBankTitle')"
      :message="togglingBank.is_active
        ? $t('banking.toggleDeactivateBankMessage', { name: togglingBank.short_name })
        : $t('banking.toggleReactivateBankMessage', { name: togglingBank.short_name })"
      :confirm-text="togglingBank.is_active ? $t('banking.toggleDeactivateConfirm') : $t('banking.toggleReactivateConfirm')"
      :cancel-text="$t('common.cancel')"
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
import HighlightText from '@/components/common/HighlightText.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import Icon from '@/components/common/Icon.vue'
import ToggleSwitch from '@/components/common/ToggleSwitch.vue'
import Pagination from '@/components/common/Pagination.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import ExportButton from '@/components/common/ExportButton.vue'
import ClearFiltersButton from '@/components/common/ClearFiltersButton.vue'
import DateRangeFilters from '@/components/common/DateRangeFilters.vue'
import ImportButton from '@/components/common/ImportButton.vue'
import ImportModal from '@/components/common/ImportModal.vue'
import HistoryModal from '@/components/common/HistoryModal.vue'
import ImportHistoryPanel from '@/components/common/ImportHistoryPanel.vue'
import ExportHistoryPanel from '@/components/common/ExportHistoryPanel.vue'
import HistoryButton from '@/components/common/HistoryButton.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import BulkStatusBar from '@/components/common/BulkStatusBar.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import BankFormModal from '@/features/banking/components/BankFormModal.vue'
import BankDetailModal from '@/features/banking/components/BankDetailModal.vue'
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
  fetchBanks, deleteBank, updateBank, startBankExport,
  downloadBanksImportTemplate, previewBanksImport, revalidateBanksImport, startBanksImport,
} from '@/features/banking/services/bankService'
import { fetchImportStatus } from '@/features/imports/services/importService'
import { BANK_COLUMNS, BANK_INITIAL_COL_WIDTHS } from '@/features/banking/constants'

const columnVisibility = useColumnVisibility({
  storageKey: 'banks',
  columns: BANK_COLUMNS,
  lockedKeys: ['select', 'stt', 'actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(BANK_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))
import { ErrorCode } from '@/utils/errorCodes'
import { normalizeText } from '@/utils/textNormalizer'
import { formatDateTime } from '@/utils/datetime'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')
const showToast = inject('showToast')

const banks = ref([])
const loading = ref(false)
const searchQuery = ref('')
const includeInactive = ref(true)
const { startDate, endDate, dateField, isActive: dateRangeActive, inDateRange, clear: clearDateRange } = useDateRangeFilter()

const hasActiveFilters = computed(() => dateRangeActive.value)
const clearFilters = () => { clearDateRange() }

const showForm = ref(false)
const showImport = ref(false)
const showHistory = ref(false)

const historyTabs = computed(() => [
  { key: 'imports', label: t('shared.imports'), component: ImportHistoryPanel, props: { scope: 'business', scopeId: currentBusiness.value?.id, type: 'banks' } },
  { key: 'exports', label: t('shared.exports'), component: ExportHistoryPanel, props: { scope: 'business', scopeId: currentBusiness.value?.id, types: ['banks'] } },
])
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
    if (!inDateRange(b)) return false
    if (!needle) return true
    return (
      normalizeText(b.short_name).includes(needle) ||
      normalizeText(b.full_name_vi).includes(needle) ||
      normalizeText(b.full_name_en).includes(needle)
    )
  })
})

// Newest-first on load, then held stable within the session so saving an edit
// doesn't bump the row to the top and cost you your place while editing down the list.
const newestFirstBanks = computed(() =>
  [...filteredBanks.value].sort(
    (a, b) => new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0),
  )
)
const { ordered: orderedBanks } = useSessionOrder(newestFirstBanks)

const sort = useSortCriteria()
const sortedBanks = computed(() =>
  sort.sortItems(orderedBanks.value, (bank, key) => {
    if (key === 'status') return bank.is_active ? 1 : 0
    if (key === 'created_at' || key === 'updated_at') return new Date(bank[key] || 0).getTime()
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

const { mark, isRecent } = useRowHighlight()

const selectableIds = computed(() => sortedBanks.value.map(b => String(b.id)))
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: selectableIds, scopeToEligible: true })

const { exporting, run: runExport } = useExport({
  start: () => {
    const params = {
      search: searchQuery.value.trim() || undefined,
      include_inactive: includeInactive.value ? undefined : 'false',
    }
    if (selectedIds.value.size > 0) {
      params.ids = Array.from(selectedIds.value)
    }
    params.columns = columnVisibility.togglableColumns
      .filter((col) => columnVisibility.isVisible(col.key))
      .map((col) => col.key)
    return startBankExport({ businessId: currentBusiness.value.id, params })
  },
  defaultFilename: (id) => `banks-${id}.xlsx`,
  onSuccess: () => showToast(t('banking.bankExportReady'), 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

const { bulkBusy, pendingAction, request: requestBulk, confirm: confirmBulk, cancel: cancelBulk, confirmConfig } = useBulkActions({
  selectedIds, clearSelection, reload: () => load(), nounKey: 'bank',
  setActive: (id, isActive) => updateBank({ id, input: { is_active: isActive } }),
  remove: (id) => deleteBank({ id }),
})

watch([searchQuery, includeInactive, startDate, endDate, dateField, () => sort.sortCriteria.value], resetPage, { deep: true })

const load = async () => {
  if (!currentBusiness?.value?.id) {
    banks.value = []
    return
  }
  if (!banks.value.length) loading.value = true
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
  const editedId = editingBank.value?.id
  closeForm()
  await load()
  if (editedId) mark(editedId)
}

const onImported = async () => {
  await load()
  showToast(t('banking.banksImported'), 'success')
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
      alert(translateError(err))
    }
  }
}

const performDeactivate = async () => {
  const bank = deactivateTarget.value
  deactivateTarget.value = null
  try {
    await updateBank({ id: bank.id, input: { is_active: false } })
    await load()
    mark(bank.id)
  } catch (err) {
    alert(translateError(err))
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
    mark(bank.id)
  } catch (err) {
    bank.is_active = previous
    alert(translateError(err))
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
.stt-col { color: #6b7280; font-variant-numeric: tabular-nums; }
.date-col { color: #6b7280; white-space: nowrap; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.name-link:hover { color: #2563eb; text-decoration: underline; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.actions-col { text-align: right; white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; margin-left: 4px; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
