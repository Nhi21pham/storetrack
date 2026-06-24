<template>
  <div class="modal-overlay" @click.self="handleClickOutside">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ isEdit ? $t('suppliers.editSupplier') : $t('suppliers.newSupplier') }}</h2>
        <button class="close-btn" @click="handleClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label>{{ $t('suppliers.supplierName') }} <span class="required">*</span></label>
          <input v-model="form.name" type="text" :placeholder="$t('suppliers.namePlaceholder')" :class="{ error: errors.name }" />
          <span v-if="errors.name" class="error-text">{{ errors.name }}</span>
        </div>

        <div class="form-group">
          <label>{{ $t('suppliers.taxCode') }}</label>
          <input v-model="form.tax_code" type="text" :placeholder="$t('suppliers.taxCodePlaceholder')" :class="{ error: errors.tax_code }" />
          <span v-if="errors.tax_code" class="error-text">{{ errors.tax_code }}</span>
        </div>

        <div class="form-group">
          <label>{{ $t('suppliers.address') }}</label>
          <input v-model="form.address" type="text" :placeholder="$t('suppliers.addressPlaceholder')" :class="{ error: errors.address }" />
          <span v-if="errors.address" class="error-text">{{ errors.address }}</span>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>{{ $t('suppliers.email') }}</label>
            <input v-model="form.email" type="email" :placeholder="$t('suppliers.emailPlaceholder')" :class="{ error: errors.email }" />
            <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
          </div>
          <div class="form-group">
            <label>{{ $t('suppliers.phone') }}</label>
            <input v-model="form.phone" type="tel" :placeholder="$t('suppliers.phonePlaceholder')" :class="{ error: errors.phone }" />
            <span v-if="errors.phone" class="error-text">{{ errors.phone }}</span>
          </div>
        </div>

        <div v-if="apiError" class="api-error">{{ apiError }}</div>

        <BankAccountsFormWidget
          ref="bankAccountsWidget"
          :party-id="supplier?.party?.id ?? null"
          :default-holder-name="form.name"
          :entity-label="$t('suppliers.entityLabel')"
        />
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="handleClose" :disabled="loading">{{ $t('common.cancel') }}</button>
        <button class="btn-submit" @click="handleSubmit" :disabled="loading || !isDirty">
          <span v-if="loading" class="spinner"></span>
          {{ isEdit ? $t('common.saveChanges') : $t('suppliers.createSupplier') }}
        </button>
      </div>
    </div>
  </div>

  <ConfirmDialog
    v-if="showUnsavedWarning"
    :title="$t('account.unsavedTitle')"
    :message="$t('account.unsavedMessage')"
    :confirm-text="$t('account.discard')"
    :cancel-text="$t('account.keepEditing')"
    @confirm="$emit('close')"
    @cancel="showUnsavedWarning = false"
  />
</template>

<script setup>
import { ref, computed, inject } from 'vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import BankAccountsFormWidget from '@/features/banking/components/BankAccountsFormWidget.vue'
import { validators } from '@/utils/validators'
import { translateError } from '@/utils/translateError'
import { createSupplier, updateSupplier } from '@/features/suppliers/services/supplierService'

const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const props = defineProps({
  supplier: { type: Object, default: null },
  // Seed a NEW supplier's fields (e.g. from an extracted invoice). Ignored when
  // editing — these don't make the modal think it's in edit mode.
  prefillName: { type: String, default: '' },
  prefillTaxCode: { type: String, default: '' },
  prefillPhone: { type: String, default: '' },
  prefillAddress: { type: String, default: '' },
})

const emit = defineEmits(['close', 'saved'])

const isEdit = computed(() => !!props.supplier)
const loading = ref(false)
const apiError = ref('')
const showUnsavedWarning = ref(false)

const errors = ref({ name: '', tax_code: '', email: '', phone: '', address: '' })

const initialForm = () => ({
  name: props.supplier?.name || props.prefillName || '',
  tax_code: props.supplier?.tax_code || props.prefillTaxCode || '',
  address: props.supplier?.address || props.prefillAddress || '',
  email: props.supplier?.email || '',
  phone: props.supplier?.phone || props.prefillPhone || ''
})

const form = ref(initialForm())
const originalForm = ref(JSON.stringify(initialForm()))
const bankAccountsWidget = ref(null)

const isDirty = computed(() =>
  JSON.stringify(form.value) !== originalForm.value || bankAccountsWidget.value?.hasDrafts
)

const validateForm = () => {
  errors.value = { name: '', tax_code: '', email: '', phone: '', address: '' }

  errors.value.name = validators.supplierName(form.value.name)
  errors.value.tax_code = validators.supplierTaxCode(form.value.tax_code)
  errors.value.email = validators.businessEmail(form.value.email)
  errors.value.phone = validators.businessPhone(form.value.phone)
  errors.value.address = validators.businessAddress(form.value.address)

  return !Object.values(errors.value).some(e => e !== null)
}

const handleSubmit = async () => {
  if (!validateForm()) return

  loading.value = true
  apiError.value = ''

  const input = {
    name: form.value.name,
    tax_code: form.value.tax_code || null,
    address: form.value.address || null,
    email: form.value.email || null,
    phone: form.value.phone || null,
  }
  const ctx = {
    storeId: currentStore.value?.id,
    businessId: currentBusiness.value?.id,
    input,
  }

  try {
    const result = isEdit.value
      ? await updateSupplier({ id: props.supplier.id, ...ctx })
      : await createSupplier(ctx)

    const partyId = result?.party?.id ?? props.supplier?.party?.id ?? props.supplier?.party_id
    const errorMsg = await bankAccountsWidget.value?.submitDrafts(partyId)
    if (errorMsg) {
      apiError.value = errorMsg
      emit('saved', result)
      return
    }

    emit('saved', result)
  } catch (err) {
    apiError.value = translateError(err)
  } finally {
    loading.value = false
  }
}

const handleClickOutside = () => {
  isDirty.value ? showUnsavedWarning.value = true : emit('close')
}

const handleClose = () => {
  isDirty.value ? showUnsavedWarning.value = true : emit('close')
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 520px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: visible; max-height: 90vh; display: flex; flex-direction: column; }
.modal-body { overflow-y: auto; overflow-x: visible; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.required { color: #dc2626; }

.form-group input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; background: #fff; transition: border-color 0.15s; outline: none; box-sizing: border-box; }
.form-group input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.form-group input.error { border-color: #dc2626; }

.error-text { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.api-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-top: 4px; }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }

.btn-cancel { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-cancel:hover { background: #e9eaec; }
.btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-submit { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 6px; }
.btn-submit:hover:not(:disabled) { background: #333; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
