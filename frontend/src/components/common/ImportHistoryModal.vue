<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ title }}</h2>
        <div class="header-actions">
          <button class="refresh-btn" :disabled="loading || refreshing" title="Refresh" @click="refresh">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :class="{ spinning: refreshing }">
              <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
          </button>
          <button class="close-btn" @click="$emit('close')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="modal-body">
        <div v-if="loading" class="state-row"><div class="spinner"></div><span>Loading history...</span></div>
        <div v-else-if="error" class="api-error">{{ error }}</div>
        <div v-else-if="!rows.length" class="empty-state">No imports yet.</div>

        <div v-else class="table-wrap">
          <table>
            <thead>
              <tr>
                <th class="file-col">File</th>
                <th>Status</th>
                <th class="num">Created</th>
                <th class="num">Skipped</th>
                <th class="num">Failed</th>
                <th>By</th>
                <th>When</th>
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
                    <div v-if="detailLoading" class="state-row sm"><div class="spinner"></div><span>Loading details...</span></div>
                    <div v-else-if="detail?.error_message" class="api-error">{{ detail.error_message }}</div>
                    <div v-else-if="!detailProblems.length" class="detail-clean">
                      All {{ detail?.created_count ?? row.created_count }} row(s) created cleanly — nothing skipped or failed.
                    </div>
                    <div v-else class="detail-problems">
                      <p class="detail-title">Skipped / failed rows:</p>
                      <ul>
                        <li v-for="p in detailProblems" :key="p.rowNumber">
                          <span class="badge sm" :class="p.status === 'failed' ? 'bad' : 'warn'">Row {{ p.rowNumber }}</span>
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
  </div>
</template>

<script setup>
import { ref, computed, toRef, onMounted } from 'vue'
import Pagination from '@/components/common/Pagination.vue'
import { useImportHistory } from '@/composables/useImportHistory'
import { fetchImportDetail } from '@/features/imports/services/importHistoryService'
import { formatDateTime } from '@/utils/datetime'

const props = defineProps({
  storeId: { type: [String, Number], required: true },
  type:    { type: String, required: true },
  title:   { type: String, default: 'Import History' },
})

defineEmits(['close'])

const { loading, refreshing, error, rows, page, perPage, total, lastPage, load, goToPage, refresh } =
  useImportHistory({ storeId: toRef(props, 'storeId'), type: props.type })

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
    detail.value = await fetchImportDetail({ storeId: props.storeId, importId: row.id })
  } catch (e) {
    detail.value = { error_message: e.message }
  } finally {
    detailLoading.value = false
  }
}

const statusInfo = (status) => {
  switch (status) {
    case 'completed':  return { label: 'Completed', cls: 'done' }
    case 'processing': return { label: 'Processing', cls: 'busy' }
    case 'pending':    return { label: 'Pending', cls: 'busy' }
    case 'failed':     return { label: 'Failed', cls: 'bad' }
    default:           return { label: status, cls: 'busy' }
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 880px; min-height: 460px; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: hidden; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.header-actions { display: flex; align-items: center; gap: 4px; }
.refresh-btn, .close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 6px; border-radius: 6px; transition: all 0.15s; display: flex; }
.refresh-btn:hover:not(:disabled), .close-btn:hover { color: #374151; background: #f3f4f6; }
.refresh-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.refresh-btn svg.spinning { animation: spin 0.7s linear infinite; }

.modal-body { flex: 1; overflow-y: auto; display: flex; flex-direction: column; min-height: 0; padding: 8px 0 0; }

.state-row { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 48px; color: #6b7280; font-size: 14px; }
.state-row.sm { padding: 16px; }
.spinner { width: 18px; height: 18px; border: 2px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }
.empty-state { text-align: center; padding: 48px; font-size: 14px; color: #9ca3af; }
.api-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin: 12px 24px; }

.table-wrap { overflow-x: auto; flex: 1; padding: 0 12px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th { position: sticky; top: 0; background: #fff; text-align: left; padding: 10px 12px; font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; box-shadow: 0 1px 0 #f3f4f6; }
th.num, td.num { text-align: right; font-variant-numeric: tabular-nums; }
.data-row { cursor: pointer; }
.data-row td { padding: 11px 12px; border-bottom: 1px solid #f9fafb; color: #374151; vertical-align: middle; }
.data-row:hover td { background: #fafafa; }
.data-row.open td { background: #f9fafb; }
.file-col { font-weight: 500; color: #111; white-space: nowrap; }
.chevron { color: #9ca3af; transition: transform 0.15s; vertical-align: middle; margin-right: 4px; }
.chevron.open { transform: rotate(90deg); }
.num.ok { color: #16a34a; } .num.warn { color: #b45309; } .num.bad { color: #dc2626; }
.by-cell, .date-cell { color: #6b7280; font-size: 12px; white-space: nowrap; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.badge.sm { padding: 1px 7px; }
.badge.done { background: #dcfce7; color: #166534; }
.badge.busy { background: #fef9c3; color: #854d0e; }
.badge.bad { background: #fee2e2; color: #991b1b; }
.badge.warn { background: #fef9c3; color: #854d0e; }

.detail-row td { background: #fafafa; padding: 12px 16px 16px 32px; border-bottom: 1px solid #f3f4f6; }
.detail-clean { font-size: 12.5px; color: #16a34a; }
.detail-title { font-size: 12px; color: #6b7280; margin: 0 0 6px; }
.detail-problems ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 5px; max-height: 30vh; overflow-y: auto; }
.detail-problems li { font-size: 12.5px; color: #6b7280; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.detail-problems .row-data { font-weight: 600; color: #111; }
.detail-problems .row-msg { color: #6b7280; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
