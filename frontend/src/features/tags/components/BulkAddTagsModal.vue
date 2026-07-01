<template>
  <div class="modal-overlay" @click.self="onClose">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ $t('bulk.addTagsTitle') }}</h2>
        <button class="close-btn" @click="onClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <p class="subtitle">{{ $t('bulk.addTagsSubtitle', { count, noun }) }}</p>
        <TagPicker v-model="pairs" :store-id="storeId" />
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="onClose" :disabled="busy">{{ $t('common.cancel') }}</button>
        <button class="btn-submit" @click="onApply" :disabled="busy || !pairs.length">
          <span v-if="busy" class="spinner"></span>
          {{ $t('bulk.addTagsConfirm') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import TagPicker from '@/features/tags/components/TagPicker.vue'

const props = defineProps({
  storeId: { type: [String, Number], default: null },
  count:   { type: Number, default: 0 },
  noun:    { type: String, default: '' },
  busy:    { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'apply'])

const pairs = ref([])

const onClose = () => {
  if (!props.busy) emit('close')
}

const onApply = () => {
  if (props.busy || !pairs.value.length) return
  emit('apply', pairs.value.map(p => ({
    tag_id: p.tag_id,
    tag_value_id: p.tag_value_id != null ? p.tag_value_id : null,
  })))
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
.subtitle { font-size: 13px; color: #6b7280; margin: 0 0 14px; line-height: 1.5; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }
.btn-cancel { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover:not(:disabled) { background: #e9eaec; }
.btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-submit { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-submit:hover:not(:disabled) { background: #333; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
