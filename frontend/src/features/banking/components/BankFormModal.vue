<template>
  <div class="modal-overlay" @click.self="handleClickOutside">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ isEdit ? 'Edit Bank' : 'New Bank' }}</h2>
        <button class="close-btn" @click="handleClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group" :class="{ 'has-suggestions': showSuggestions && activeField === 'short_name' }">
          <label>Short Name <span class="required">*</span></label>
          <input
            v-model="form.short_name"
            type="text"
            placeholder="e.g. Vietcombank"
            :class="{ error: errors.short_name }"
            @focus="activeField = 'short_name'"
            @blur="onBlurField"
          />
          <span v-if="errors.short_name" class="error-text">{{ errors.short_name }}</span>
          <SuggestionList
            v-if="!isEdit && showSuggestions && activeField === 'short_name'"
            :items="suggestions"
            @pick="onPickSuggestion"
          />
        </div>

        <div class="form-group" :class="{ 'has-suggestions': showSuggestions && activeField === 'full_name_vi' }">
          <label>Full Name (Vietnamese) <span class="required">*</span></label>
          <input
            v-model="form.full_name_vi"
            type="text"
            placeholder="e.g. Ngân hàng TMCP Ngoại thương Việt Nam"
            :class="{ error: errors.full_name_vi }"
            @focus="activeField = 'full_name_vi'"
            @blur="onBlurField"
          />
          <span v-if="errors.full_name_vi" class="error-text">{{ errors.full_name_vi }}</span>
          <SuggestionList
            v-if="!isEdit && showSuggestions && activeField === 'full_name_vi'"
            :items="suggestions"
            @pick="onPickSuggestion"
          />
        </div>

        <div class="form-group" :class="{ 'has-suggestions': showSuggestions && activeField === 'full_name_en' }">
          <label>Full Name (English) <span class="required">*</span></label>
          <input
            v-model="form.full_name_en"
            type="text"
            placeholder="e.g. Joint Stock Commercial Bank for Foreign Trade of Vietnam"
            :class="{ error: errors.full_name_en }"
            @focus="activeField = 'full_name_en'"
            @blur="onBlurField"
          />
          <span v-if="errors.full_name_en" class="error-text">{{ errors.full_name_en }}</span>
          <SuggestionList
            v-if="!isEdit && showSuggestions && activeField === 'full_name_en'"
            :items="suggestions"
            @pick="onPickSuggestion"
          />
        </div>

        <div v-if="isEdit" class="form-group toggle-group">
          <label class="toggle-label">
            <input v-model="form.is_active" type="checkbox" />
            <span>Active</span>
          </label>
          <p class="hint">Inactive banks won't appear in the bank picker on new bank accounts.</p>
        </div>

        <div v-if="apiError" class="api-error">{{ apiError }}</div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="handleClose" :disabled="loading">Cancel</button>
        <button class="btn-submit" @click="handleSubmit" :disabled="loading || !isDirty">
          <span v-if="loading" class="spinner"></span>
          {{ isEdit ? 'Save Changes' : 'Create Bank' }}
        </button>
      </div>
    </div>
  </div>

  <ConfirmDialog
    v-if="showUnsavedWarning"
    title="Unsaved Changes"
    message="You have unsaved changes. Are you sure you want to discard them?"
    confirm-text="Yes, discard"
    cancel-text="Keep editing"
    @confirm="$emit('close')"
    @cancel="showUnsavedWarning = false"
  />
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import SuggestionList from '@/features/banking/components/BankSuggestionList.vue'
import { createBank, updateBank, searchBanks } from '@/features/banking/services/bankService'
import { normalizeText } from '@/utils/textNormalizer'

const props = defineProps({
  bank: { type: Object, default: null },
  businessId: { type: [String, Number], default: null },
})

const emit = defineEmits(['close', 'saved', 'pick-existing'])

const isEdit = computed(() => !!props.bank)
const loading = ref(false)
const apiError = ref('')
const showUnsavedWarning = ref(false)

const errors = ref({ short_name: '', full_name_vi: '', full_name_en: '' })

const initialForm = () => ({
  short_name: props.bank?.short_name || '',
  full_name_vi: props.bank?.full_name_vi || '',
  full_name_en: props.bank?.full_name_en || '',
  is_active: props.bank?.is_active ?? true,
})

const form = ref(initialForm())
const originalForm = ref(JSON.stringify(initialForm()))
const isDirty = computed(() => JSON.stringify(form.value) !== originalForm.value)

