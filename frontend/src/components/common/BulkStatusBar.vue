<template>
  <div class="selection-bar">
    <span class="selection-count">{{ $t('shared.selectedCount', { count }) }}</span>
    <div class="selection-actions">
      <button class="btn-selection-action" @click="$emit('clear')">{{ $t('common.clear') }}</button>
      <ExportButton v-if="showExport" variant="ghost" :label="$t('shared.exportSelected')" :exporting="exporting" @click="$emit('export')" />
      <button v-if="showTags" class="btn-selection-action tags" :disabled="busy" @click="$emit('tags')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        {{ $t('bulk.addTags') }}
      </button>
      <button v-if="showRemoveTags" class="btn-selection-action untag" :disabled="busy" @click="$emit('remove-tags')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        {{ $t('bulk.removeTags') }}
      </button>
      <button v-if="showStatus" class="btn-selection-action activate" :disabled="busy" @click="$emit('activate')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        {{ $t('common.activate') }}
      </button>
      <button v-if="showStatus" class="btn-selection-action warn" :disabled="busy" @click="$emit('deactivate')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        {{ $t('common.deactivate') }}
      </button>
      <button v-if="canDelete" class="btn-selection-action danger" :disabled="busy" @click="$emit('delete')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        {{ $t('common.delete') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import ExportButton from '@/components/common/ExportButton.vue'

defineProps({
  count:      { type: Number,  required: true },
  busy:       { type: Boolean, default: false },
  canDelete:  { type: Boolean, default: false },
  showStatus: { type: Boolean, default: true },
  showExport: { type: Boolean, default: false },
  showTags:   { type: Boolean, default: false },
  showRemoveTags: { type: Boolean, default: false },
  exporting:  { type: Boolean, default: false },
})

defineEmits(['clear', 'activate', 'deactivate', 'delete', 'export', 'tags', 'remove-tags'])
</script>

<style scoped>
.selection-bar { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; margin-bottom: 12px; background: #111; color: #fff; border-radius: 10px; font-size: 13px; }
.selection-count { font-weight: 600; }
.selection-actions { display: flex; gap: 8px; }
.btn-selection-action { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border: 1px solid rgba(255,255,255,0.2); border-radius: 7px; font-size: 12.5px; font-weight: 500; color: #fff; background: transparent; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.btn-selection-action:hover:not(:disabled) { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.4); }
.btn-selection-action.tags:hover:not(:disabled) { background: #4338ca; border-color: #4338ca; }
.btn-selection-action.untag:hover:not(:disabled) { background: #be123c; border-color: #be123c; }
.btn-selection-action.activate:hover:not(:disabled) { background: #059669; border-color: #059669; }
.btn-selection-action.warn:hover:not(:disabled) { background: #b45309; border-color: #b45309; }
.btn-selection-action.danger:hover:not(:disabled) { background: #dc2626; border-color: #dc2626; }
.btn-selection-action:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
