<template>
  <div class="modal-overlay" @click.self="handleClickOutside">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ isEdit ? $t('productCategories.editCategory') : $t('productCategories.newCategory') }}</h2>
        <button class="close-btn" @click="handleClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label>{{ $t('productCategories.code') }} <span class="required">*</span></label>
          <input
            v-model="form.code"
            type="text"
            :placeholder="$t('productCategories.codePlaceholder')"
            maxlength="10"
            :disabled="isEdit"
            :class="{ error: errors.code }"
            @input="form.code = form.code.toUpperCase()"
          />
          <span v-if="errors.code" class="error-text">{{ errors.code }}</span>
          <p v-else class="hint">{{ $t('productCategories.codeHint') }}</p>
        </div>

        <div class="form-group" :class="{ 'has-suggestions': showSuggestions }">
          <label>{{ $t('common.name') }} <span class="required">*</span></label>
          <input
            v-model="form.name"
            type="text"
            :placeholder="$t('productCategories.namePlaceholder')"
            maxlength="100"
            :class="{ error: errors.name }"
            @focus="active = true"
            @blur="onBlurField"
          />
          <span v-if="errors.name" class="error-text">{{ errors.name }}</span>
          <SuggestionList
            v-if="showSuggestions"
            :items="suggestions"
            @pick="onPickSuggestion"
          />
        </div>

        <div class="form-group">
          <label>{{ $t('common.description') }}</label>
          <textarea
            v-model="form.description"
            rows="3"
            maxlength="500"
            :placeholder="$t('tags.descriptionPlaceholder')"
            :class="{ error: errors.description }"
          />
          <span v-if="errors.description" class="error-text">{{ errors.description }}</span>
        </div>

        <div v-if="isEdit" class="form-group toggle-group">
          <div class="toggle-row">
            <ToggleSwitch v-model="form.is_active" />
            <span class="toggle-text">{{ $t('productCategories.activeToggle') }}</span>
          </div>
          <p class="hint">{{ $t('productCategories.inactiveHint') }}</p>
        </div>

        <div v-if="apiError" class="api-error">{{ apiError }}</div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="handleClose" :disabled="loading">{{ $t('common.cancel') }}</button>
        <button class="btn-submit" @click="handleSubmit" :disabled="loading || !isDirty">
          <span v-if="loading" class="spinner"></span>
          {{ isEdit ? $t('common.saveChanges') : $t('productCategories.createCategory') }}
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
import { ref, computed, watch } from 'vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import ToggleSwitch from '@/components/common/ToggleSwitch.vue'
import SuggestionList from '@/features/productCategories/components/ProductCategorySuggestionList.vue'
import {
  createProductCategory,
  updateProductCategory,
  searchProductCategories,
} from '@/features/productCategories/services/productCategoryService'
import { normalizeText } from '@/utils/textNormalizer'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

const props = defineProps({
  category: { type: Object, default: null },
  storeId:  { type: [String, Number], default: null },
  // Seed the name when creating from the product-import inline-create flow.
  prefillName: { type: String, default: '' },
})

const emit = defineEmits(['close', 'saved', 'pick-existing'])

const isEdit = computed(() => !!props.category)
const loading = ref(false)
const apiError = ref('')
const showUnsavedWarning = ref(false)

const errors = ref({ code: '', name: '', description: '' })

const initialForm = () => ({
  code:        props.category?.code || '',
  name:        props.category?.name || props.prefillName || '',
  description: props.category?.description || '',
  is_active:   props.category?.is_active ?? true,
})

const form = ref(initialForm())
const originalForm = ref(JSON.stringify(initialForm()))
const isDirty = computed(() => JSON.stringify(form.value) !== originalForm.value)

const active = ref(false)
const suggestions = ref([])
const showSuggestions = computed(() => active.value && suggestions.value.length > 0)

let searchTimer = null

const runSearch = async (value) => {
  const q = value.trim()
  if (q.length < 1 || !props.storeId) {
    suggestions.value = []
    return
  }
  try {
    const results = await searchProductCategories({ storeId: props.storeId, q, includeInactive: true, limit: 8 })
    const currentId = props.category?.id ? String(props.category.id) : null
    suggestions.value = (results || []).filter(r => String(r.id) !== currentId)
  } catch (e) {
    suggestions.value = []
  }
}

watch(
  () => [form.value.name, active.value],
  () => {
    if (searchTimer) clearTimeout(searchTimer)
    if (!active.value) {
      suggestions.value = []
      return
    }
    searchTimer = setTimeout(() => runSearch(form.value.name), 250)
  }
)

watch(() => props.category, () => {
  form.value = initialForm()
  originalForm.value = JSON.stringify(initialForm())
  errors.value = { code: '', name: '', description: '' }
  apiError.value = ''
  suggestions.value = []
})

const onBlurField = () => {
  setTimeout(() => {
    active.value = false
    suggestions.value = []
  }, 150)
}

const onPickSuggestion = (category) => {
  suggestions.value = []
  emit('pick-existing', category)
}

const validateForm = () => {
  errors.value = { code: '', name: '', description: '' }
  const code = form.value.code.trim()
  const name = form.value.name.trim()

  if (!isEdit.value) {
    if (!code) errors.value.code = t('productCategories.codeRequired')
    else if (!/^[A-Z]{2,10}$/.test(code)) errors.value.code = t('productCategories.codeFormat')
  }

  if (!name) errors.value.name = t('productCategories.nameRequired')
  else if (name.length > 100) errors.value.name = t('productCategories.nameTooLong')

  if ((form.value.description || '').length > 500) {
    errors.value.description = t('productCategories.descTooLong')
  }

  if (!isEdit.value) {
    const norm = normalizeText(name)
    const dup = suggestions.value.find(s => normalizeText(s.name) === norm || s.code === code)
    if (dup) {
      const key = dup.code === code ? 'productCategories.duplicateByCode' : 'productCategories.duplicateByName'
      apiError.value = t(key, { code: dup.code, name: dup.name })
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
        name:        form.value.name,
        description: form.value.description || null,
        is_active:   form.value.is_active,
      }
      const result = await updateProductCategory({ id: props.category.id, input })
      emit('saved', result)
    } else {
      const input = {
        code:        form.value.code,
        name:        form.value.name,
        description: form.value.description || null,
      }
      const result = await createProductCategory({ storeId: props.storeId, input })
      emit('saved', result)
    }
  } catch (err) {
    apiError.value = translateError(err)
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
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 520px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: visible; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; }

.form-group { margin-bottom: 16px; position: relative; }
.form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.required { color: #dc2626; }

.form-group input[type="text"], .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; background: #fff; transition: border-color 0.15s; outline: none; box-sizing: border-box; font-family: inherit; resize: vertical; }
.form-group input[type="text"]:focus, .form-group textarea:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.form-group input[type="text"].error, .form-group textarea.error { border-color: #dc2626; }
.form-group input[type="text"]:disabled { background: #f9fafb; color: #6b7280; cursor: not-allowed; }

.error-text { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }

.toggle-group { display: flex; flex-direction: column; gap: 6px; }
.toggle-row { display: flex; align-items: center; gap: 10px; }
.toggle-text { font-size: 14px; font-weight: 500; color: #111; }
.hint { margin: 4px 0 0; font-size: 12px; color: #6b7280; }

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
