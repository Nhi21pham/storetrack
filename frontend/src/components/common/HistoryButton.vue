<template>
  <button
    class="history-btn"
    :class="[variant, variant === 'solid' ? `size-${size}` : null]"
    :disabled="disabled"
    :title="title"
    @click="$emit('click')"
  >
    <svg :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
    <span>{{ label }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { t } from '@/i18n'

// Shared History button, styled to match Import/Export buttons. Opens an
// import/export history modal on the page it sits in.
const props = defineProps({
  disabled: { type: Boolean, default: false },
  label:    { type: String,  default: () => t('shared.history') },
  title:    { type: String,  default: () => t('shared.viewHistoryTitle') },
  variant:  { type: String,  default: 'solid', validator: (v) => ['solid', 'ghost'].includes(v) },
  size:     { type: String,  default: 'lg',    validator: (v) => ['sm', 'md', 'lg'].includes(v) },
})

defineEmits(['click'])

const iconSize = computed(() => (props.variant === 'ghost' ? 13 : 14))
</script>

<style scoped>
.history-btn { display: inline-flex; align-items: center; gap: 6px; font-family: inherit; cursor: pointer; white-space: nowrap; flex-shrink: 0; transition: background 0.2s, border-color 0.15s, opacity 0.2s; }

.history-btn.solid { border: 1px solid #d1d5db; font-weight: 600; color: #374151; background: #fff; }
.history-btn.solid:hover:not(:disabled) { background: #f9fafb; border-color: #9ca3af; }
.history-btn.solid:disabled { opacity: 0.55; cursor: not-allowed; }

.history-btn.size-sm { padding: 5px 12px; border-radius: 7px; font-size: 12.5px; }
.history-btn.size-md { padding: 7px 13px; border-radius: 10px; font-size: 12.5px; height: 36px; }
.history-btn.size-lg { padding: 9px 14px; border-radius: 10px; font-size: 13.5px; }

.history-btn.ghost { gap: 5px; padding: 6px 12px; border-radius: 7px; font-size: 12.5px; font-weight: 500; color: #fff; border: 1px solid rgba(255,255,255,0.2); background: transparent; }
.history-btn.ghost:hover:not(:disabled) { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.4); }
.history-btn.ghost:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
