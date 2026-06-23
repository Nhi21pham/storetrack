<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ $t('payments.recordPayment') }}</h2>
        <button class="close-btn" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <p class="party-line">{{ $t('payments.for') }} <strong>{{ partyName }}</strong></p>

        <div class="alloc">
          <div class="alloc-head">
            <span class="c-check"></span>
            <span class="c-inv">{{ $t('payments.invoice') }}</span>
            <span class="c-num">{{ $t('payments.balance') }}</span>
            <span class="c-num">{{ $t('payments.pay') }}</span>
          </div>
          <label v-for="row in rows" :key="row.id" class="alloc-row" :class="{ on: row.selected }">
            <input class="c-check" type="checkbox" v-model="row.selected" @change="onToggle(row)" />
            <span class="c-inv">
              <span class="code">{{ row.code }}</span>
              <span class="date">{{ formatDate(row.invoice_date) }}</span>
            </span>
            <span class="c-num balance">{{ formatMoney(row.balance) }}</span>
            <span class="c-num">
              <NumberInput
                class="amount-input"
                :decimals="0"
                v-model="row.amount"
                :disabled="!row.selected"
                @update:model-value="clampRow(row)"
              />
            </span>
          </label>
          <p v-if="rows.length === 0" class="empty">{{ $t('payments.noOpenInvoicesToPay') }}</p>
        </div>

        <div class="total-line">
          <span>{{ $t('payments.total') }}</span>
          <span class="total-amount">{{ formatMoney(total) }}</span>
        </div>

        <div class="form-grid">
          <div class="field">
            <label>{{ $t('payments.date') }}</label>
            <input type="date" v-model="paidAt" />
          </div>
          <div class="field">
            <label>{{ $t('payments.method') }}</label>
            <select v-model="method">
              <option v-for="m in paymentMethodOptions()" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label>{{ $t('payments.note') }}</label>
          <textarea v-model="note" rows="2" :placeholder="$t('payments.notePlaceholder')"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-close" @click="$emit('close')">{{ $t('common.cancel') }}</button>
        <button class="btn-save" :disabled="!canSubmit || saving" @click="submit">
          {{ saving ? $t('payments.recording') : $t('payments.recordPayment') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject } from 'vue'
import { formatMoney, formatInvoiceDate as formatDate, paymentMethodOptions, PAYMENT_METHOD_VALUES, todayInputDate } from '@/features/invoices/constants'
import { recordPayment } from '@/features/payments/services/paymentService'
import NumberInput from '@/components/common/NumberInput.vue'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

const props = defineProps({
  storeId: { type: [String, Number], required: true },
  partyId: { type: [String, Number], required: true },
  partyName: { type: String, default: '' },
  openInvoices: { type: Array, required: true },
  preselectInvoiceId: { type: [String, Number], default: null },
})
const emit = defineEmits(['close', 'saved'])
const showToast = inject('showToast')

const rows = ref(props.openInvoices.map((inv) => {
  const preselected = props.preselectInvoiceId != null && String(inv.id) === String(props.preselectInvoiceId)
  return {
    id: inv.id,
    code: inv.code,
    invoice_date: inv.invoice_date,
    balance: Number(inv.balance),
    selected: preselected,
    amount: preselected ? Number(inv.balance) : '',
  }
}))
const paidAt = ref(todayInputDate())
const method = ref(PAYMENT_METHOD_VALUES[0])
const note = ref('')
const saving = ref(false)

const onToggle = (row) => {
  if (row.selected && (row.amount === '' || Number(row.amount) <= 0)) {
    row.amount = row.balance
  } else if (!row.selected) {
    row.amount = ''
  }
}

const clampRow = (row) => {
  if (Number(row.amount) > row.balance) row.amount = row.balance
}

const total = computed(() =>
  rows.value.reduce((sum, r) => sum + (r.selected ? Number(r.amount) || 0 : 0), 0),
)

const canSubmit = computed(() =>
  rows.value.some((r) => r.selected && Number(r.amount) > 0),
)

const submit = async () => {
  if (!canSubmit.value || saving.value) return
  const allocations = rows.value
    .filter((r) => r.selected && Number(r.amount) > 0)
    .map((r) => ({ invoice_id: r.id, amount: Number(r.amount) }))
  saving.value = true
  try {
    await recordPayment({
      storeId: props.storeId,
      input: {
        party_id: props.partyId,
        paid_at: paidAt.value,
        method: method.value,
        note: note.value || null,
        allocations,
      },
    })
    showToast(t('payments.paymentRecorded'))
    emit('saved')
  } catch (err) {
    showToast(translateError(err), 'error')
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 16px; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 560px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: hidden; display: flex; flex-direction: column; max-height: 90vh; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 16px 24px; overflow-y: auto; }
.party-line { font-size: 14px; color: #4b5563; margin-bottom: 14px; }
.party-line strong { color: #111; }

.alloc { border: 1px solid #eef0f2; border-radius: 10px; overflow: hidden; }
.alloc-head, .alloc-row { display: grid; grid-template-columns: 32px 1fr 130px 130px; align-items: center; gap: 8px; padding: 8px 12px; }
.alloc-head { background: #f9fafb; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
.alloc-row { border-top: 1px solid #f3f4f6; cursor: pointer; }
.alloc-row.on { background: #f0f7ff; }
.c-num { text-align: right; }
.c-inv { display: flex; flex-direction: column; }
.c-inv .code { font-family: monospace; font-size: 13px; font-weight: 600; color: #111; }
.c-inv .date { font-size: 11.5px; color: #9ca3af; }
.balance { font-size: 13px; color: #374151; }
.amount-input { width: 120px; text-align: right; padding: 6px 8px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 13px; font-family: inherit; }
.amount-input:disabled { background: #f9fafb; color: #d1d5db; }
.empty { padding: 14px; text-align: center; color: #9ca3af; font-size: 13px; }

.total-line { display: flex; justify-content: space-between; align-items: center; padding: 14px 4px 4px; font-size: 14px; font-weight: 600; color: #111; }
.total-amount { font-size: 18px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
.field { display: flex; flex-direction: column; gap: 6px; margin-top: 12px; }
.field label { font-size: 12px; font-weight: 600; color: #6b7280; }
.field input, .field select, .field textarea { padding: 9px 11px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-family: inherit; color: #111; }
.field textarea { resize: vertical; }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }
.btn-close { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-close:hover { background: #e9eaec; }
.btn-save { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-save:hover:not(:disabled) { background: #333; }
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
