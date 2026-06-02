<template>
  <div class="modal-overlay" @click.self="handleClose">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ isEdit ? 'Edit Value' : 'Add Value' }}</h2>
        <button class="close-btn" @click="handleClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <p class="tag-context">Tag: <strong>{{ tagName }}</strong></p>
        <div class="form-group">
          <label>Value <span class="required">*</span></label>
          <input
            v-model="form.value"
            type="text"
            placeholder="e.g. Red"
            maxlength="100"
            :class="{ error: error }"
            @keyup.enter="handleSubmit"
          />
          <span v-if="error" class="error-text">{{ error }}</span>
        </div>
        <div v-if="apiError" class="api-error">{{ apiError }}</div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="handleClose" :disabled="loading">Cancel</button>
        <button class="btn-submit" @click="handleSubmit" :disabled="loading || !isDirty">
          <span v-if="loading" class="spinner"></span>
          {{ isEdit ? 'Save Changes' : 'Add Value' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { createTagValue, updateTagValue } from '@/features/tags/services/tagService'

const props = defineProps({
  tagId:   { type: [String, Number], default: null },
  tagName: { type: String, default: '' },
  value:   { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const isEdit = computed(() => !!props.value)
const loading = ref(false)
const error = ref('')
const apiError = ref('')

const form = ref({ value: props.value?.value || '' })
const isDirty = computed(() => form.value.value.trim() !== (props.value?.value || '').trim())

const validate = () => {
  error.value = ''
  const v = form.value.value.trim()
  if (!v) error.value = 'Value is required.'
  else if (v.length > 100) error.value = 'Value must be at most 100 characters.'
  return !error.value
}

const handleSubmit = async () => {
  apiError.value = ''
  if (!validate()) return

  loading.value = true
  try {
    const input = { value: form.value.value.trim() }
    const result = isEdit.value
      ? await updateTagValue({ id: props.value.id, input })
      : await createTagValue({ tagId: props.tagId, input })
    emit('saved', result)
  } catch (err) {
    apiError.value = err.message
  } finally {
    loading.value = false
  }
}

const handleClose = () => emit('close')
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 460px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; }
.tag-context { font-size: 13px; color: #6b7280; margin-bottom: 14px; }
.tag-context strong { color: #111; }

.form-group { margin-bottom: 8px; }
.form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.required { color: #dc2626; }

.form-group input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; background: #fff; outline: none; box-sizing: border-box; }
.form-group input[type="text"]:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.form-group input[type="text"].error { border-color: #dc2626; }

.error-text { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }
.api-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-top: 8px; }

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
