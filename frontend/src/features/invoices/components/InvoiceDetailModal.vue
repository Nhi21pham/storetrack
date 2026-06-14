<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <div class="title-wrap">
          <h2>{{ invoice.code }}</h2>
          <span class="type-badge" :class="{ sale: isSale }">{{ isSale ? 'Sale' : 'Purchase' }}</span>
        </div>
        <button class="close-btn" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="meta-grid">
          <div class="meta"><label>{{ isSale ? 'Customer' : 'Supplier' }}</label><span>{{ invoice.party_name || '—' }}</span></div>
          <div class="meta"><label>Invoice date</label><span>{{ formatInvoiceDate(invoice.invoice_date) }}</span></div>
          <div class="meta"><label>Payment method</label><span>{{ paymentMethodLabel(invoice.payment_method) }}</span></div>
          <div class="meta"><label>Status</label><PaymentStatusBadge :status="invoice.payment_status" /></div>
          <div v-if="invoice.description" class="meta full"><label>Description</label><span>{{ invoice.description }}</span></div>
        </div>

        <table class="items-table">
          <thead>
            <tr>
              <th>Product</th>
              <th class="num">Qty</th>
              <th class="num">{{ isSale ? 'Sale price' : 'Unit price' }}</th>
              <th>Taxes</th>
              <th class="num">Subtotal</th>
              <th v-if="isSale" class="num">Cost</th>
              <th class="num">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in invoice.items" :key="item.id">
              <td class="product">{{ item.product_name }}</td>
              <td class="num">{{ formatQuantity(item.quantity) }}</td>
              <td class="num">{{ formatMoney(item.unit_price) }}</td>
              <td>
                <span v-if="!item.taxes || item.taxes.length === 0" class="muted">—</span>
                <span v-for="t in item.taxes" :key="t.id" class="tax-chip">
                  {{ t.tax_name }} {{ trimRate(t.tax_rate) }}%
                </span>
              </td>
              <td class="num">{{ formatMoney(item.subtotal) }}</td>
              <td v-if="isSale" class="num cost-cell">
                <span class="cost-total">{{ formatMoney(item.cost_total) }}</span>
                <span v-for="c in (item.costs || [])" :key="c.id" class="cost-draw">
                  {{ formatQuantity(c.quantity) }} × {{ formatMoney(c.unit_cost) }}
                </span>
              </td>
              <td class="num strong">{{ formatMoney(item.grand_total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="modal-footer">
        <div class="footer-left">
          <button v-if="canManage || canEdit" class="btn-edit" @click="$emit('edit')">Edit</button>
          <button v-if="canManage" class="btn-delete" @click="$emit('delete')">Delete</button>
        </div>
        <div class="totals">
          <div class="t-row"><span>Subtotal</span><span>{{ formatMoney(invoice.subtotal) }}</span></div>
          <div class="t-row"><span>Tax</span><span>{{ formatMoney(invoice.tax_total) }}</span></div>
          <div class="t-row grand"><span>Grand total</span><span>{{ formatMoney(invoice.grand_total) }}</span></div>
          <div class="t-row"><span>Paid</span><span>{{ formatMoney(invoice.paid_amount) }}</span></div>
          <div class="t-row" :style="Number(invoice.balance) > 0 ? 'color:#b45309;font-weight:600' : null"><span>Balance</span><span>{{ formatMoney(invoice.balance) }}</span></div>
          <div v-if="isSale" class="t-row cogs"><span>Cost of goods</span><span>{{ formatMoney(costTotal) }}</span></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import PaymentStatusBadge from '@/features/invoices/components/PaymentStatusBadge.vue'
import {
  INVOICE_TYPE,
  formatMoney,
  formatQuantity,
  formatInvoiceDate,
  paymentMethodLabel,
} from '@/features/invoices/constants'

const props = defineProps({
  invoice: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
})

defineEmits(['close', 'edit', 'delete'])

const isSale = computed(() => props.invoice.type === INVOICE_TYPE.SALE)

const costTotal = computed(() =>
  (props.invoice.items || []).reduce((sum, it) => sum + Number(it.cost_total || 0), 0),
)

const trimRate = (rate) => String(parseFloat(rate))
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 760px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 24px 80px rgba(0,0,0,0.15); }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.title-wrap { display: flex; align-items: center; gap: 10px; }
.title-wrap h2 { font-size: 18px; font-weight: 700; color: #111; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.type-badge { padding: 3px 10px; border-radius: 999px; background: #eef2ff; color: #4338ca; font-size: 12px; font-weight: 600; }
.type-badge.sale { background: #ecfdf5; color: #047857; }
.cost-cell { vertical-align: top; }
.cost-total { display: block; }
.cost-draw { display: block; font-size: 11.5px; font-weight: 400; color: #9ca3af; font-variant-numeric: tabular-nums; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; overflow-y: auto; }

.meta-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 20px; }
.meta { display: flex; flex-direction: column; gap: 4px; }
.meta.full { grid-column: 1 / -1; }
.meta label { font-size: 12px; font-weight: 600; color: #9ca3af; }
.meta span { font-size: 14px; color: #111; }

.items-table { width: 100%; border-collapse: collapse; }
.items-table th { text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; padding: 0 8px 10px; border-bottom: 1px solid #f0f1f3; }
.items-table th.num { text-align: right; }
.items-table td { padding: 10px 8px; border-bottom: 1px solid #f6f7f8; font-size: 14px; color: #111; vertical-align: top; }
.items-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.items-table td.num.strong { font-weight: 700; }
.product { font-weight: 500; }
.muted { color: #9ca3af; }
.tax-chip { display: inline-block; margin: 0 4px 4px 0; padding: 2px 8px; border-radius: 6px; background: #f3f4f6; color: #374151; font-size: 12px; }

.modal-footer { padding: 16px 24px; border-top: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; }
.footer-left { display: flex; gap: 8px; }
.btn-edit { padding: 9px 18px; background: #f3f4f6; color: #111; border: none; border-radius: 9px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-edit:hover { background: #e9eaec; }
.btn-delete { padding: 9px 18px; background: #fff; color: #dc2626; border: 1px solid #fecaca; border-radius: 9px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-delete:hover { background: #fef2f2; }
.totals { width: 280px; display: flex; flex-direction: column; gap: 8px; }
.t-row { display: flex; justify-content: space-between; font-size: 14px; color: #374151; font-variant-numeric: tabular-nums; }
.t-row.grand { padding-top: 8px; border-top: 1px solid #eef0f2; font-size: 16px; font-weight: 800; color: #111; }
.t-row.cogs { padding-top: 8px; border-top: 1px solid #eef0f2; color: #6b7280; }
</style>
