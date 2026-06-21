<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ title }}</h2>
        <button class="close-btn" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div v-if="tabs.length > 1" class="tab-bar">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="tab"
          :class="{ active: tab.key === activeKey }"
          @click="activeKey = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="modal-body">
        <component :is="activeTab.component" v-bind="activeTab.props" :key="activeTab.key" v-if="activeTab" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

// Generic tabbed history modal. Each tab supplies its own panel component and
// props, so the same shell serves Imports|Exports (entity pages), Scans|Exports
// (invoices) and Exports-only (reports). Panels share their table/badge/state
// visuals via the :deep styles below — they only carry their own markup.
const props = defineProps({
  title: { type: String, default: 'History' },
  // [{ key, label, component, props }]
  tabs:  { type: Array, required: true },
})

defineEmits(['close'])

const activeKey = ref(props.tabs[0]?.key)
const activeTab = computed(() => props.tabs.find((t) => t.key === activeKey.value) || props.tabs[0])
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 920px; min-height: 460px; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: hidden; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 6px; border-radius: 6px; transition: all 0.15s; display: flex; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.tab-bar { display: flex; gap: 4px; padding: 0 16px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0; }
.tab { background: none; border: none; padding: 12px 14px; font-size: 13px; font-weight: 600; color: #9ca3af; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color 0.15s; }
.tab:hover { color: #374151; }
.tab.active { color: #111; border-bottom-color: #111; }

.modal-body { flex: 1; display: flex; min-height: 0; }

/* Shared panel visuals — panels (Import/Scan/Export history) carry only their
   own markup and unique bits; everything below is styled once, here. Base table
   rules key off .history-table so nested detail tables are never affected. */
.modal-body :deep(.history-panel) { flex: 1; display: flex; flex-direction: column; min-height: 0; width: 100%; }
.modal-body :deep(.panel-filters) { display: flex; align-items: flex-end; gap: 12px; padding: 12px 24px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0; }
.modal-body :deep(.filters-spacer) { flex: 1; }
.modal-body :deep(.clear-dates) { display: inline-flex; align-items: center; min-height: 36px; padding: 0 14px; border: 1px solid #e5e7eb; background: #fafafa; color: #6b7280; border-radius: 10px; font-size: 13px; font-weight: 500; cursor: pointer; }
.modal-body :deep(.clear-dates:hover) { border-color: #dc2626; color: #dc2626; background: #fef2f2; }
.modal-body :deep(.refresh-btn) { align-self: center; background: none; border: none; color: #9ca3af; cursor: pointer; padding: 6px; border-radius: 6px; display: flex; transition: all 0.15s; }
.modal-body :deep(.refresh-btn:hover:not(:disabled)) { color: #374151; background: #f3f4f6; }
.modal-body :deep(.refresh-btn:disabled) { opacity: 0.4; cursor: not-allowed; }
.modal-body :deep(.refresh-btn svg.spinning) { animation: spin 0.7s linear infinite; }

.modal-body :deep(.panel-body) { flex: 1; overflow-y: auto; display: flex; flex-direction: column; min-height: 0; padding: 8px 0 0; }

.modal-body :deep(.state-row) { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 48px; color: #6b7280; font-size: 14px; }
.modal-body :deep(.state-row.sm) { padding: 16px; }
.modal-body :deep(.spinner) { width: 18px; height: 18px; border: 2px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }
.modal-body :deep(.empty-state) { text-align: center; padding: 48px; font-size: 14px; color: #9ca3af; }
.modal-body :deep(.api-error) { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin: 12px 24px; }

.modal-body :deep(.table-wrap) { overflow-x: auto; flex: 1; padding: 0 12px; }
.modal-body :deep(.history-table) { width: 100%; border-collapse: collapse; font-size: 13px; }
.modal-body :deep(.history-table thead th) { position: sticky; top: 0; background: #fff; text-align: left; padding: 10px 12px; font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; box-shadow: 0 1px 0 #f3f4f6; }
.modal-body :deep(.history-table th.num), .modal-body :deep(.history-table td.num) { text-align: right; font-variant-numeric: tabular-nums; }
.modal-body :deep(.history-table tbody td) { padding: 11px 12px; border-bottom: 1px solid #f9fafb; color: #374151; vertical-align: middle; }
.modal-body :deep(.history-table .data-row:hover td) { background: #fafafa; }
.modal-body :deep(.file-col) { color: #111; white-space: nowrap; }
.modal-body :deep(.by-cell), .modal-body :deep(.date-cell) { color: #6b7280; font-size: 12px; white-space: nowrap; }
.modal-body :deep(.muted) { color: #d1d5db; }

.modal-body :deep(.badge) { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.modal-body :deep(.badge.done) { background: #dcfce7; color: #166534; }
.modal-body :deep(.badge.busy) { background: #fef9c3; color: #854d0e; }
.modal-body :deep(.badge.bad) { background: #fee2e2; color: #991b1b; }
.modal-body :deep(.badge.warn) { background: #fef9c3; color: #854d0e; }
</style>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
