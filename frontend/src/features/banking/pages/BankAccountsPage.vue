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
        <table class="account-table">
          <thead>
            <tr>
              <th>Owner</th>
              <th>Bank</th>
              <th>Account Number</th>
              <th>Holder Name</th>
              <th>Branch</th>
              <th>Province</th>
              <th class="actions-col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in accounts" :key="a.id">
              <td>
                <span class="type-badge" :class="a.party?.type">{{ ownerLabel(a) }}</span>
              </td>
              <td class="bank-cell">{{ a.bank?.short_name }}</td>
              <td class="mono">{{ a.account_number }}</td>
              <td>{{ a.account_holder_name || '—' }}</td>
              <td>{{ a.branch || '—' }}</td>
              <td>{{ a.province?.name_vi || '—' }}</td>
              <td class="actions-col">
                <button class="row-action" @click="openEdit(a)">Edit</button>
                <button v-if="canDelete" class="row-action danger" @click="confirmDelete(a)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
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
import BankAccountFormModal from '@/features/banking/components/BankAccountFormModal.vue'
import { fetchBankAccounts, deleteBankAccount } from '@/features/banking/services/bankAccountService'

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')

const accounts = ref([])
const loading = ref(false)
const searchQuery = ref('')

const showForm = ref(false)
const editingAccount = ref(null)
const deleteTarget = ref(null)

const canDelete = computed(() => {
  const role = currentStore?.value?.my_role
  return role === 'owner' || role === 'accountant'
})

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

.table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.account-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.account-table thead { background: #f9fafb; }
.account-table th { text-align: left; font-weight: 600; color: #374151; padding: 12px 16px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
.account-table td { padding: 12px 16px; color: #111; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.account-table tbody tr:last-child td { border-bottom: none; }

.type-badge { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; background: #f3f4f6; color: #374151; }
.type-badge.business { background: #e0f2fe; color: #0369a1; }
.type-badge.customer { background: #ecfdf5; color: #047857; }
.type-badge.supplier { background: #fef3c7; color: #92400e; }

.bank-cell { font-weight: 600; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }

.actions-col { text-align: right; white-space: nowrap; }
.row-action { background: none; border: none; padding: 6px 10px; font-size: 13px; color: #374151; cursor: pointer; border-radius: 6px; }
.row-action:hover { background: #f3f4f6; color: #111; }
.row-action.danger { color: #b91c1c; }
.row-action.danger:hover { background: #fef2f2; color: #b91c1c; }
</style>
