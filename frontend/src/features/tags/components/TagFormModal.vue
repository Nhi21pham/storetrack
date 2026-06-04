<template>
  <div class="modal-overlay" @click.self="handleClose">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ isEdit ? 'Edit Tag' : 'New Tag' }}</h2>
        <button class="close-btn" @click="handleClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label>Key <span class="required">*</span></label>
          <div class="key-field">
            <input
              v-model="form.name"
              type="text"
              placeholder="e.g. Color"
              maxlength="100"
              autocomplete="off"
              :class="{ error: errors.name }"
              @focus="onKeyFocus"
              @blur="onKeyBlur"
            />
            <ul v-if="showSuggestions && suggestions.length" class="suggestions">
              <li v-for="t in suggestions" :key="t.id" @mousedown.prevent="selectSuggestion(t)">
                <span class="sugg-name">{{ t.name }}</span>
                <span class="sugg-count">{{ t.values.length }} value{{ t.values.length === 1 ? '' : 's' }}</span>
              </li>
            </ul>
          </div>
          <span v-if="errors.name" class="error-text">{{ errors.name }}</span>
          <p v-else-if="isExisting && !isEdit" class="hint existing-hint">Existing tag — values you add below will be added to it.</p>
          <p v-else class="hint">The label of the tag (e.g. Color, Size).</p>
        </div>

        <div class="form-group">
          <label>Description</label>
          <textarea
            v-model="form.description"
            rows="3"
            maxlength="500"
            placeholder="Optional"
            :class="{ error: errors.description }"
          />
          <span v-if="errors.description" class="error-text">{{ errors.description }}</span>
        </div>

        <div v-if="!isEdit" class="form-group">
          <label>Values</label>

          <div v-if="isExisting && existingValues.length" class="existing-values">
            <span class="existing-values-label">Current:</span>
            <TagChip
              v-for="ev in existingValues"
              :key="ev.id"
              :tag-name="existingMatch.name"
              :value="ev.value"
            />
          </div>

          <div class="value-input-row">
            <input
              v-model="valueInput"
              type="text"
              placeholder="Type a value, press Enter"
              maxlength="100"
              :class="{ error: valueError }"
              @keydown="handleValueKeydown"
            />
            <button type="button" class="btn-add-value" @click="addValue" :disabled="!valueInput.trim()">Add</button>
          </div>
          <span v-if="valueError" class="error-text">{{ valueError }}</span>
          <p v-else class="hint">Optional. Add one or more values (e.g. Red, Blue).</p>

          <div v-if="pendingValues.length" class="value-chips">
            <span v-for="(val, i) in pendingValues" :key="i" class="pending-chip">
              {{ val }}
              <ChipRemoveButton title="Remove" @click="removeValue(i)" />
            </span>
          </div>
        </div>

        <div v-if="apiError" class="api-error">{{ apiError }}</div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="handleClose" :disabled="loading">Cancel</button>
        <button class="btn-submit" @click="handleSubmit" :disabled="loading || !canSubmit">
          <span v-if="loading" class="spinner"></span>
          {{ submitLabel }}
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
import { ref, computed, watch, onMounted } from 'vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import TagChip from '@/components/common/TagChip.vue'
import ChipRemoveButton from '@/components/common/ChipRemoveButton.vue'
import { fetchTags, createTag, updateTag, addTagValues } from '@/features/tags/services/tagService'
import { normalizeText } from '@/utils/textNormalizer'

const props = defineProps({
  tag:     { type: Object, default: null },
  storeId: { type: [String, Number], default: null },
})

const emit = defineEmits(['close', 'saved'])

const isEdit = computed(() => !!props.tag)
const loading = ref(false)
const apiError = ref('')
const showUnsavedWarning = ref(false)

const errors = ref({ name: '', description: '' })

const form = ref({
  name:        props.tag?.name || '',
  description: props.tag?.description || '',
})
const originalForm = ref(JSON.stringify({ name: form.value.name, description: form.value.description }))

const pendingValues = ref([])
const valueInput = ref('')
const valueError = ref('')

const allTags = ref([])
const showSuggestions = ref(false)

const suggestions = computed(() => {
  const needle = normalizeText(form.value.name)
  const list = needle
    ? allTags.value.filter(t => normalizeText(t.name).includes(needle))
    : allTags.value
  return list.slice(0, 8)
})

const existingMatch = computed(() => {
  const needle = normalizeText(form.value.name)
  if (!needle) return null
  return allTags.value.find(t => normalizeText(t.name) === needle) || null
})
const isExisting = computed(() => !isEdit.value && !!existingMatch.value)
const existingValues = computed(() => existingMatch.value?.values || [])

const isDirty = computed(() => {
  const current = JSON.stringify({ name: form.value.name, description: form.value.description })
  return current !== originalForm.value || pendingValues.value.length > 0
})

const canSubmit = computed(() => {
  const name = form.value.name.trim()
  if (!name) return false
  if (isEdit.value) return isDirty.value
  if (isExisting.value) {
    const descChanged = (form.value.description || '') !== (existingMatch.value.description || '')
    return pendingValues.value.length > 0 || descChanged
  }
  return true
})

