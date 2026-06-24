<template>
  <div class="modal-overlay" @click.self="handleClickOutside">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ isEdit ? $t('stores.editStore') : $t('stores.newStore') }}</h2>
        <button class="close-btn" @click="handleClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <!-- Business selector (create mode only) -->
        <div v-if="!isEdit" class="form-group">
          <label>{{ $t('stores.business') }} <span class="required">*</span></label>
          <SelectField v-model="form.business_id" :error="!!errors.business_id">
            <option value="">{{ $t('stores.selectBusiness') }}</option>
            <option v-for="biz in ownedBusinesses" :key="biz.id" :value="biz.id">
              {{ biz.name }}
            </option>
          </SelectField>
          <span v-if="errors.business_id" class="error-text">{{ errors.business_id }}</span>
        </div>

        <div v-if="isEdit" class="business-badge">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
          {{ store?.business?.name }}
        </div>

        <div class="form-group">
          <label>{{ $t('stores.storeName') }} <span class="required">*</span></label>
          <input v-model="form.name" type="text" :placeholder="$t('stores.namePlaceholder')" :class="{ error: errors.name }" />
          <span v-if="errors.name" class="error-text">{{ errors.name }}</span>
        </div>

        <div class="form-group">
          <label>{{ $t('stores.address') }}</label>
          <input v-model="form.address" type="text" :placeholder="$t('stores.addressPlaceholder')" :class="{ error: errors.address }" />
          <span v-if="errors.address" class="error-text">{{ errors.address }}</span>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>{{ $t('stores.email') }}</label>
            <input v-model="form.email" type="email" :placeholder="$t('stores.emailPlaceholder')" :class="{ error: errors.email }" />
            <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
          </div>
          <div class="form-group">
            <label>{{ $t('stores.phone') }}</label>
            <input v-model="form.phone" type="tel" :placeholder="$t('stores.phonePlaceholder')" :class="{ error: errors.phone }" />
            <span v-if="errors.phone" class="error-text">{{ errors.phone }}</span>
          </div>
        </div>

        <div v-if="apiError" class="api-error">{{ apiError }}</div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="handleClose" :disabled="loading">{{ $t('common.cancel') }}</button>
        <button class="btn-submit" @click="handleSubmit" :disabled="loading || !isDirty">
          <span v-if="loading" class="spinner"></span>
          {{ isEdit ? $t('common.saveChanges') : $t('stores.createStore') }}
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
import { ref, computed, onMounted } from 'vue'
import { validators } from '@/utils/validators'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import SelectField from '@/components/common/SelectField.vue'
import {
  fetchMyBusinesses,
  createStore,
  updateStore,
} from '@/features/stores/services/storeService'

const props = defineProps({
  store: { type: Object, default: null }
})

const emit = defineEmits(['close', 'saved'])

const isEdit = computed(() => !!props.store)
const loading = ref(false)
const apiError = ref('')
const showUnsavedWarning = ref(false)
const ownedBusinesses = ref([])

const errors = ref({ business_id: '', name: '', address: '', email: '', phone: '' })

const initialForm = () => ({
  business_id: props.store?.business?.id || '',
  name: props.store?.name || '',
  address: props.store?.address || '',
  email: props.store?.email || '',
  phone: props.store?.phone || ''
})

const form = ref(initialForm())
const originalForm = ref(JSON.stringify(initialForm()))

const isDirty = computed(() => JSON.stringify(form.value) !== originalForm.value)

const fetchOwnedBusinesses = async () => {
  try {
    ownedBusinesses.value = await fetchMyBusinesses()
  } catch (err) {
    apiError.value = translateError(err)
  }
}

const validateForm = () => {
  errors.value = { business_id: '', name: '', address: '', email: '', phone: '' }

  if (!isEdit.value && !form.value.business_id) {
    errors.value.business_id = t('stores.selectBusinessError')
  }

  errors.value.name = validators.storeName(form.value.name)
  errors.value.address = validators.businessAddress(form.value.address)
  errors.value.email = validators.businessEmail(form.value.email)
  errors.value.phone = validators.businessPhone(form.value.phone)

  return !Object.values(errors.value).some(e => e !== null && e !== '')
}

const handleSubmit = async () => {
  if (!validateForm()) return

  loading.value = true
  apiError.value = ''

  const baseInput = {
    name: form.value.name,
    address: form.value.address || null,
    email: form.value.email || null,
    phone: form.value.phone || null,
  }

  try {
    const result = isEdit.value
      ? await updateStore(props.store.id, baseInput)
      : await createStore({ business_id: form.value.business_id, ...baseInput })
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

onMounted(() => {
  if (!isEdit.value) {
    fetchOwnedBusinesses()
  }
})
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 520px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: hidden; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; }

.business-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: #374151; background: #f3f4f6; padding: 6px 12px; border-radius: 8px; margin-bottom: 16px; }

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