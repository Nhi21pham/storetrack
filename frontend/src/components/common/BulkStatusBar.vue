<template>
  <div class="selection-bar">
    <span class="selection-count">{{ count }} selected</span>
    <div class="selection-actions">
      <button class="btn-selection-action" @click="$emit('clear')">Clear</button>
      <button v-if="showExport" class="btn-selection-action" :disabled="exporting" @click="$emit('export')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        {{ exporting ? 'Exporting...' : 'Export selected' }}
      </button>
      <button v-if="showStatus" class="btn-selection-action activate" :disabled="busy" @click="$emit('activate')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Activate
      </button>
      <button v-if="showStatus" class="btn-selection-action warn" :disabled="busy" @click="$emit('deactivate')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Deactivate
      </button>
      <button v-if="canDelete" class="btn-selection-action danger" :disabled="busy" @click="$emit('delete')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        Delete
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  count:      { type: Number,  required: true },
  busy:       { type: Boolean, default: false },
  canDelete:  { type: Boolean, default: false },
  showStatus: { type: Boolean, default: true },
  showExport: { type: Boolean, default: false },
  exporting:  { type: Boolean, default: false },
})

defineEmits(['clear', 'activate', 'deactivate', 'delete', 'export'])
</script>

<style scoped>
.selection-bar { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; margin-bottom: 12px; background: #111; color: #fff; border-radius: 10px; font-size: 13px; }
.selection-count { font-weight: 600; }
.selection-actions { display: flex; gap: 8px; }
.btn-selection-action { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border: 1px solid rgba(255,255,255,0.2); border-radius: 7px; font-size: 12.5px; font-weight: 500; color: #fff; background: transparent; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.btn-selection-action:hover:not(:disabled) { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.4); }
.btn-selection-action.activate:hover:not(:disabled) { background: #059669; border-color: #059669; }
.btn-selection-action.warn:hover:not(:disabled) { background: #b45309; border-color: #b45309; }
.btn-selection-action.danger:hover:not(:disabled) { background: #dc2626; border-color: #dc2626; }
.btn-selection-action:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
