<template>
  <button
    class="export-btn"
    :class="variant"
    :disabled="exporting || disabled"
    :title="exporting ? 'Preparing export...' : title"
    @click="$emit('click')"
  >
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    <span>{{ exporting ? 'Exporting...' : label }}</span>
  </button>
</template>

<script setup>
// Shared Export button. `solid` is the dark filled toolbar style; `ghost` is the
// outlined style used inside dark selection bars.
defineProps({
  exporting: { type: Boolean, default: false },
  disabled:  { type: Boolean, default: false },
  label:     { type: String,  default: 'Export' },
  title:     { type: String,  default: 'Export current view to Excel' },
  variant:   { type: String,  default: 'solid', validator: (v) => ['solid', 'ghost'].includes(v) },
})

defineEmits(['click'])
</script>

<style scoped>
.export-btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 7px; font-size: 12.5px; cursor: pointer; white-space: nowrap; flex-shrink: 0; transition: background 0.2s, border-color 0.15s, opacity 0.2s; }

.export-btn.solid { padding: 9px 14px; border-radius: 10px; font-size: 13.5px; border: 1px solid #111; font-weight: 600; color: #fff; background: #111; }
.export-btn.solid:hover:not(:disabled) { background: #000; }
.export-btn.solid:disabled { opacity: 0.55; cursor: not-allowed; }

.export-btn.ghost { padding: 6px 12px; border: 1px solid rgba(255,255,255,0.2); font-weight: 500; color: #fff; background: transparent; }
.export-btn.ghost:hover:not(:disabled) { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.4); }
.export-btn.ghost:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