const submitLabel = computed(() => {
  if (isEdit.value) return 'Save Changes'
  if (isExisting.value) return 'Add to Tag'
  return 'Create Tag'
})

watch(() => props.tag, () => {
  form.value = { name: props.tag?.name || '', description: props.tag?.description || '' }
  originalForm.value = JSON.stringify({ name: form.value.name, description: form.value.description })
  pendingValues.value = []
  valueInput.value = ''
  errors.value = { name: '', description: '' }
  valueError.value = ''
  apiError.value = ''
})

watch(() => form.value.name, () => {
  if (!isEdit.value) showSuggestions.value = true
})

onMounted(async () => {
  if (isEdit.value || !props.storeId) return
  try {
    allTags.value = await fetchTags({ storeId: props.storeId })
  } catch {
    allTags.value = []
  }
})

const onKeyFocus = () => {
  if (!isEdit.value) showSuggestions.value = true
}

const onKeyBlur = () => {
  setTimeout(() => { showSuggestions.value = false }, 150)
}

const selectSuggestion = (tag) => {
  form.value.name = tag.name
  form.value.description = tag.description || ''
  showSuggestions.value = false
}

const addValue = () => {
  valueError.value = ''
  const value = valueInput.value.trim()
  if (!value) return
  if (value.length > 100) {
    valueError.value = 'Value must be at most 100 characters.'
    return
  }
  const norm = normalizeText(value)
  if (existingValues.value.some(ev => normalizeText(ev.value) === norm)) {
    valueError.value = 'That value already exists on this tag.'
    return
  }
  if (pendingValues.value.some(pv => normalizeText(pv) === norm)) {
    valueError.value = 'That value is already in the list.'
    valueInput.value = ''
    return
  }
  pendingValues.value.push(value)
  valueInput.value = ''
}

const removeValue = (index) => {
  pendingValues.value.splice(index, 1)
}

const handleValueKeydown = (e) => {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault()
    addValue()
  }
}

const validateForm = () => {
  errors.value = { name: '', description: '' }
  const name = form.value.name.trim()

  if (!name) errors.value.name = 'Key is required.'
  else if (name.length > 100) errors.value.name = 'Key must be at most 100 characters.'

  if ((form.value.description || '').length > 500) {
    errors.value.description = 'Description must be at most 500 characters.'
  }

  return !Object.values(errors.value).some(e => e !== '')
}

const handleSubmit = async () => {
  apiError.value = ''
  if (!validateForm()) return

  loading.value = true
  try {
    let result

    if (isEdit.value) {
      result = await updateTag({
        id: props.tag.id,
        input: {
          name:        form.value.name.trim(),
          description: form.value.description || null,
        },
      })
    } else if (isExisting.value) {
      const tag = existingMatch.value
      let updated = tag
      const descChanged = (form.value.description || '') !== (tag.description || '')
      if (descChanged) {
        updated = await updateTag({ id: tag.id, input: { description: form.value.description || null } })
      }
      if (pendingValues.value.length) {
        updated = await addTagValues({ tagId: tag.id, values: pendingValues.value })
      }
      result = updated
    } else {
      const input = {
        name:        form.value.name.trim(),
        description: form.value.description || null,
      }
      if (pendingValues.value.length) input.values = pendingValues.value
      result = await createTag({ storeId: props.storeId, input })
    }

    emit('saved', result)
  } catch (err) {
    apiError.value = err.message
  } finally {
    loading.value = false
  }
}

const handleClose = () => {
  if (isDirty.value) showUnsavedWarning.value = true
  else emit('close')
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 520px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); }

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

.error-text { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }
.hint { margin: 4px 0 0; font-size: 12px; color: #6b7280; }
.existing-hint { color: #4338ca; font-weight: 500; }

.key-field { position: relative; }
.suggestions { position: absolute; top: calc(100% + 4px); left: 0; right: 0; margin: 0; padding: 4px; list-style: none; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 12px 32px rgba(0,0,0,0.12); z-index: 20; max-height: 220px; overflow-y: auto; }
.suggestions li { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 10px; border-radius: 6px; cursor: pointer; }
.suggestions li:hover { background: #f3f4f6; }
.sugg-name { font-size: 14px; font-weight: 600; color: #111; }
.sugg-count { font-size: 12px; color: #9ca3af; white-space: nowrap; }

.existing-values { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-bottom: 10px; }
.existing-values-label { font-size: 12px; color: #6b7280; }

.value-input-row { display: flex; gap: 8px; align-items: stretch; }
.value-input-row input[type="text"] { flex: 1; }
.btn-add-value { padding: 8px 16px; background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; }
.btn-add-value:hover:not(:disabled) { background: #e0e7ff; }
.btn-add-value:disabled { opacity: 0.5; cursor: not-allowed; }

.value-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.pending-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; padding: 3px 6px 3px 9px; border-radius: 6px; background: #eef2ff; color: #4338ca; line-height: 1.5; }

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
