<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>Scan History</h2>
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

      <div class="modal-filters">
        <DateRangeFilter
          v-model:startDate="startDate"
          v-model:endDate="endDate"
          @change="() => goToPage(1)"
        />
        <button v-if="startDate || endDate" class="clear-dates" @click="clearDates">Clear dates</button>
      </div>

      <div class="modal-body">
        <div v-if="loading" class="state-row"><div class="spinner"></div><span>Loading history...</span></div>
        <div v-else-if="error" class="api-error">{{ error }}</div>
        <div v-else-if="!rows.length" class="empty-state">No scans yet.</div>

        <div v-else class="table-wrap">
          <table>
            <thead>
              <tr>
                <th class="file-col">File</th>
                <th>Scan type</th>
                <th>Status</th>
                <th class="num">Items</th>
                <th class="num">New</th>
                <th>{{ partyLabel }}</th>
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
                  <td><ScanTypeBadge :type="row.scan_type" /></td>
                  <td><span class="badge" :class="statusInfo(row.status).cls">{{ statusInfo(row.status).label }}</span></td>
                  <td class="num">
                    <span v-if="row.status === 'failed'" class="muted">—</span>
                    <span v-else>{{ row.matched_item_count }} / {{ row.item_count }}</span>
                  </td>
                  <td class="num">
                    <span v-if="row.created_count > 0" class="created-count">{{ row.created_count }}</span>
                    <span v-else class="muted">—</span>
                  </td>
                  <td class="party-cell">{{ row.party_name || '—' }}</td>
                  <td class="by-cell">{{ row.user_name || row.user_email || '—' }}</td>
                  <td class="date-cell">{{ formatDateTime(row.created_at) }}</td>
                </tr>
                <tr v-if="expandedId === row.id" class="detail-row">
                  <td colspan="8">
                    <div v-if="detailLoading" class="state-row sm"><div class="spinner"></div><span>Loading details...</span></div>
                    <div v-else-if="detail?.status === 'failed'" class="api-error">
                      {{ detail.error_message || 'This scan failed.' }}
                    </div>
                    <div v-else-if="detail" class="detail">
                      <div class="detail-summary">
                        <div class="sum-item">
                          <span class="sum-label">{{ partyLabel }}</span>
                          <span class="sum-value">
                            {{ detail.party?.matched_name || detail.party?.extracted_name || '—' }}
                            <MatchBadge v-if="detail.party?.extracted_name || detail.party?.matched" :matched="!!detail.party?.matched" />
                          </span>
                        </div>
                        <div v-if="detail.invoice_no" class="sum-item">
                          <span class="sum-label">Invoice no.</span>
                          <span class="sum-value">{{ detail.invoice_no }}</span>
                        </div>
                        <div class="sum-item">
                          <span class="sum-label">Subtotal</span>
                          <span class="sum-value">{{ formatMoney(detail.totals?.subtotal) }}</span>
                        </div>
                        <div class="sum-item">
                          <span class="sum-label">VAT</span>
                          <span class="sum-value">{{ formatMoney(detail.totals?.vat_total) }}</span>
                        </div>
                        <div class="sum-item">
                          <span class="sum-label">Grand total</span>
                          <span class="sum-value strong">{{ formatMoney(detail.totals?.grand_total) }}</span>
                        </div>
                      </div>

                      <div v-if="detail.created_entities?.length" class="detail-block">
                        <p class="detail-title">New records created from this scan:</p>
                        <ul class="chips">
                          <li v-for="(e, i) in detail.created_entities" :key="i" class="chip">
                            <span class="chip-type">{{ entityTypeLabel(e.type) }}</span> {{ e.name }}
                          </li>
                        </ul>
                      </div>

                      <div v-if="detail.warnings?.length" class="detail-block">
                        <p class="detail-title">Warnings:</p>
                        <ul class="warns">
                          <li v-for="(w, i) in detail.warnings" :key="i">{{ w }}</li>
                        </ul>
                      </div>

                      <div class="detail-block">
                        <p class="detail-title">Items read ({{ detail.items?.length || 0 }}):</p>
                        <div v-if="!detail.items?.length" class="detail-clean">No line items were read.</div>
                        <table v-else class="items-table">
                          <thead>
                            <tr>
                              <th>Product</th>
                              <th>Unit</th>
                              <th class="num">Qty</th>
                              <th class="num">Unit price</th>
                              <th class="num">Line total</th>
                              <th></th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(it, i) in detail.items" :key="i">
                              <td>{{ it.name || '—' }}<template v-if="it.code"> · {{ it.code }}</template></td>
                              <td>{{ it.unit || '—' }}</td>
                              <td class="num">{{ formatQuantity(it.quantity) }}</td>
                              <td class="num">{{ formatMoney(it.unit_price) }}</td>
                              <td class="num">{{ formatMoney(it.line_total) }}</td>
                              <td><MatchBadge :matched="!!it.matched" /></td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
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
import DateRangeFilter from '@/components/common/DateRangeFilter.vue'
import ScanTypeBadge from '@/features/invoices/components/ScanTypeBadge.vue'
import MatchBadge from '@/features/invoices/components/MatchBadge.vue'
import { useScanHistory } from '@/composables/useScanHistory'
import { fetchScanDetail } from '@/features/invoices/services/scanHistoryService'
import { formatMoney, formatQuantity } from '@/features/invoices/constants'
import { formatDateTime } from '@/utils/datetime'

