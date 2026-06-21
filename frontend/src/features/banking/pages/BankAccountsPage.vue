<template>
  <PageContainer :maxWidth="1200">
    <PageHeader title="Bank Accounts" subtitle="All bank accounts attached to businesses, customers, and suppliers.">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Bank Account
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      title="No business found"
      description="You need to create or select a business before managing bank accounts."
    />

    <EmptyState
      v-else-if="!currentStore"
      title="No store selected"
      description="Select a store to manage bank accounts."
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" placeholder="Search by account number, holder name, or branch..." />
        <ClearFiltersButton v-if="hasActiveFilters" @click="clearFilters" />
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
        <HistoryButton @click="showHistory = true" />
        <ImportButton @click="showImport = true" />
        <ExportButton :exporting="exporting" :disabled="sortedAccounts.length === 0" @click="runExport" />
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
        :show-status="false"
        show-export
        :exporting="exporting"
        @clear="clearSelection"
        @export="runExport"
        @delete="requestBulk('delete')"
      />

      <LoadingState v-if="loading">Loading bank accounts...</LoadingState>

      <EmptyState
        v-else-if="accounts.length === 0 && !searchQuery"
        title="No bank accounts yet"
        description="Add a bank account for a business, customer, or supplier to get started."
      />

      <EmptyState
        v-else-if="accounts.length === 0"
        :description="`No bank accounts matching &quot;${searchQuery}&quot;`"
      />

      <EmptyState
        v-else-if="sortedAccounts.length === 0"
        description="No bank accounts match the current filters."
      />

      <div v-else class="table-wrap">
        <ResizableTable :key="tableKey" :columns="columnVisibility.visibleColumns.value" :initial-widths="visibleWidths" sticky-header>
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

          <tr v-for="(a, idx) in paginatedAccounts" :key="a.id">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(a.id)" @change="toggleRow(a.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('stt')" class="stt-col">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
            <td v-if="columnVisibility.isVisible('owner')">
              <ObjectBadge :type="a.party?.type" />
            </td>
            <td v-if="columnVisibility.isVisible('owner_name')">
              <button
                v-if="a.party?.display_name"
                class="name-link"
                :title="a.party.display_name"
                @click="detailAccount = a"
              >{{ a.party.display_name }}</button>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('bank')"><span class="bank-cell">{{ a.bank?.short_name }}</span></td>
            <td v-if="columnVisibility.isVisible('account_number')"><span class="mono">{{ a.account_number }}</span></td>
            <td v-if="columnVisibility.isVisible('holder_name')"><span class="truncate" :title="a.account_holder_name || ''">{{ a.account_holder_name || '—' }}</span></td>
            <td v-if="columnVisibility.isVisible('branch')"><span class="truncate" :title="a.branch || ''">{{ a.branch || '—' }}</span></td>
            <td v-if="columnVisibility.isVisible('province')"><span class="truncate" :title="a.province?.name_vi || ''">{{ a.province?.name_vi || '—' }}</span></td>
            <td v-if="columnVisibility.isVisible('created_at')" class="date-col">{{ formatDateTime(a.created_at) }}</td>
            <td v-if="columnVisibility.isVisible('updated_at')" class="date-col">{{ formatDateTime(a.updated_at) }}</td>
            <td class="actions-col">
              <button class="action-btn" @click="openEdit(a)" title="Edit">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(a)" title="Delete">
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

    <BankAccountFormModal
      v-if="showForm"
      :account="editingAccount"
      @close="closeForm"
      @saved="onSaved"
    />

    <ImportModal
      v-if="showImport"
      title="Import Bank Accounts"
      template-filename="bank-accounts-import-template.xlsx"
      :required-headers="['Type', 'Name', 'Bank', 'Account Number']"
      :optional-headers="['Phone', 'Province', 'Account Holder Name', 'Branch']"
      :instructions="importInstructions"
      :download-template="() => downloadBankAccountsImportTemplate({ businessId: currentBusiness.id })"
      :preview="(file) => previewBankAccountsImport({ businessId: currentBusiness.id, file })"
      :revalidate="(rows) => revalidateBankAccountsImport({ businessId: currentBusiness.id, rows })"
      :start="(rows, originalFilename) => startBankAccountsImport({ businessId: currentBusiness.id, rows, originalFilename })"
      :status="(id) => fetchImportStatus({ importId: id })"
      @close="showImport = false"
      @imported="onImported"
    >
      <template #review-banner="{ rows, resolveReference }">
        <MissingReferencesImportBanner
          :rows="rows"
          :business-id="currentBusiness.id"
          :resolve-reference="resolveReference"
        />
      </template>
    </ImportModal>

    <HistoryModal
      v-if="showHistory"
      title="Bank Account History"
      :tabs="historyTabs"
      @close="showHistory = false"
    />

    <BankAccountDetailModal
      v-if="detailAccount"
      :account="detailAccount"
      :can-edit="true"
      @close="detailAccount = null"
      @edit="onDetailEdit"
    />

    <ConfirmDialog
      v-if="deleteTarget"
      :title="`Delete bank account ${deleteTarget.account_number}?`"
      message="This will permanently remove the bank account."
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
import BankAccountFormModal from '@/features/banking/components/BankAccountFormModal.vue'
import BankAccountDetailModal from '@/features/banking/components/BankAccountDetailModal.vue'
import MissingReferencesImportBanner from '@/components/common/MissingReferencesImportBanner.vue'
import ObjectBadge from '@/components/common/ObjectBadge.vue'
import { useClientPagination } from '@/composables/useClientPagination'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { useRowSelection } from '@/composables/useRowSelection'
import { useBulkActions } from '@/composables/useBulkActions'
import { useExport } from '@/composables/useExport'
import { useDateRangeFilter } from '@/composables/useDateRangeFilter'
import {
  fetchBankAccounts, deleteBankAccount, startBankAccountExport,
  downloadBankAccountsImportTemplate, previewBankAccountsImport, revalidateBankAccountsImport, startBankAccountsImport,
} from '@/features/banking/services/bankAccountService'
import { fetchImportStatus } from '@/features/imports/services/importService'
import { BANK_ACCOUNT_COLUMNS, BANK_ACCOUNT_INITIAL_COL_WIDTHS } from '@/features/banking/constants'
import { normalizeText } from '@/utils/textNormalizer'
import { formatDateTime } from '@/utils/datetime'

const columnVisibility = useColumnVisibility({
  storageKey: 'bank_accounts',
  columns: BANK_ACCOUNT_COLUMNS,
  lockedKeys: ['select', 'stt', 'actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(BANK_ACCOUNT_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')
const showToast = inject('showToast')

const accounts = ref([])
const loading = ref(false)
const searchQuery = ref('')
const { startDate, endDate, dateField, isActive: dateRangeActive, inDateRange, clear: clearDateRange } = useDateRangeFilter()

const hasActiveFilters = computed(() => dateRangeActive.value)
const clearFilters = () => { clearDateRange() }

const showForm = ref(false)
const showImport = ref(false)
const showHistory = ref(false)

const historyTabs = computed(() => [
  { key: 'imports', label: 'Imports', component: ImportHistoryPanel, props: { scope: 'business', scopeId: currentBusiness.value?.id, type: 'bank_accounts' } },
  { key: 'exports', label: 'Exports', component: ExportHistoryPanel, props: { scope: 'business', scopeId: currentBusiness.value?.id, types: ['bank-accounts'] } },
])
const editingAccount = ref(null)
const detailAccount = ref(null)
const deleteTarget = ref(null)

const importInstructions = [
  { text: 'Type must be one of Supplier, Customer or Business (Vietnamese labels also work).', example: 'Supplier · Customer · Business  /  Nhà cung cấp · Khách hàng · Doanh nghiệp' },
  'Name must already exist in this business — the owner is never created by the import.',
  { text: 'Customers can share a name, so add the Phone column to point at the right one.', example: 'Name: Nguyễn Văn A   Phone: 0901234567' },
  { text: 'Bank is matched by its short, Vietnamese, or English name; create a missing bank right from the preview.', example: 'VCB  ·  Vietcombank  ·  Ngân hàng TMCP Ngoại thương Việt Nam' },
]

const onDetailEdit = (account) => {
  detailAccount.value = null
  openEdit(account)
}

const canDelete = computed(() => {
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return role === 'owner' || role === 'accountant'
})

const filteredAccounts = computed(() => accounts.value.filter(inDateRange))

// Most recently updated first by default, so the No. column reads newest-to-oldest.
const orderedAccounts = computed(() =>
  [...filteredAccounts.value].sort(
    (a, b) => new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0),
  )
)

const sort = useSortCriteria()
const sortedAccounts = computed(() =>
  sort.sortItems(orderedAccounts.value, (account, key) => {
    switch (key) {
      case 'owner':           return account.party?.type || ''
      case 'owner_name':      return normalizeText(account.party?.display_name || '')
      case 'bank':            return normalizeText(account.bank?.short_name || '')
      case 'account_number':  return account.account_number || ''
      case 'holder_name':     return normalizeText(account.account_holder_name || '')
      case 'branch':          return normalizeText(account.branch || '')
      case 'province':        return normalizeText(account.province?.name_vi || '')
      case 'created_at':
      case 'updated_at':      return new Date(account[key] || 0).getTime()
      default:                return ''
    }
  })
)

const {
  currentPage,
  perPage,
  total,
  totalPages,
  paginated: paginatedAccounts,
  setPerPage,
  resetPage,
} = useClientPagination(sortedAccounts)

const selectableIds = computed(() => sortedAccounts.value.map(a => String(a.id)))
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: selectableIds, scopeToEligible: true })

