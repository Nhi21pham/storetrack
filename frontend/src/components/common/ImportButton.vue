<template>
  <button
    class="import-btn"
    :class="[variant, variant === 'solid' ? `size-${size}` : null]"
    :disabled="disabled"
    :title="title"
    @click="$emit('click')"
  >
    <svg :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    <span>{{ label }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'

// Shared Import button, styled to match ExportButton: `solid` is the toolbar
// style, `ghost` the outlined style for dark selection bars.
const props = defineProps({
  disabled: { type: Boolean, default: false },
  label:    { type: String,  default: 'Import' },
  title:    { type: String,  default: 'Import records from an Excel file' },
  variant:  { type: String,  default: 'solid', validator: (v) => ['solid', 'ghost'].includes(v) },
  size:     { type: String,  default: 'lg',    validator: (v) => ['sm', 'md', 'lg'].includes(v) },
})

defineEmits(['click'])

const iconSize = computed(() => (props.variant === 'ghost' ? 13 : 14))
</script>

<style scoped>
.import-btn { display: inline-flex; align-items: center; gap: 6px; font-family: inherit; cursor: pointer; white-space: nowrap; flex-shrink: 0; transition: background 0.2s, border-color 0.15s, opacity 0.2s; }

.import-btn.solid { border: 1px solid #111; font-weight: 600; color: #111; background: #fff; }
.import-btn.solid:hover:not(:disabled) { background: #f3f4f6; }
.import-btn.solid:disabled { opacity: 0.55; cursor: not-allowed; }

.import-btn.size-sm { padding: 5px 12px; border-radius: 7px; font-size: 12.5px; }
.import-btn.size-md { padding: 7px 13px; border-radius: 10px; font-size: 12.5px; height: 36px; }
.import-btn.size-lg { padding: 9px 14px; border-radius: 10px; font-size: 13.5px; }

.import-btn.ghost { gap: 5px; padding: 6px 12px; border-radius: 7px; font-size: 12.5px; font-weight: 500; color: #fff; border: 1px solid rgba(255,255,255,0.2); background: transparent; }
.import-btn.ghost:hover:not(:disabled) { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.4); }
.import-btn.ghost:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
