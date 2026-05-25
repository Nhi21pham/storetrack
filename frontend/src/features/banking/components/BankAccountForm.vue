<template>
  <div class="bank-account-form">
    <div class="form-group">
      <label>Bank <span class="required">*</span></label>
      <SearchableSelect
        v-model="form.bank_id"
        :options="bankOptions"
        :allow-all="false"
        placeholder="Select a bank..."
        search-placeholder="Search banks..."
      />
      <span v-if="errors.bank_id" class="error-text">{{ errors.bank_id }}</span>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Account Number <span class="required">*</span></label>
        <input
          v-model.trim="form.account_number"
          type="text"
          placeholder="e.g. 0123456789"
          :class="{ error: errors.account_number }"
        />
        <span v-if="errors.account_number" class="error-text">{{ errors.account_number }}</span>
      </div>
      <div class="form-group">
        <label>Account Holder Name</label>
        <input
          v-model="form.account_holder_name"
          type="text"
          :placeholder="defaultHolderName || 'Name registered with the bank'"
          :class="{ error: errors.account_holder_name }"
        />
        <span v-if="errors.account_holder_name" class="error-text">{{ errors.account_holder_name }}</span>
        <p v-if="defaultHolderName && !form.account_holder_name" class="hint">
          Leave empty to default to "{{ defaultHolderName }}"
        </p>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Branch</label>
        <input
          v-model="form.branch"
          type="text"
          placeholder="e.g. Chi nhánh Hà Nội"
          :class="{ error: errors.branch }"
        />
        <span v-if="errors.branch" class="error-text">{{ errors.branch }}</span>
      </div>
      <div class="form-group">
        <label>Province / City</label>
        <SearchableSelect
          v-model="form.province_id"
          :options="provinceOptions"
          allow-all
          all-label="— None —"
          placeholder="Select province..."
          search-placeholder="Search provinces..."
        />
      </div>
    </div>

    <div v-if="apiError" class="api-error">{{ apiError }}</div>

    <div class="form-actions">
      <button class="btn-cancel" type="button" @click="$emit('cancel')" :disabled="saving">Cancel</button>
      <button class="btn-submit" type="button" @click="handleSubmit" :disabled="saving || !isDirty">
        <span v-if="saving" class="spinner"></span>
        {{ isEdit ? 'Save Changes' : 'Create Bank Account' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject, onMounted, watch } from 'vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import { fetchBanks } from '@/features/banking/services/bankService'
import { fetchProvinces } from '@/features/banking/services/provinceService'
import { createBankAccount, updateBankAccount } from '@/features/banking/services/bankAccountService'

const currentBusiness = inject('currentBusiness', null)

const props = defineProps({
  partyId: { type: [String, Number], default: null },
  account: { type: Object, default: null },
  defaultHolderName: { type: String, default: '' },
  mode: { type: String, default: 'api' },
})

const emit = defineEmits(['saved', 'cancel'])

const isEdit = computed(() => !!props.account)
const saving = ref(false)
const apiError = ref('')

const banks = ref([])
const provinces = ref([])

const initialForm = () => ({
  bank_id: props.account?.bank_id != null ? String(props.account.bank_id) : '',
  province_id: props.account?.province_id != null ? String(props.account.province_id) : '',
  account_number: props.account?.account_number || '',
  account_holder_name: props.account?.account_holder_name || '',
  branch: props.account?.branch || '',
})

const form = ref(initialForm())
const originalForm = ref(JSON.stringify(initialForm()))
const isDirty = computed(() => isEdit.value ? JSON.stringify(form.value) !== originalForm.value : true)

const errors = ref({ bank_id: '', account_number: '', account_holder_name: '', branch: '' })

const bankOptions = computed(() => {
  const list = banks.value
    .filter(b => b.is_active || String(b.id) === form.value.bank_id)
    .map(b => ({ value: String(b.id), label: b.short_name }))
  return list
})

const provinceOptions = computed(() =>
  provinces.value.map(p => ({ value: String(p.id), label: p.name_vi }))
)

onMounted(async () => {
  const businessId = currentBusiness?.value?.id
  if (!businessId) {
    provinces.value = await fetchProvinces()
    return
  }
  const [bankList, provinceList] = await Promise.all([
    fetchBanks({ businessId, includeInactive: true }),
    fetchProvinces(),
  ])
  banks.value = bankList
  provinces.value = provinceList
})

watch(() => props.account, () => {
  form.value = initialForm()
  originalForm.value = JSON.stringify(initialForm())
})

const validate = () => {
  errors.value = { bank_id: '', account_number: '', account_holder_name: '', branch: '' }
  if (!form.value.bank_id) errors.value.bank_id = 'Please select a bank.'
  const acc = form.value.account_number.trim()
  if (!acc) errors.value.account_number = 'Account number is required.'
  else if (acc.length < 4) errors.value.account_number = 'Account number must be at least 4 characters.'
  else if (acc.length > 50) errors.value.account_number = 'Account number must be at most 50 characters.'
  else if (!/^[0-9A-Za-z]+$/.test(acc)) errors.value.account_number = 'Only letters and digits are allowed.'
  if (form.value.account_holder_name && form.value.account_holder_name.length > 255) errors.value.account_holder_name = 'Too long.'
  if (form.value.branch && form.value.branch.length > 255) errors.value.branch = 'Too long.'
  return !Object.values(errors.value).some(e => e !== '')
}

const handleSubmit = async () => {
  apiError.value = ''
  if (!validate()) return

  const input = {
    bank_id: form.value.bank_id,
    account_number: form.value.account_number.trim(),
  }
  if (form.value.province_id) input.province_id = form.value.province_id
  else if (isEdit.value) input.province_id = null

  const holderName = form.value.account_holder_name.trim()
    ? form.value.account_holder_name.trim()
    : (props.defaultHolderName || null)
  input.account_holder_name = holderName

  input.branch = form.value.branch.trim() || null

  if (props.mode === 'emit') {
    const bank = banks.value.find(b => String(b.id) === input.bank_id)
    const province = provinces.value.find(p => String(p.id) === input.province_id)
    emit('saved', { ...input, bank, province })
    return
  }

  saving.value = true
  try {
    const result = isEdit.value
      ? await updateBankAccount({ id: props.account.id, input })
      : await createBankAccount({ partyId: props.partyId, input })
    emit('saved', result)
  } catch (err) {
    apiError.value = err.message
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.bank-account-form { display: flex; flex-direction: column; gap: 4px; }

.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.required { color: #dc2626; }

.form-group input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; background: #fff; outline: none; box-sizing: border-box; }
.form-group input[type="text"]:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.form-group input[type="text"].error { border-color: #dc2626; }

.error-text { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }
.hint { margin: 4px 0 0; font-size: 12px; color: #6b7280; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.api-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin: 4px 0 12px; }

.form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 8px; border-top: 1px solid #f3f4f6; margin-top: 4px; }
.btn-cancel { padding: 9px 18px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover { background: #e9eaec; }
.btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-submit { padding: 9px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-submit:hover:not(:disabled) { background: #333; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
