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
      </div>

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

      <div v-else class="table-wrap">
        <ResizableTable :columns="BANK_ACCOUNT_COLUMNS" :initial-widths="BANK_ACCOUNT_INITIAL_COL_WIDTHS">
          <tr v-for="a in paginatedAccounts" :key="a.id">
            <td>
              <span class="type-badge" :class="a.party?.type">{{ ownerLabel(a) }}</span>
            </td>
            <td><span class="bank-cell">{{ a.bank?.short_name }}</span></td>
            <td><span class="mono">{{ a.account_number }}</span></td>
            <td><span class="truncate" :title="a.account_holder_name || ''">{{ a.account_holder_name || '—' }}</span></td>
            <td><span class="truncate" :title="a.branch || ''">{{ a.branch || '—' }}</span></td>
            <td><span class="truncate" :title="a.province?.name_vi || ''">{{ a.province?.name_vi || '—' }}</span></td>
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

    <ConfirmDialog
      v-if="deleteTarget"
      :title="`Delete bank account ${deleteTarget.account_number}?`"
      message="This will permanently remove the bank account."
      confirm-text="Delete"
      cancel-text="Cancel"
      @confirm="performDelete"
      @cancel="deleteTarget = null"
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
import BankAccountFormModal from '@/features/banking/components/BankAccountFormModal.vue'
import { useClientPagination } from '@/composables/useClientPagination'
import { fetchBankAccounts, deleteBankAccount } from '@/features/banking/services/bankAccountService'
import { BANK_ACCOUNT_COLUMNS, BANK_ACCOUNT_INITIAL_COL_WIDTHS } from '@/features/banking/constants'

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')

const accounts = ref([])
const loading = ref(false)
const searchQuery = ref('')

const showForm = ref(false)
const editingAccount = ref(null)
const deleteTarget = ref(null)

const canDelete = computed(() => {
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return role === 'owner' || role === 'accountant'
})

const {
  currentPage,
  perPage,
  total,
  totalPages,
  paginated: paginatedAccounts,
  setPerPage,
  resetPage,
} = useClientPagination(accounts)

watch(searchQuery, resetPage)

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

watch(() => currentBusiness?.value?.id, load)

watch(searchQuery, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 250)
})

const ownerLabel = (account) => {
  const type = account.party?.type
  if (type === 'business') return 'Business'
  if (type === 'customer') return 'Customer'
  if (type === 'supplier') return 'Supplier'
  return type || '—'
}

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
.toolbar { margin-bottom: 16px; }

.btn-create { display: flex; align-items: center; gap: 6px; padding: 9px 16px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-create:hover { background: #333; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bank-cell { font-weight: 600; color: #111; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }

.type-badge { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; background: #f3f4f6; color: #374151; }
.type-badge.business { background: #e0f2fe; color: #0369a1; }
.type-badge.customer { background: #ecfdf5; color: #047857; }
.type-badge.supplier { background: #fef3c7; color: #92400e; }

.bank-cell { font-weight: 600; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }

.actions-col { text-align: right; white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; margin-left: 4px; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