const activeField = ref(null)
const suggestions = ref([])
const showSuggestions = computed(() => suggestions.value.length > 0)

let searchTimer = null

const runSearch = async (value) => {
  const q = value.trim()
  if (q.length < 2 || !props.businessId) {
    suggestions.value = []
    return
  }
  try {
    const results = await searchBanks({ businessId: props.businessId, q, includeInactive: true, limit: 8 })
    suggestions.value = results || []
  } catch (e) {
    suggestions.value = []
  }
}

if (!isEdit.value) {
  watch(
    () => [form.value.short_name, form.value.full_name_vi, form.value.full_name_en, activeField.value],
    () => {
      if (searchTimer) clearTimeout(searchTimer)
      const field = activeField.value
      if (!field) {
        suggestions.value = []
        return
      }
      const value = form.value[field]
      searchTimer = setTimeout(() => runSearch(value), 250)
    }
  )
}

const onBlurField = () => {
  setTimeout(() => {
    activeField.value = null
    suggestions.value = []
  }, 150)
}

const onPickSuggestion = (bank) => {
  suggestions.value = []
  emit('pick-existing', bank)
}

const validateForm = () => {
  errors.value = { short_name: '', full_name_vi: '', full_name_en: '' }
  if (!form.value.short_name.trim()) errors.value.short_name = 'Short name is required.'
  else if (form.value.short_name.length > 50) errors.value.short_name = 'Short name must be at most 50 characters.'
  if (!form.value.full_name_vi.trim() || form.value.full_name_vi.trim().length < 2) errors.value.full_name_vi = 'Vietnamese name is required (min 2 characters).'
  else if (form.value.full_name_vi.length > 255) errors.value.full_name_vi = 'Vietnamese name must be at most 255 characters.'
  if (!form.value.full_name_en.trim() || form.value.full_name_en.trim().length < 2) errors.value.full_name_en = 'English name is required (min 2 characters).'
  else if (form.value.full_name_en.length > 255) errors.value.full_name_en = 'English name must be at most 255 characters.'

  if (!isEdit.value) {
    const shortNorm = normalizeText(form.value.short_name)
    const viNorm = normalizeText(form.value.full_name_vi)
    const enNorm = normalizeText(form.value.full_name_en)
    const dup = suggestions.value.find(s =>
      normalizeText(s.short_name) === shortNorm ||
      normalizeText(s.full_name_vi) === viNorm ||
      normalizeText(s.full_name_en) === enNorm
    )
    if (dup) {
      apiError.value = `A bank with this name already exists: ${dup.short_name}. Open it from the suggestion list above to edit.`
      return false
    }
  }

  return !Object.values(errors.value).some(e => e !== '')
}

const handleSubmit = async () => {
  apiError.value = ''
  if (!validateForm()) return

  loading.value = true
  try {
    if (isEdit.value) {
      const input = {
        short_name: form.value.short_name,
        full_name_vi: form.value.full_name_vi,
        full_name_en: form.value.full_name_en,
        is_active: form.value.is_active,
      }
      const result = await updateBank({ id: props.bank.id, input })
      emit('saved', result)
    } else {
      const input = {
        short_name: form.value.short_name,
        full_name_vi: form.value.full_name_vi,
        full_name_en: form.value.full_name_en,
      }
      const result = await createBank({ businessId: props.businessId, input })
      emit('saved', result)
    }
  } catch (err) {
    apiError.value = err.message
  } finally {
    loading.value = false
  }
}

const handleClickOutside = () => {
  if (isDirty.value) showUnsavedWarning.value = true
  else emit('close')
}

const handleClose = () => {
  if (isDirty.value) showUnsavedWarning.value = true
  else emit('close')
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 560px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: visible; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; }

.form-group { margin-bottom: 16px; position: relative; }
.form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.required { color: #dc2626; }

.form-group input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; background: #fff; transition: border-color 0.15s; outline: none; box-sizing: border-box; }
.form-group input[type="text"]:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.form-group input[type="text"].error { border-color: #dc2626; }

.error-text { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }

.toggle-group { display: flex; flex-direction: column; gap: 4px; }
.toggle-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; font-weight: 500; color: #111; }
.hint { margin: 0; font-size: 12px; color: #6b7280; }

.api-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-top: 4px; }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }

.btn-cancel { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover { background: #e9eaec; }
.btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-submit { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-submit:hover:not(:disabled) { background: #333; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
