<template>
  <span class="scan-type-badge" :class="isAi ? 'ai' : 'dictionary'">
    <svg v-if="isAi" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 3l1.9 4.6L18.5 9.5 13.9 11.4 12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/></svg>
    <svg v-else width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 5a2 2 0 0 1 2-2h13v18H6a2 2 0 0 1-2-2z"/><line x1="9" y1="7" x2="15" y2="7"/><line x1="9" y1="11" x2="15" y2="11"/></svg>
    {{ isAi ? $t('invoices.aiScan') : $t('invoices.dictionary') }}
  </span>
</template>

<script setup>
// Shows which engine produced a scan: the AI provider (gemini) or the free,
// deterministic "dictionary" template parser. Reused across purchase and (later)
// sale invoice scan history.
import { computed } from 'vue'

const props = defineProps({
  // 'ai' | 'template' as stored on the scan record.
  type: { type: String, default: 'template' },
})

const isAi = computed(() => props.type === 'ai')
</script>

<style scoped>
.scan-type-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
.ai { background: #ede9fe; color: #6d28d9; }
.dictionary { background: #eff6ff; color: #1d4ed8; }
</style>