const props = defineProps({
  storeId: { type: [String, Number], required: true },
  // Invoice kind whose scans to show: 'purchase' | 'sale'.
  type: { type: String, required: true },
})

defineEmits(['close'])

// A purchase scan reads the seller (supplier); a sale scan reads the buyer (customer).
const partyLabel = computed(() => (props.type === 'sale' ? 'Customer' : 'Supplier'))

const { loading, refreshing, error, rows, page, perPage, total, lastPage, startDate, endDate, load, goToPage, refresh } =
  useScanHistory({ storeId: toRef(props, 'storeId'), type: props.type })

const clearDates = () => {
  startDate.value = ''
  endDate.value = ''
  goToPage(1)
}

const expandedId = ref(null)
const detail = ref(null)
const detailLoading = ref(false)

const entityTypeLabel = (type) => ({ supplier: 'Supplier', customer: 'Customer', product: 'Product', unit: 'Unit' }[type] || type)

// Scans are synchronous, so a record is only ever completed or failed; the
// pending/processing fallback is kept defensive in case that ever changes.
const statusInfo = (status) => {
  switch (status) {
    case 'completed': return { label: 'Successful', cls: 'done' }
    case 'failed':    return { label: 'Failed', cls: 'bad' }
    default:          return { label: 'Processing', cls: 'busy' }
  }
}

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
    detail.value = await fetchScanDetail({ storeId: props.storeId, scanId: row.id })
  } catch (e) {
    detail.value = { status: 'failed', error_message: e.message }
  } finally {
    detailLoading.value = false
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 920px; min-height: 460px; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: hidden; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.header-actions { display: flex; align-items: center; gap: 4px; }
.refresh-btn, .close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 6px; border-radius: 6px; transition: all 0.15s; display: flex; }
.refresh-btn:hover:not(:disabled), .close-btn:hover { color: #374151; background: #f3f4f6; }
.refresh-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.refresh-btn svg.spinning { animation: spin 0.7s linear infinite; }

.modal-filters { display: flex; align-items: flex-end; gap: 12px; padding: 12px 24px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0; }
.clear-dates { display: inline-flex; align-items: center; min-height: 36px; padding: 0 14px; border: 1px solid #e5e7eb; background: #fafafa; color: #6b7280; border-radius: 10px; font-size: 13px; font-weight: 500; cursor: pointer; }
.clear-dates:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }

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
.created-count { color: #047857; font-weight: 700; }
.muted { color: #d1d5db; }
.party-cell { color: #374151; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.by-cell, .date-cell { color: #6b7280; font-size: 12px; white-space: nowrap; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.badge.done { background: #dcfce7; color: #166534; }
.badge.busy { background: #fef9c3; color: #854d0e; }
.badge.bad { background: #fee2e2; color: #991b1b; }

.detail-row td { background: #fafafa; padding: 14px 16px 16px 32px; border-bottom: 1px solid #f3f4f6; }
.detail { display: flex; flex-direction: column; gap: 14px; }
.detail-summary { display: flex; flex-wrap: wrap; gap: 18px 28px; }
.sum-item { display: flex; flex-direction: column; gap: 3px; }
.sum-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; font-weight: 600; }
.sum-value { font-size: 13px; color: #111; display: inline-flex; align-items: center; gap: 6px; font-variant-numeric: tabular-nums; }
.sum-value.strong { font-weight: 700; }

.detail-block { display: flex; flex-direction: column; gap: 6px; }
.detail-title { font-size: 12px; color: #6b7280; margin: 0; font-weight: 600; }
.detail-clean { font-size: 12.5px; color: #9ca3af; }

.chips { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 6px; }
.chip { font-size: 12.5px; background: #ecfdf5; color: #065f46; border-radius: 8px; padding: 3px 10px; }
.chip-type { font-weight: 700; margin-right: 4px; }

.warns { margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 3px; }
.warns li { font-size: 12.5px; color: #9a3412; }

.items-table { background: #fff; border: 1px solid #f3f4f6; border-radius: 8px; overflow: hidden; }
.items-table thead th { position: static; box-shadow: none; padding: 8px 10px; }
.items-table td { padding: 8px 10px; border-bottom: 1px solid #f9fafb; color: #374151; }
.items-table tr:last-child td { border-bottom: none; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
