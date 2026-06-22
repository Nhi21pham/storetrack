<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ $t('tags.detailsTitle') }}</h2>
        <button class="close-btn" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="detail-row">
          <span class="detail-label">{{ $t('tags.key') }}</span>
          <span class="detail-value name-text">{{ tag.name }}</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('tags.values') }}</span>
          <span class="detail-value">
            <span v-if="tag.values && tag.values.length" class="values-list">
              <TagChip v-for="v in tag.values" :key="v.id" :tag-name="tag.name" :value="v.value" />
            </span>
            <span v-else class="empty-val">{{ $t('tags.keyOnlyNoValues') }}</span>
          </span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('common.description') }}</span>
          <span v-if="tag.description" class="detail-value">{{ tag.description }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('common.createdAt') }}</span>
          <span v-if="tag.created_at" class="detail-value">{{ formatDateTime(tag.created_at) }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('common.updatedAt') }}</span>
          <span v-if="tag.updated_at" class="detail-value">{{ formatDateTime(tag.updated_at) }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-close" @click="$emit('close')">{{ $t('common.close') }}</button>
        <button class="btn-edit" @click="$emit('edit', tag)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        {{ $t('common.edit') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatDateTime } from '@/utils/datetime'
import TagChip from '@/components/common/TagChip.vue'

defineProps({
  tag: { type: Object, required: true },
})

defineEmits(['close', 'edit'])
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 540px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: hidden; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; }

.detail-row { display: grid; grid-template-columns: 150px 1fr; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f3f4f6; align-items: baseline; }
.detail-row:last-child { border-bottom: none; }

.detail-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
.detail-value { font-size: 14px; color: #111; word-break: break-word; }

.name-text { font-weight: 700; font-size: 15px; }
.values-list { display: flex; flex-wrap: wrap; gap: 6px; }
.empty-val { color: #9ca3af; }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }

.btn-close { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-close:hover { background: #e9eaec; }

.btn-edit { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 6px; }
.btn-edit:hover { background: #333; }
</style>
