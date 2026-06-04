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
          <input
            v-model="form.name"
            type="text"
            placeholder="e.g. Color"
            maxlength="100"
            :class="{ error: errors.name }"
          />
          <span v-if="errors.name" class="error-text">{{ errors.name }}</span>
          <p v-else class="hint">The label of the tag (e.g. Color, Size). Values are added afterwards.</p>
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

        <div v-if="apiError" class="api-error">{{ apiError }}</div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="handleClose" :disabled="loading">Cancel</button>
        <button class="btn-submit" @click="handleSubmit" :disabled="loading || !isDirty">
          <span v-if="loading" class="spinner"></span>
          {{ isEdit ? 'Save Changes' : 'Create Tag' }}
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
import { createTag, updateTag } from '@/features/tags/services/tagService'

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

const initialForm = () => ({
  name:        props.tag?.name || '',
  description: props.tag?.description || '',
})

const form = ref(initialForm())
const originalForm = ref(JSON.stringify(initialForm()))
const isDirty = computed(() => JSON.stringify(form.value) !== originalForm.value)

watch(() => props.tag, () => {
  form.value = initialForm()
  originalForm.value = JSON.stringify(initialForm())
  errors.value = { name: '', description: '' }
  apiError.value = ''
})

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
    const input = {
      name:        form.value.name.trim(),
      description: form.value.description || null,
    }
    const result = isEdit.value
      ? await updateTag({ id: props.tag.id, input })
      : await createTag({ storeId: props.storeId, input })
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
