<template>
  <div class="history-panel">
    <div class="panel-filters">
      <DateRangeFilter
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        @change="() => goToPage(1)"
      />
      <button v-if="startDate || endDate" class="clear-dates" @click="clearDates">Clear dates</button>
      <div class="filters-spacer"></div>
      <button class="refresh-btn" :disabled="loading || refreshing" title="Refresh" @click="refresh">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :class="{ spinning: refreshing }">
          <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
        </svg>
      </button>
    </div>

    <div class="panel-body">
      <div v-if="loading" class="state-row"><div class="spinner"></div><span>Loading history...</span></div>
      <div v-else-if="error" class="api-error">{{ error }}</div>
      <div v-else-if="!rows.length" class="empty-state">No exports yet.</div>

      <div v-else class="table-wrap">
        <table class="history-table">
          <thead>
            <tr>
              <th class="file-col">File</th>
              <th>Type</th>
              <th>Status</th>
              <th>By</th>
              <th>When</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td class="file-col">
                <span class="file-name">{{ row.filename || '—' }}</span>
                <span v-if="exportFormatLabel(row.filename)" class="fmt-chip">{{ exportFormatLabel(row.filename) }}</span>
                <div v-if="row.status === 'failed' && row.error_message" class="row-err">{{ row.error_message }}</div>
              </td>
              <td>{{ exportTypeLabel(row.type) }}</td>
              <td><span class="badge" :class="statusInfo(row.status).cls">{{ statusInfo(row.status).label }}</span></td>
              <td class="by-cell">{{ row.user_name || row.user_email || '—' }}</td>
              <td class="date-cell">{{ formatDateTime(row.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination
        v-if="!loading && total > 0"
        :current-page="page"
        :total-pages="lastPage"
        :total="total"
        :per-page="perPage"
        @update:current-page="goToPage"
        @update:per-page="onPerPage"
      />
    </div>
  </div>
</template>

<script setup>
import { toRef, onMounted } from 'vue'
import Pagination from '@/components/common/Pagination.vue'
import DateRangeFilter from '@/components/common/DateRangeFilter.vue'
import { useExportHistory } from '@/composables/useExportHistory'
import { exportTypeLabel, exportFormatLabel } from '@/utils/exportTypes'
import { formatDateTime } from '@/utils/datetime'

const props = defineProps({
  // 'store' | 'business' — which scope the exports were recorded against.
  scope:   { type: String, default: 'store' },
  scopeId: { type: [String, Number], required: true },
  // Export type strings to show, e.g. ['invoices', 'invoice-documents'].
  types:   { type: Array, default: null },
})

const { loading, refreshing, error, rows, page, perPage, total, lastPage, startDate, endDate, load, goToPage, refresh } =
  useExportHistory({ scope: props.scope, scopeId: toRef(props, 'scopeId'), types: props.types })

const clearDates = () => {
  startDate.value = ''
  endDate.value = ''
  goToPage(1)
}

const onPerPage = (value) => {
  perPage.value = value
  load(1)
}

const statusInfo = (status) => {
  switch (status) {
    case 'completed':  return { label: 'Completed', cls: 'done' }
    case 'processing': return { label: 'Processing', cls: 'busy' }
    case 'pending':    return { label: 'Preparing', cls: 'busy' }
    case 'failed':     return { label: 'Failed', cls: 'bad' }
    default:           return { label: status, cls: 'busy' }
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.file-name { font-weight: 500; color: #111; }
.fmt-chip { display: inline-block; margin-left: 8px; padding: 1px 7px; border-radius: 5px; background: #eef2ff; color: #4338ca; font-size: 10px; font-weight: 600; vertical-align: middle; }
.row-err { margin-top: 3px; font-size: 11.5px; color: #b91c1c; white-space: normal; max-width: 360px; }
</style>
