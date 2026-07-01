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
      <div v-else-if="!rows.length" class="empty-state">{{ $t('invoices.noScans') }}</div>

      <div v-else class="table-wrap">
        <table class="history-table">
          <thead>
            <tr>
              <th class="file-col">{{ $t('shared.file') }}</th>
              <th>{{ $t('invoices.scanType') }}</th>
              <th>{{ $t('shared.status') }}</th>
              <th class="num">{{ $t('invoices.items') }}</th>
              <th class="num">{{ $t('invoices.newCol') }}</th>
              <th>{{ partyLabel }}</th>
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
                <td class="party-cell" v-tooltip="row.party_name || ''">{{ row.party_name || '—' }}</td>
                <td class="by-cell">{{ row.user_name || row.user_email || '—' }}</td>
                <td class="date-cell">{{ formatDateTime(row.created_at) }}</td>
              </tr>
              <tr v-if="expandedId === row.id" class="detail-row">
                <td colspan="8">
                  <div v-if="detailLoading" class="state-row sm"><div class="spinner"></div><span>{{ $t('shared.loadingDetails') }}</span></div>
                  <div v-else-if="detail?.status === 'failed'" class="api-error">
                    {{ detail.error_message || $t('invoices.scanFailed') }}
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
                        <span class="sum-label">{{ $t('invoices.invoiceNo') }}</span>
                        <span class="sum-value">{{ detail.invoice_no }}</span>
                      </div>
                      <div class="sum-item">
                        <span class="sum-label">{{ $t('invoices.subtotal') }}</span>
                        <span class="sum-value">{{ formatMoney(detail.totals?.subtotal) }}</span>
                      </div>
                      <div class="sum-item">
                        <span class="sum-label">{{ $t('invoices.vat') }}</span>
                        <span class="sum-value">{{ formatMoney(detail.totals?.vat_total) }}</span>
                      </div>
                      <div class="sum-item">
                        <span class="sum-label">{{ $t('invoices.grandTotal') }}</span>
                        <span class="sum-value strong">{{ formatMoney(detail.totals?.grand_total) }}</span>
                      </div>
                    </div>

                    <div v-if="detail.created_entities?.length" class="detail-block">
                      <p class="detail-title">{{ $t('invoices.newRecordsCreated') }}</p>
                      <ul class="chips">
                        <li v-for="(e, i) in detail.created_entities" :key="i" class="chip">
                          <span class="chip-type">{{ entityTypeLabel(e.type) }}</span> {{ e.name }}
                        </li>
                      </ul>
                    </div>

                    <div v-if="detail.warnings?.length" class="detail-block">
                      <p class="detail-title">{{ $t('invoices.warnings') }}</p>
                      <ul class="warns">
                        <li v-for="(w, i) in detail.warnings" :key="i">{{ w }}</li>
                      </ul>
                    </div>

                    <div class="detail-block">
                      <p class="detail-title">{{ $t('invoices.itemsReadCount', { count: detail.items?.length || 0 }) }}</p>
                      <div v-if="!detail.items?.length" class="detail-clean">{{ $t('invoices.noLineItems') }}</div>
                      <table v-else class="items-table">
                        <thead>
                          <tr>
                            <th>{{ $t('invoices.product') }}</th>
                            <th>{{ $t('invoices.unit') }}</th>
                            <th class="num">{{ $t('invoices.qty') }}</th>
                            <th class="num">{{ $t('invoices.unitPrice') }}</th>
                            <th class="num">{{ $t('invoices.lineTotal') }}</th>
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
import { t } from '@/i18n'

const props = defineProps({
  storeId: { type: [String, Number], required: true },
  // Invoice kind whose scans to show: 'purchase' | 'sale'.
  type: { type: String, required: true },
})

// A purchase scan reads the seller (supplier); a sale scan reads the buyer (customer).
const partyLabel = computed(() => (props.type === 'sale' ? t('invoices.customer') : t('invoices.supplier')))

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

const entityTypeLabel = (type) => ({
  supplier: t('invoices.supplier'),
  customer: t('invoices.customer'),
  product: t('invoices.product'),
  unit: t('invoices.unit'),
}[type] || type)

// Scans are synchronous, so a record is only ever completed or failed; the
// pending/processing fallback is kept defensive in case that ever changes.
const statusInfo = (status) => {
  switch (status) {
    case 'completed': return { label: t('invoices.scanStatus.successful'), cls: 'done' }
    case 'failed':    return { label: t('invoices.scanStatus.failed'), cls: 'bad' }
    default:          return { label: t('invoices.scanStatus.processing'), cls: 'busy' }
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
.data-row { cursor: pointer; }
.data-row.open :deep(td) { background: #f9fafb; }
.chevron { color: #9ca3af; transition: transform 0.15s; vertical-align: middle; margin-right: 4px; }
.chevron.open { transform: rotate(90deg); }
.created-count { color: #047857; font-weight: 700; }
.party-cell { color: #374151; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

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

.items-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #f3f4f6; border-radius: 8px; overflow: hidden; }
.items-table thead th { position: static; box-shadow: none; padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; }
.items-table th.num, .items-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.items-table td { padding: 8px 10px; border-bottom: 1px solid #f9fafb; color: #374151; font-size: 13px; }
.items-table tr:last-child td { border-bottom: none; }
</style>
