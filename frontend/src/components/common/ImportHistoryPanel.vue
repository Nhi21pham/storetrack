<template>
  <div class="history-panel">
    <div class="panel-filters">
      <DateRangeFilter
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        @change="() => goToPage(1)"
      />
      <button v-if="startDate || endDate" class="clear-dates" @click="clearDates">{{ $t('shared.clearDates') }}</button>
      <div class="filters-spacer"></div>
      <button class="refresh-btn" :disabled="loading || refreshing" :title="$t('shared.refresh')" @click="refresh">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :class="{ spinning: refreshing }">
          <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
        </svg>
      </button>
    </div>

    <div class="panel-body">
      <div v-if="loading" class="state-row"><div class="spinner"></div><span>{{ $t('shared.loadingHistory') }}</span></div>
      <div v-else-if="error" class="api-error">{{ error }}</div>
      <div v-else-if="!rows.length" class="empty-state">{{ $t('shared.noImportsYet') }}</div>

      <div v-else class="table-wrap">
        <table class="history-table">
          <thead>
            <tr>
              <th class="file-col">{{ $t('shared.file') }}</th>
              <th>{{ $t('shared.status') }}</th>
              <th class="num">{{ $t('shared.created') }}</th>
              <th class="num">{{ $t('shared.skipped') }}</th>
              <th class="num">{{ $t('shared.failed') }}</th>
              <th>{{ $t('shared.by') }}</th>
              <th>{{ $t('shared.when') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="row in rows" :key="row.id">
              <tr class="data-row" :class="{ open: expandedId === row.id }" @click="toggle(row)">
                <td class="file-col">
                  <svg class="chevron" :class="{ open: expandedId === row.id }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                  {{ row.original_filename || '—' }}
                </td>
                <td><span class="badge" :class="statusInfo(row.status).cls">{{ statusInfo(row.status).label }}</span></td>
                <td class="num ok">{{ row.created_count }}</td>
                <td class="num warn">{{ row.skipped_count }}</td>
                <td class="num bad">{{ row.failed_count }}</td>
                <td class="by-cell">{{ row.user_name || row.user_email || '—' }}</td>
                <td class="date-cell">{{ formatDateTime(row.created_at) }}</td>
              </tr>
              <tr v-if="expandedId === row.id" class="detail-row">
                <td colspan="7">
                  <div v-if="detailLoading" class="state-row sm"><div class="spinner"></div><span>{{ $t('shared.loadingDetails') }}</span></div>
                  <div v-else-if="detail?.error_message" class="api-error">{{ detail.error_message }}</div>
                  <div v-else-if="!detailProblems.length" class="detail-clean">
                    {{ $t('shared.allRowsCreatedCleanly', { count: detail?.created_count ?? row.created_count }) }}
                  </div>
                  <div v-else class="detail-problems">
                    <p class="detail-title">{{ $t('shared.skippedFailedRows') }}</p>
                    <ul>
                      <li v-for="p in detailProblems" :key="p.rowNumber">
                        <span class="badge sm" :class="p.status === 'failed' ? 'bad' : 'warn'">{{ $t('shared.row', { number: p.rowNumber }) }}</span>
                        <span v-if="formatValues(p.values)" class="row-data">{{ formatValues(p.values) }}</span>
                        <span class="row-msg">— {{ p.message }}</span>
                      </li>
                    </ul>
                  </div>
                </td>
              </tr>
            </template>
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
import { ref, computed, toRef, onMounted } from 'vue'
import Pagination from '@/components/common/Pagination.vue'
import DateRangeFilter from '@/components/common/DateRangeFilter.vue'
import { useImportHistory } from '@/composables/useImportHistory'
import { fetchImportDetail } from '@/features/imports/services/importHistoryService'
import { formatDateTime } from '@/utils/datetime'
import { t } from '@/i18n'

const props = defineProps({
  scope:   { type: String, default: 'store' },
  scopeId: { type: [String, Number], required: true },
  type:    { type: String, required: true },
})

const { loading, refreshing, error, rows, page, perPage, total, lastPage, startDate, endDate, load, goToPage, refresh } =
  useImportHistory({ scope: props.scope, scopeId: toRef(props, 'scopeId'), type: props.type })

const clearDates = () => {
  startDate.value = ''
  endDate.value = ''
  goToPage(1)
}

const expandedId = ref(null)
const detail = ref(null)
const detailLoading = ref(false)

const detailProblems = computed(() => detail.value?.results || [])

// Joins a row's actual cell values for display, e.g. "Hộp" or "Bút · 12000".
const formatValues = (values) =>
  Object.values(values || {}).filter((v) => v !== '' && v != null).join(' · ')

const onPerPage = (value) => {
  perPage.value = value
  load(1)
}

const toggle = async (row) => {
  if (expandedId.value === row.id) {
    expandedId.value = null
    return
  }
  expandedId.value = row.id
  detail.value = null
  detailLoading.value = true
  try {
    detail.value = await fetchImportDetail({ scope: props.scope, scopeId: props.scopeId, importId: row.id })
  } catch (e) {
    detail.value = { error_message: e.message }
  } finally {
    detailLoading.value = false
  }
}

const statusInfo = (status) => {
  switch (status) {
    case 'completed':  return { label: t('shared.jobStatus.completed'), cls: 'done' }
    case 'processing': return { label: t('shared.jobStatus.processing'), cls: 'busy' }
    case 'pending':    return { label: t('shared.jobStatus.pendingImport'), cls: 'busy' }
    case 'failed':     return { label: t('shared.jobStatus.failed'), cls: 'bad' }
    default:           return { label: status, cls: 'busy' }
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.num.ok { color: #16a34a; } .num.warn { color: #b45309; } .num.bad { color: #dc2626; }
.data-row { cursor: pointer; }
.data-row.open :deep(td) { background: #f9fafb; }
.chevron { color: #9ca3af; transition: transform 0.15s; vertical-align: middle; margin-right: 4px; }
.chevron.open { transform: rotate(90deg); }

.detail-row td { background: #fafafa; padding: 12px 16px 16px 32px; border-bottom: 1px solid #f3f4f6; }
.detail-clean { font-size: 12.5px; color: #16a34a; }
.detail-title { font-size: 12px; color: #6b7280; margin: 0 0 6px; }
.detail-problems ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 5px; max-height: 30vh; overflow-y: auto; }
.detail-problems li { font-size: 12.5px; color: #6b7280; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.detail-problems .row-data { font-weight: 600; color: #111; }
.detail-problems .row-msg { color: #6b7280; }
.badge.sm { padding: 1px 7px; }
</style>
