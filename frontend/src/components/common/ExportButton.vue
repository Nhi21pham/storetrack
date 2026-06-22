<template>
  <button
    class="export-btn"
    :class="[variant, variant === 'solid' ? `size-${size}` : null]"
    :disabled="exporting || disabled"
    :title="exporting ? $t('shared.preparingExport') : title"
    @click="$emit('click')"
  >
    <svg v-if="exporting" class="spinner" :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9" stroke-opacity="0.25"/><path d="M21 12a9 9 0 0 0-9-9" stroke-linecap="round"/></svg>
    <svg v-else :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    <span>{{ exporting ? $t('common.exporting') : label }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { t } from '@/i18n'

// Shared Export button. `solid` is the dark filled toolbar style (sized sm/md/lg
// to match neighboring controls); `ghost` is the outlined style used inside the
// dark selection bars.
const props = defineProps({
  exporting: { type: Boolean, default: false },
  disabled:  { type: Boolean, default: false },
  label:     { type: String,  default: () => t('common.export') },
  title:     { type: String,  default: () => t('shared.exportToExcelTitle') },
  variant:   { type: String,  default: 'solid', validator: (v) => ['solid', 'ghost'].includes(v) },
  size:      { type: String,  default: 'lg',    validator: (v) => ['sm', 'md', 'lg'].includes(v) },
})

defineEmits(['click'])

const iconSize = computed(() => (props.variant === 'ghost' ? 13 : 14))
</script>

<style scoped>
.export-btn { display: inline-flex; align-items: center; gap: 6px; font-family: inherit; cursor: pointer; white-space: nowrap; flex-shrink: 0; transition: background 0.2s, border-color 0.15s, opacity 0.2s; }

.spinner { animation: export-spin 0.7s linear infinite; }
@keyframes export-spin { to { transform: rotate(360deg); } }

.export-btn.solid { border: 1px solid #111; font-weight: 600; color: #fff; background: #111; }
.export-btn.solid:hover:not(:disabled) { background: #000; }
.export-btn.solid:disabled { opacity: 0.55; cursor: not-allowed; }

.export-btn.size-sm { padding: 5px 12px; border-radius: 7px; font-size: 12.5px; }
.export-btn.size-md { padding: 7px 13px; border-radius: 10px; font-size: 12.5px; height: 36px; }
.export-btn.size-lg { padding: 9px 14px; border-radius: 10px; font-size: 13.5px; }

.export-btn.ghost { gap: 5px; padding: 6px 12px; border-radius: 7px; font-size: 12.5px; font-weight: 500; color: #fff; border: 1px solid rgba(255,255,255,0.2); background: transparent; }
.export-btn.ghost:hover:not(:disabled) { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.4); }
.export-btn.ghost:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
