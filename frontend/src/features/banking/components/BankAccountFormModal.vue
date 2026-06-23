<template>
  <div class="modal-overlay" @click.self="emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ isEdit ? $t('banking.editAccount') : $t('banking.newAccount') }}</h2>
        <button class="close-btn" @click="emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div v-if="!isEdit" class="owner-section">
          <h3 class="section-title">{{ $t('banking.owner') }}</h3>
          <div class="form-row">
            <div class="form-group">
              <label>{{ $t('banking.type') }} <span class="required">*</span></label>
              <SearchableSelect
                v-model="partyType"
                :options="typeOptions"
                :allow-all="false"
                size="large"
                :placeholder="$t('banking.selectType')"
                @change="onTypeChange"
              />
            </div>
            <div class="form-group">
              <label>{{ $t('banking.name') }} <span class="required">*</span></label>
              <SearchableSelect
                v-model="ownerId"
                :options="ownerOptions"
                :allow-all="false"
                size="large"
                :placeholder="partyType ? $t('banking.selectName') : $t('banking.selectTypeFirst')"
              />
            </div>
          </div>
          <p v-if="loadingOwners" class="hint">{{ $t('common.loading') }}</p>
          <p v-else-if="noOwnersInStore" class="hint">
            {{ $t('banking.noOwnersInStore', { type: $t('banking.ownerTypeOption.' + partyType.toUpperCase()) }) }}
          </p>
        </div>

        <h3 v-if="!isEdit" class="section-title">{{ $t('banking.accountDetails') }}</h3>
        <BankAccountForm
          :party-id="resolvedPartyId"
          :account="account"
          :default-holder-name="defaultHolderName"
          :owner-required="!isEdit"
          @saved="onSaved"
          @cancel="emit('close')"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject, watch } from 'vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import BankAccountForm from '@/features/banking/components/BankAccountForm.vue'
import { fetchCustomers } from '@/features/customers/services/customerService'
import { fetchSuppliers } from '@/features/suppliers/services/supplierService'
import { t } from '@/i18n'

const props = defineProps({
  account: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const isEdit = computed(() => !!props.account)

const typeOptions = computed(() => [
  { value: 'business', label: t('banking.ownerTypeOption.BUSINESS') },
  { value: 'customer', label: t('banking.ownerTypeOption.CUSTOMER') },
  { value: 'supplier', label: t('banking.ownerTypeOption.SUPPLIER') },
])

const partyType = ref('')
const ownerId = ref('')
const loadingOwners = ref(false)

const customers = ref([])
const suppliers = ref([])

const onTypeChange = async (newType) => {
  ownerId.value = ''
  if (newType === 'customer' && customers.value.length === 0) {
    loadingOwners.value = true
    try {
      customers.value = await fetchCustomers({ storeId: currentStore?.value?.id, businessId: currentBusiness?.value?.id })
    } finally {
      loadingOwners.value = false
    }
  } else if (newType === 'supplier' && suppliers.value.length === 0) {
    loadingOwners.value = true
    try {
      suppliers.value = await fetchSuppliers({ storeId: currentStore?.value?.id, businessId: currentBusiness?.value?.id })
    } finally {
      loadingOwners.value = false
    }
  }
}

const currentStoreId = computed(() => (currentStore?.value?.id != null ? String(currentStore.value.id) : null))

// A customer/supplier is selectable here only if it belongs to the current store
// (created there, or has an invoice there). Bank accounts are managed per store,
// so unlike invoices you can't attach one to another store's party.
const belongsToCurrentStore = (record) => {
  const sid = currentStoreId.value
  if (!sid) return false
  return (record.stores || []).some((s) => String(s.id) === sid)
}

const ownerOptions = computed(() => {
  if (partyType.value === 'business') {
    const b = currentBusiness?.value
    if (!b?.party_id) return []
    return [{ value: `business:${b.party_id}`, label: b.name }]
  }
  if (partyType.value === 'customer') {
    return customers.value
      .filter(c => c.party?.id && belongsToCurrentStore(c))
      .map(c => ({ value: `customer:${c.party.id}`, label: c.name, sublabel: c.phone || '' }))
  }
  if (partyType.value === 'supplier') {
    return suppliers.value
      .filter(s => s.party?.id && belongsToCurrentStore(s))
      .map(s => ({ value: `supplier:${s.party.id}`, label: s.name }))
  }
  return []
})

// Distinguish "still loading" from "none in this store" so the empty dropdown
// isn't confusing under the new store-scoping.
const noOwnersInStore = computed(() =>
  !loadingOwners.value
  && (partyType.value === 'customer' || partyType.value === 'supplier')
  && ownerOptions.value.length === 0,
)

const resolvedPartyId = computed(() => {
  if (isEdit.value) return props.account?.party_id ? String(props.account.party_id) : ''
  if (!ownerId.value) return ''
  const [, id] = ownerId.value.split(':')
  return id
})

const defaultHolderName = computed(() => {
  if (isEdit.value) return ''
  if (!ownerId.value) return ''
  const [type] = ownerId.value.split(':')
  if (type === 'business') return currentBusiness?.value?.name || ''
  if (type === 'customer') {
    const c = customers.value.find(c => String(c.party?.id) === resolvedPartyId.value)
    return c?.name || ''
  }
  if (type === 'supplier') {
    const s = suppliers.value.find(s => String(s.party?.id) === resolvedPartyId.value)
    return s?.name || ''
  }
  return ''
})

const onSaved = (result) => {
  emit('saved', result)
}

watch(() => props.account, () => {
  if (props.account) {
    partyType.value = ''
    ownerId.value = ''
  }
})
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 640px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: visible; max-height: 90vh; display: flex; flex-direction: column; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; overflow-y: auto; }

.owner-section { padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1px solid #f3f4f6; }
.section-title { font-size: 13px; font-weight: 600; color: #111; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 0.05em; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.required { color: #dc2626; }
.hint { margin: 4px 0 0; font-size: 12px; color: #6b7280; }
</style>
