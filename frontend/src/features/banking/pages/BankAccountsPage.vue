<template>
  <PageContainer :maxWidth="1200">
    <PageHeader :title="$t('banking.accountsTitle')" :subtitle="$t('banking.accountsSubtitle')">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ $t('banking.newAccount') }}
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      :title="$t('shared.noBusinessTitle')"
      :description="$t('banking.noAccountsBusinessDesc')"
    />

    <EmptyState
      v-else-if="!currentStore"
      :title="$t('shared.noStoreTitle')"
      :description="$t('banking.noAccountsStoreDesc')"
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" :placeholder="$t('banking.searchAccountsPlaceholder')" />
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

      <LoadingState v-if="loading">{{ $t('banking.loadingAccounts') }}</LoadingState>

      <EmptyState
        v-else-if="accounts.length === 0 && !searchQuery"
        :title="$t('banking.noAccountsTitle')"
        :description="$t('banking.noAccountsDesc')"
      />

      <EmptyState
        v-else-if="accounts.length === 0"
        :description="$t('banking.noAccountsMatching', { query: searchQuery })"
      />

      <EmptyState
        v-else-if="sortedAccounts.length === 0"
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

          <tr v-for="(a, idx) in paginatedAccounts" :key="a.id" :class="{ 'row-edited': isRecent(a.id) }">
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
              ><HighlightText :text="a.party.display_name" :query="searchQuery" /></button>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('bank')"><span class="bank-cell"><HighlightText :text="a.bank?.short_name || ''" :query="searchQuery" /></span></td>
            <td v-if="columnVisibility.isVisible('account_number')"><span class="mono"><HighlightText :text="a.account_number" :query="searchQuery" /></span></td>
            <td v-if="columnVisibility.isVisible('holder_name')"><span class="truncate" :title="a.account_holder_name || ''"><HighlightText :text="a.account_holder_name || '—'" :query="searchQuery" /></span></td>
            <td v-if="columnVisibility.isVisible('branch')"><span class="truncate" :title="a.branch || ''"><HighlightText :text="a.branch || '—'" :query="searchQuery" /></span></td>
            <td v-if="columnVisibility.isVisible('province')"><span class="truncate" :title="a.province?.name_vi || ''">{{ a.province?.name_vi || '—' }}</span></td>
            <td v-if="columnVisibility.isVisible('created_at')" class="date-col">{{ formatDateTime(a.created_at) }}</td>
            <td v-if="columnVisibility.isVisible('updated_at')" class="date-col">{{ formatDateTime(a.updated_at) }}</td>
            <td class="actions-col">
              <button class="action-btn" @click="openEdit(a)" :title="$t('common.edit')">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(a)" :title="$t('common.delete')">
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
      :title="$t('banking.importAccountsTitle')"
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
      :title="$t('banking.accountHistoryTitle')"
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
      :title="$t('banking.deleteAccountTitle', { accountNumber: deleteTarget.account_number })"
      :message="$t('banking.deleteAccountMessage')"
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
import { useSessionOrder } from '@/composables/useSessionOrder'
import { useRowHighlight } from '@/composables/useRowHighlight'
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
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

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
  { key: 'imports', label: t('shared.imports'), component: ImportHistoryPanel, props: { scope: 'business', scopeId: currentBusiness.value?.id, type: 'bank_accounts' } },
  { key: 'exports', label: t('shared.exports'), component: ExportHistoryPanel, props: { scope: 'business', scopeId: currentBusiness.value?.id, types: ['bank-accounts'] } },
])
const editingAccount = ref(null)
const detailAccount = ref(null)
const deleteTarget = ref(null)

const importInstructions = computed(() => [
  { text: t('banking.import.type'), example: 'Supplier · Customer · Business  /  Nhà cung cấp · Khách hàng · Doanh nghiệp' },
  t('banking.import.name'),
  { text: t('banking.import.phone'), example: 'Name: Nguyễn Văn A   Phone: 0901234567' },
  { text: t('banking.import.bankMatch'), example: 'VCB  ·  Vietcombank  ·  Ngân hàng TMCP Ngoại thương Việt Nam' },
])

const onDetailEdit = (account) => {
  detailAccount.value = null
  openEdit(account)
}

const canDelete = computed(() => {
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return role === 'owner' || role === 'accountant'
})

const filteredAccounts = computed(() => accounts.value.filter(inDateRange))

// Newest-first on load, then held stable within the session so saving an edit
// doesn't bump the row to the top and cost you your place while editing down the list.
const newestFirstAccounts = computed(() =>
  [...filteredAccounts.value].sort(
    (a, b) => new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0),
  )
)
const { ordered: orderedAccounts } = useSessionOrder(newestFirstAccounts)

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

const { mark, isRecent } = useRowHighlight()

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
  onSuccess: () => showToast(t('banking.accountExportReady'), 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

const { bulkBusy, pendingAction, request: requestBulk, confirm: confirmBulk, cancel: cancelBulk, confirmConfig } = useBulkActions({
  selectedIds, clearSelection, reload: () => load(), nounKey: 'bankAccount',
  remove: (id) => deleteBankAccount({ id }),
})

watch([searchQuery, startDate, endDate, dateField, () => sort.sortCriteria.value], resetPage, { deep: true })

let searchTimer = null

const load = async () => {
  if (!currentBusiness?.value?.id) return
  if (!accounts.value.length) loading.value = true
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
  const editedId = editingAccount.value?.id
  closeForm()
  await load()
  if (editedId) mark(editedId)
}

const onImported = async () => {
  await load()
  showToast(t('banking.accountsImported'), 'success')
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
    alert(translateError(err))
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
