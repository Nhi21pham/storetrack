<template>
  <PageContainer :maxWidth="1100">
    <PageHeader title="Banks" subtitle="Manage the master list of banks used across bank accounts.">
      <template #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Bank
        </button>
      </template>
    </PageHeader>

    <div class="toolbar">
      <SearchBar v-model="searchQuery" placeholder="Search by name..." />
      <label class="toggle">
        <input v-model="includeInactive" type="checkbox" />
        Show inactive
      </label>
    </div>

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
      <table class="bank-table">
        <thead>
          <tr>
            <th>Short Name</th>
            <th>Vietnamese Name</th>
            <th>English Name</th>
            <th>Status</th>
            <th class="actions-col"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="bank in filteredBanks" :key="bank.id" :class="{ inactive: !bank.is_active }">
            <td class="short">{{ bank.short_name }}</td>
            <td>{{ bank.full_name_vi }}</td>
            <td>{{ bank.full_name_en }}</td>
            <td>
              <span class="status" :class="bank.is_active ? 'active' : 'inactive'">
                {{ bank.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="actions-col">
              <button class="row-action" @click="openEdit(bank)">Edit</button>
              <button class="row-action danger" @click="confirmDelete(bank)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <BankFormModal
      v-if="showForm"
      :bank="editingBank"
      @close="closeForm"
      @saved="onSaved"
      @pick-existing="onPickExisting"
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
      v-if="deactivateTarget"
      :title="`Bank is in use`"
      :message="`${deactivateTarget.short_name} is referenced by existing bank accounts and cannot be deleted. Deactivate it instead? Inactive banks stay linked to existing accounts but won't appear in new-account pickers.`"
      confirm-text="Deactivate"
      cancel-text="Cancel"
      @confirm="performDeactivate"
      @cancel="deactivateTarget = null"
    />
  </PageContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import BankFormModal from '@/features/banking/components/BankFormModal.vue'
import { fetchBanks, deleteBank, updateBank } from '@/features/banking/services/bankService'
import { ErrorCode } from '@/utils/errorCodes'
import { normalizeText } from '@/utils/textNormalizer'

const banks = ref([])
const loading = ref(true)
const searchQuery = ref('')
const includeInactive = ref(true)

const showForm = ref(false)
const editingBank = ref(null)
const deleteTarget = ref(null)
const deactivateTarget = ref(null)

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

const load = async () => {
  loading.value = true
  try {
    banks.value = await fetchBanks({ includeInactive: true })
  } finally {
    loading.value = false
  }
}

onMounted(load)

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
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.toggle { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #374151; cursor: pointer; }

.btn-create { display: flex; align-items: center; gap: 6px; padding: 9px 16px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-create:hover { background: #333; }

.table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.bank-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.bank-table thead { background: #f9fafb; }
.bank-table th { text-align: left; font-weight: 600; color: #374151; padding: 12px 16px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
.bank-table td { padding: 12px 16px; color: #111; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
.bank-table tbody tr:last-child td { border-bottom: none; }
.bank-table tbody tr.inactive { background: #fafafa; color: #6b7280; }
.bank-table tbody tr.inactive td { color: #6b7280; }
.bank-table td.short { font-weight: 600; }

.status { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.status.active { background: #ecfdf5; color: #047857; }
.status.inactive { background: #fef3c7; color: #92400e; }

.actions-col { text-align: right; white-space: nowrap; }
.row-action { background: none; border: none; padding: 6px 10px; font-size: 13px; color: #374151; cursor: pointer; border-radius: 6px; }
.row-action:hover { background: #f3f4f6; color: #111; }
.row-action.danger { color: #b91c1c; }
.row-action.danger:hover { background: #fef2f2; color: #b91c1c; }
</style>
