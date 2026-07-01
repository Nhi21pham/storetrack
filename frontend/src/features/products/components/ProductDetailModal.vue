<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ $t('products.detailsTitle') }}</h2>
        <button class="close-btn" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="detail-row">
          <span class="detail-label">{{ $t('products.code') }}</span>
          <span class="detail-value code-text">{{ product.code }}</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('common.name') }}</span>
          <span class="detail-value name-text">{{ product.name }}</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('products.category') }}</span>
          <span v-if="product.category" class="detail-value">
            <span class="cat-code">{{ product.category.code }}</span> — {{ displayCategoryName(product.category) }}
          </span>
          <span v-else class="detail-value empty-val">—</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('products.unit') }}</span>
          <span v-if="product.unit?.name" class="detail-value">{{ product.unit.name }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('products.tags') }}</span>
          <span class="detail-value">
            <span v-if="product.tags && product.tags.length" class="tags-list">
              <TagChip v-for="(t, i) in product.tags" :key="i" :tag-name="t.tag_name" :value="t.value" />
            </span>
            <span v-else class="empty-val">—</span>
          </span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('common.status') }}</span>
          <span class="detail-value">
            <span class="status-pill" :class="product.is_active ? 'active' : 'inactive'">
              {{ product.is_active ? $t('common.active') : $t('common.inactive') }}
            </span>
          </span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('common.createdAt') }}</span>
          <span v-if="product.created_at" class="detail-value">{{ formatDateTime(product.created_at) }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">{{ $t('common.updatedAt') }}</span>
          <span v-if="product.updated_at" class="detail-value">{{ formatDateTime(product.updated_at) }}</span>
          <span v-else class="detail-value empty-val">—</span>
        </div>

        <div class="invoices-section">
          <button class="section-toggle" :aria-expanded="invoicesOpen" @click="toggleInvoices">
            <svg class="chevron" :class="{ open: invoicesOpen }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            {{ $t('products.invoicesSection') }}
          </button>

          <div v-if="invoicesOpen" class="section-body">
            <p v-if="loadingInvoices" class="section-hint">{{ $t('products.loadingInvoices') }}</p>
            <template v-else>
              <p v-if="!purchaseInvoices.length && !saleInvoices.length" class="section-hint">{{ $t('products.noInvoices') }}</p>
              <template v-else>
                <div v-if="purchaseInvoices.length" class="inv-group">
                  <div class="inv-group-title">{{ $t('products.purchaseInvoices') }}</div>
                  <div v-for="inv in purchaseInvoices" :key="inv.id" class="inv-row">
                    <button class="inv-code" @click="openInvoice(inv)">{{ inv.code }}</button>
                    <span class="inv-date">{{ formatInvoiceDate(inv.invoice_date) }}</span>
                  </div>
                </div>
                <div v-if="saleInvoices.length" class="inv-group">
                  <div class="inv-group-title">{{ $t('products.saleInvoices') }}</div>
                  <div v-for="inv in saleInvoices" :key="inv.id" class="inv-row">
                    <button class="inv-code" @click="openInvoice(inv)">{{ inv.code }}</button>
                    <span class="inv-date">{{ formatInvoiceDate(inv.invoice_date) }}</span>
                  </div>
                </div>
              </template>
            </template>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-close" @click="$emit('close')">{{ $t('common.close') }}</button>
        <button v-if="canEdit" class="btn-edit" @click="$emit('edit', product)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          {{ $t('common.edit') }}
        </button>
      </div>
    </div>
  </div>

  <InvoiceDetailModal
    v-if="viewingInvoice"
    :invoice="viewingInvoice"
    can-edit
    @edit="editInvoice"
    @close="viewingInvoice = null"
  />
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { formatDateTime } from '@/utils/datetime'
import { displayCategoryName } from '@/features/productCategories/constants'
import { translateError } from '@/utils/translateError'
import TagChip from '@/components/common/TagChip.vue'
import InvoiceDetailModal from '@/features/invoices/components/InvoiceDetailModal.vue'
import { INVOICE_TYPE, formatInvoiceDate } from '@/features/invoices/constants'
import { fetchInvoicesForProduct, fetchInvoice } from '@/features/invoices/services/invoiceService'

const props = defineProps({
  product: { type: Object, required: true },
  canEdit: { type: Boolean, default: true },
})

defineEmits(['close', 'edit'])

const invoicesOpen = ref(false)
const loadingInvoices = ref(false)
const invoicesLoaded = ref(false)
const invoices = ref([])
const viewingInvoice = ref(null)

const purchaseInvoices = computed(() => invoices.value.filter(inv => inv.type === INVOICE_TYPE.PURCHASE))
const saleInvoices = computed(() => invoices.value.filter(inv => inv.type === INVOICE_TYPE.SALE))

const toggleInvoices = async () => {
  invoicesOpen.value = !invoicesOpen.value
  if (!invoicesOpen.value || invoicesLoaded.value) return
  loadingInvoices.value = true
  try {
    invoices.value = await fetchInvoicesForProduct({ productId: props.product.id })
    invoicesLoaded.value = true
  } catch (err) {
    invoicesOpen.value = false
    alert(translateError(err))
  } finally {
    loadingInvoices.value = false
  }
}

const openInvoice = async (inv) => {
  try {
    viewingInvoice.value = await fetchInvoice({ id: inv.id })
  } catch (err) {
    alert(translateError(err))
  }
}

const router = useRouter()

const editInvoice = () => {
  const invoice = viewingInvoice.value
  const base = invoice.type === INVOICE_TYPE.SALE ? 'sale-invoices' : 'purchase-invoices'
  const href = router.resolve(`/${base}/${invoice.id}/edit`).href
  window.open(href, '_blank', 'noopener')
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 520px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: hidden; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; }

.detail-row { display: grid; grid-template-columns: 150px 1fr; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f3f4f6; align-items: baseline; }
.detail-row:last-child { border-bottom: none; }

.detail-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
.detail-value { font-size: 14px; color: #111; word-break: break-word; }

.name-text { font-weight: 700; font-size: 15px; }
.code-text { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #4338ca; font-size: 15px; }
.cat-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 600; color: #4338ca; }
.tags-list { display: flex; flex-wrap: wrap; gap: 6px; }
.empty-val { color: #d1d5db; }

.invoices-section { padding-top: 12px; }
.section-toggle { display: flex; align-items: center; gap: 6px; width: 100%; background: none; border: none; padding: 6px 0; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; cursor: pointer; }
.section-toggle:hover { color: #374151; }
.chevron { transition: transform 0.15s; }
.chevron.open { transform: rotate(90deg); }
.section-body { padding: 8px 0 4px; }
.section-hint { font-size: 13px; color: #9ca3af; padding: 4px 0; }
.inv-group + .inv-group { margin-top: 14px; }
.inv-group-title { font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; }
.inv-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px solid #f3f4f6; }
.inv-row:last-child { border-bottom: none; }
.inv-code { background: none; border: none; padding: 0; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #4338ca; font-size: 14px; cursor: pointer; }
.inv-code:hover { text-decoration: underline; }
.inv-date { font-size: 13px; color: #6b7280; white-space: nowrap; }

.status-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.status-pill.active { background: #ecfdf5; color: #047857; }
.status-pill.inactive { background: #f3f4f6; color: #6b7280; }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }

.btn-close { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-close:hover { background: #e9eaec; }

.btn-edit { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 6px; }
.btn-edit:hover { background: #333; }
</style>