const { exporting, run: runExport } = useExport({
  start: () => {
    const params = { search: searchQuery.value.trim() || undefined }
    if (selectedIds.value.size > 0) {
      params.ids = Array.from(selectedIds.value)
    }
    params.columns = columnVisibility.togglableColumns
      .filter((col) => columnVisibility.isVisible(col.key))
      .map((col) => col.key)
    return startBankAccountExport({ businessId: currentBusiness.value.id, params })
  },
  defaultFilename: (id) => `bank-accounts-${id}.xlsx`,
  onSuccess: () => showToast('Bank account export ready.', 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

const { bulkBusy, pendingAction, request: requestBulk, confirm: confirmBulk, cancel: cancelBulk, confirmConfig } = useBulkActions({
  selectedIds, clearSelection, reload: () => load(), noun: 'bank account',
  remove: (id) => deleteBankAccount({ id }),
})

watch([searchQuery, startDate, endDate, dateField, () => sort.sortCriteria.value], resetPage, { deep: true })

let searchTimer = null

const load = async () => {
  if (!currentBusiness?.value?.id) return
  loading.value = true
  try {
    accounts.value = await fetchBankAccounts({
      businessId: currentBusiness.value.id,
      search: searchQuery.value.trim() || null,
    })
  } finally {
    loading.value = false
  }
}

onMounted(load)

watch(() => currentBusiness?.value?.id, () => { clearSelection(); load() })

watch(searchQuery, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 250)
})

const openCreate = () => {
  editingAccount.value = null
  showForm.value = true
}

const openEdit = (account) => {
  editingAccount.value = account
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingAccount.value = null
}

const onSaved = async () => {
  closeForm()
  await load()
}

const onImported = async () => {
  await load()
  showToast('Bank accounts imported.', 'success')
}

const confirmDelete = (account) => {
  deleteTarget.value = account
}

const performDelete = async () => {
  const account = deleteTarget.value
  deleteTarget.value = null
  try {
    await deleteBankAccount({ id: account.id })
    await load()
  } catch (err) {
    alert(err.message)
  }
}
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; }


.btn-create { display: flex; align-items: center; gap: 6px; padding: 9px 16px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-create:hover { background: #333; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bank-cell { font-weight: 600; color: #111; }
.stt-col { color: #6b7280; font-variant-numeric: tabular-nums; }
.date-col { color: #6b7280; white-space: nowrap; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }

.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.name-link:hover { color: #2563eb; text-decoration: underline; }
.empty-val { color: #d1d5db; }

.bank-cell { font-weight: 600; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }

.actions-col { text-align: right; white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; margin-left: 4px; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
