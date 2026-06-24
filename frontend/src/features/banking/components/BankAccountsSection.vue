<template>
  <section class="bank-accounts-section">
    <div class="section-head">
      <h3>{{ $t('banking.sectionTitle') }}</h3>
      <button v-if="canManage && !addingNew && editingId == null" class="btn-add" type="button" @click="startAdd">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ $t('banking.addBankAccount') }}
      </button>
    </div>

    <p v-if="loadError" class="section-error">{{ loadError }}</p>

    <div v-if="loading" class="section-loading">{{ $t('common.loading') }}</div>

    <ul v-else-if="accounts.length > 0 || addingNew" class="account-list">
      <li v-for="(account, index) in accounts" :key="account._localId ?? account.id" class="account-row">
        <template v-if="editingId === (account._localId ?? account.id)">
          <BankAccountForm
            class="inline-form"
            :party-id="partyId"
            :business-id="businessId"
            :account="account"
            :default-holder-name="defaultHolderName"
            :mode="isDraftMode ? 'emit' : 'api'"
            @saved="(payload) => onEditSaved(index, payload)"
            @cancel="cancelEdit"
          />
        </template>
        <template v-else>
          <div class="row-summary">
            <div class="row-header">
              <span class="bank-name">{{ account.bank?.short_name || '—' }}</span>
              <span class="account-number">{{ account.account_number }}</span>
            </div>
            <dl class="row-details">
              <div v-if="account.account_holder_name" class="detail-item">
                <dt>{{ $t('banking.holder') }}</dt>
                <dd>{{ account.account_holder_name }}</dd>
              </div>
              <div v-if="account.branch" class="detail-item">
                <dt>{{ $t('banking.branch') }}</dt>
                <dd>{{ account.branch }}</dd>
              </div>
              <div v-if="account.province?.name_vi" class="detail-item">
                <dt>{{ $t('banking.province') }}</dt>
                <dd>{{ account.province.name_vi }}</dd>
              </div>
            </dl>
          </div>
          <div v-if="canManage" class="row-actions">
            <button type="button" class="action-btn" @click="startEdit(account)" :title="$t('common.edit')">
              <Icon name="edit" :size="14" />
            </button>
            <button type="button" class="action-btn danger" @click="removeAccount(account, index)" :title="$t('banking.remove')">
              <Icon name="delete" :size="14" />
            </button>
          </div>
        </template>
      </li>

      <li v-if="addingNew" class="account-row editing">
        <BankAccountForm
          class="inline-form"
          :party-id="partyId"
          :business-id="businessId"
          :account="null"
          :default-holder-name="defaultHolderName"
          :mode="isDraftMode ? 'emit' : 'api'"
          @saved="onNewSaved"
          @cancel="cancelAdd"
        />
      </li>
    </ul>

    <p v-else class="empty">{{ $t('banking.noAccountsInList') }}</p>
  </section>
</template>

<script setup>
import { ref, computed, inject, onMounted, watch } from 'vue'
import BankAccountForm from '@/features/banking/components/BankAccountForm.vue'
import Icon from '@/components/common/Icon.vue'
import {
  fetchBankAccountsForParty,
  deleteBankAccount,
} from '@/features/banking/services/bankAccountService'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

const currentStore = inject('currentStore', null)

const props = defineProps({
  partyId: { type: [String, Number], default: null },
  businessId: { type: [String, Number], default: null },
  defaultHolderName: { type: String, default: '' },
  modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const isDraftMode = computed(() => !props.partyId)

const canManage = computed(() => {
  if (isDraftMode.value) return true
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return role === 'owner' || role === 'accountant'
})

const accounts = ref([])
const loading = ref(false)
const loadError = ref('')
const addingNew = ref(false)
const editingId = ref(null)

let localIdCounter = 0
const nextLocalId = () => `draft-${++localIdCounter}`

const load = async () => {
  if (isDraftMode.value) {
    accounts.value = props.modelValue.map(a => ({ ...a, _localId: a._localId ?? nextLocalId() }))
    return
  }
  loading.value = true
  loadError.value = ''
  try {
    accounts.value = await fetchBankAccountsForParty({ partyId: props.partyId })
  } catch (err) {
    loadError.value = translateError(err)
    accounts.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)

watch(() => props.partyId, load)

watch(
  () => props.modelValue,
  (val) => {
    if (isDraftMode.value) {
      accounts.value = val.map(a => ({ ...a, _localId: a._localId ?? nextLocalId() }))
    }
  }
)

const emitDraftChange = () => {
  if (!isDraftMode.value) return
  emit('update:modelValue', accounts.value.map(a => ({ ...a })))
}

const startAdd = () => {
  addingNew.value = true
  editingId.value = null
}

const cancelAdd = () => {
  addingNew.value = false
}

const onNewSaved = async (payload) => {
  addingNew.value = false
  if (isDraftMode.value) {
    accounts.value.push({ ...payload, _localId: nextLocalId() })
    emitDraftChange()
  } else {
    await load()
  }
}

const startEdit = (account) => {
  editingId.value = account._localId ?? account.id
  addingNew.value = false
}

const cancelEdit = () => {
  editingId.value = null
}

const onEditSaved = async (index, payload) => {
  editingId.value = null
  if (isDraftMode.value) {
    const localId = accounts.value[index]?._localId
    accounts.value.splice(index, 1, { ...payload, _localId: localId })
    emitDraftChange()
  } else {
    await load()
  }
}

const removeAccount = async (account, index) => {
  if (isDraftMode.value) {
    if (!confirm(t('banking.removeFromList'))) return
    accounts.value.splice(index, 1)
    emitDraftChange()
    return
  }
  if (!confirm(t('banking.deleteAccountTitle', { accountNumber: account.account_number }))) return
  try {
    await deleteBankAccount({ id: account.id })
    await load()
  } catch (err) {
    alert(translateError(err))
  }
}
</script>

<style scoped>
.bank-accounts-section { margin-top: 8px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
.section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.section-head h3 { font-size: 13px; font-weight: 600; color: #111; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; }

.btn-add { display: inline-flex; align-items: center; gap: 4px; padding: 6px 10px; background: #f3f4f6; color: #111; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; }
.btn-add:hover { background: #e9eaec; }

.section-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 8px 12px; border-radius: 8px; font-size: 12px; margin: 0 0 10px; }
.section-loading { font-size: 13px; color: #6b7280; padding: 8px 0; }

.account-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.account-row { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; display: flex; align-items: flex-start; gap: 10px; }
.account-row.editing { display: block; padding: 14px; background: #fff; }
.inline-form { width: 100%; }

.row-summary { flex: 1; min-width: 0; }
.row-header { display: flex; flex-wrap: wrap; align-items: baseline; gap: 10px; padding-bottom: 8px; margin-bottom: 8px; border-bottom: 1px dashed #e5e7eb; }
.bank-name { font-weight: 700; color: #111; font-size: 15px; }
.account-number { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; color: #374151; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 2px 8px; }

.row-details { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; font-size: 13px; }
.detail-item { display: contents; }
.detail-item dt { color: #9ca3af; font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; align-self: center; }
.detail-item dd { margin: 0; color: #374151; word-break: break-word; }

.row-actions { display: flex; gap: 4px; flex-shrink: 0; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

.empty { font-size: 13px; color: #9ca3af; padding: 6px 0; margin: 0; }
</style>
