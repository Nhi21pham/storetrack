<template>
  <div class="selection-bar">
    <span class="selection-count">{{ $t('shared.selectedCount', { count }) }}</span>
    <div class="selection-actions">
      <button class="btn-selection-action" @click="$emit('clear')">{{ $t('common.clear') }}</button>
      <ExportButton variant="ghost" :label="$t('shared.exportSelected')" :exporting="exporting" @click="$emit('export')" />
    </div>
  </div>
</template>

<script setup>
import ExportButton from '@/components/common/ExportButton.vue'

defineProps({
  count:     { type: Number,  required: true },
  exporting: { type: Boolean, required: true },
})

defineEmits(['clear', 'export'])
</script>

<style scoped>
.selection-bar { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; margin-bottom: 12px; background: #111; color: #fff; border-radius: 10px; font-size: 13px; }
.selection-count { font-weight: 600; }
.selection-actions { display: flex; gap: 8px; }
.btn-selection-action { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border: 1px solid rgba(255,255,255,0.2); border-radius: 7px; font-size: 12.5px; font-weight: 500; color: #fff; background: transparent; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.btn-selection-action:hover:not(:disabled) { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.4); }
</style>
