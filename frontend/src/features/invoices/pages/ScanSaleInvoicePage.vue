<template>
  <PageContainer :maxWidth="1100">
    <PageHeader title="Scan Sale Invoice" subtitle="Upload a PDF or photo and we'll prefill the invoice for you to review.">
      <template #actions>
        <button class="btn-secondary" @click="goToList">Cancel</button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentStore"
      title="No store selected"
      description="Select a store to scan an invoice."
    />

    <template v-else>
      <InactiveBanner v-if="!currentStore.is_active">
        This store is deactivated. You cannot create invoices until it is reactivated.
      </InactiveBanner>

      <!-- Step 1: upload -->
      <section v-if="phase === 'upload'" class="card upload-card">
        <h3 class="card-title">Upload invoice</h3>
        <label class="dropzone" :class="{ filled: !!file }">
          <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="file-input" @change="onFileChange" />
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <span v-if="file" class="dz-name">{{ file.name }}</span>
          <span v-else class="dz-hint">Click to choose a PDF or photo (JPG, PNG, WEBP)</span>
        </label>

        <FormMessage v-if="error" block :text="error" style="margin-top: 14px" />

        <div class="upload-actions">
          <button class="btn-primary" :disabled="!file || extracting || !currentStore.is_active" @click="extract">
            <span v-if="extracting" class="spinner"></span>
            {{ extracting ? 'Reading invoice…' : 'Extract' }}
          </button>
        </div>

        <ExtractionSourceBar
          :needs-ai="needsAi"
          :busy="extracting"
          style="margin-top: 14px"
          @scan-ai="scanWithAi"
        />

        <p class="upload-note">PDFs are read for free on our server; photos use AI. The file is read once and not stored.</p>
      </section>

      <!-- Step 2: review -->
      <template v-else>
        <ExtractionSourceBar
          :source="source"
          :suggest-ai="aiSuggested"
          :allow-ai="true"
          :busy="extracting"
          @scan-ai="scanWithAi"
        />

        <ReferenceChipsBanner
          :groups="bannerGroups"
          message="This invoice references records that don't exist yet. Create them here and they'll be linked."
          @create="onCreateRef"
        />

        <div v-if="review.warnings && review.warnings.length" class="warnings">
          <div v-for="(w, i) in review.warnings" :key="i" class="warn-row">{{ w }}</div>
        </div>

        <section class="card">
          <h3 class="card-title">Details</h3>
          <div class="details-grid">
            <div class="form-group">
              <label>Customer <span class="required">*</span></label>
              <div class="picker-row">
                <SearchableSelect
                  v-model="customerPartyId"
                  :options="customerOptions"
                  :allow-all="false"
                  placeholder="Select a customer"
                  search-placeholder="Search customers…"
                />
                <MatchBadge :matched="!!customerPartyId" />
              </div>
              <span v-if="extractedCustomerLabel" class="hint">Read: {{ extractedCustomerLabel }}</span>
            </div>

            <div class="form-group">
              <label>Invoice date</label>
              <input v-model="invoiceDate" type="date" class="text-input" />
            </div>

            <div v-if="review.invoice_no" class="form-group full">
              <label>Invoice number</label>
              <input :value="review.invoice_no" type="text" class="text-input" disabled />
              <span class="hint">Saved into the invoice description.</span>
            </div>
          </div>
        </section>

        <section class="card">
          <h3 class="card-title">Items</h3>
          <ResizableTable :columns="ITEM_COLUMNS" :initial-widths="ITEM_COL_WIDTHS">
            <tr v-for="(line, i) in lines" :key="i" class="item-row">
              <td class="c-idx">{{ i + 1 }}</td>
              <td class="c-product">
                <div class="picker-row">
                  <SearchableSelect
                    v-model="line.productId"
                    :options="productOptions"
                    :allow-all="false"
                    :teleport="true"
                    placeholder="Select a product"
                    search-placeholder="Search products…"
                  />
                  <MatchBadge :matched="!!line.productId" />
                </div>
                <span class="hint">Read: {{ line.extracted.name }}<template v-if="line.extracted.code"> · {{ line.extracted.code }}</template></span>
              </td>
              <td class="c-unit">
                <div class="unit-cell">
                  <span class="unit-name">{{ line.unitName || line.extracted.unit || '—' }}</span>
                  <MatchBadge v-if="line.extracted.unit" :matched="!!line.unitId" />
                </div>
                <span v-if="line.unitName && line.extracted.unit" class="hint">Read: {{ line.extracted.unit }}</span>
              </td>
              <td class="c-num">{{ formatQuantity(line.extracted.quantity) }}</td>
              <td class="c-num">{{ formatMoney(line.extracted.unit_price) }}</td>
              <td class="c-num">{{ line.extracted.tax_rate != null ? line.extracted.tax_rate + '%' : '—' }}</td>
              <td class="c-num strong">{{ formatMoney(line.extracted.line_total) }}</td>
            </tr>
          </ResizableTable>
          <p v-if="lines.length === 0" class="empty-note">No line items were read — add them on the next step.</p>
        </section>

        <section class="footer-bar">
          <div class="totals">
            <div class="total-row"><span>Subtotal</span><span>{{ formatMoney(review.subtotal) }}</span></div>
            <div class="total-row"><span>VAT</span><span>{{ formatMoney(review.vat_total) }}</span></div>
            <div class="total-row grand"><span>Grand total</span><span>{{ formatMoney(review.grand_total) }}</span></div>
          </div>
          <div class="actions">
            <button class="btn-secondary" @click="startOver">Start over</button>
            <button class="btn-primary" :disabled="!currentStore.is_active" @click="continueToInvoice">
              Continue to invoice
            </button>
          </div>
        </section>
      </template>
    </template>

    <CustomerFormModal
      v-if="showCustomerForm"
      :customer="null"
      :prefill-name="review.party.extracted.name || ''"
      :prefill-tax-code="review.party.extracted.tax_code || ''"
      :prefill-phone="review.party.extracted.phone || ''"
      :prefill-address="review.party.extracted.address || ''"
      @close="showCustomerForm = false"
      @saved="onCustomerCreated"
    />

    <ProductFormModal
      v-if="showProductForm"
      :product="null"
      :store-id="storeId"
      :prefill-name="pendingProductName"
      :prefill-unit-id="pendingProductUnitId"
      @close="closeProductForm"
      @saved="onProductCreated($event, true)"
      @pick-existing="onProductCreated($event, false)"
    />

    <UnitFormModal
      v-if="showUnitForm"
      :unit="null"
      :store-id="storeId"
      :prefill-name="pendingUnitName"
      @close="closeUnitForm"
      @saved="onUnitCreated($event, true)"
      @pick-existing="onUnitCreated($event, false)"
    />

    <ConfirmDialog
      v-if="showLeaveWarning"
      title="Discard scanned invoice?"
      message="This invoice has been scanned and is ready to review. If you leave now, the scanned data will be lost."
      confirm-text="Yes, discard"
      cancel-text="Keep reviewing"
      type="danger"
      @confirm="discardAndLeave"
      @cancel="keepReviewing"
    />
  </PageContainer>
</template>

<script setup>
import { ref, computed, inject, onMounted, watch } from 'vue'
import { useRouter, onBeforeRouteLeave } from 'vue-router'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import InactiveBanner from '@/components/common/InactiveBanner.vue'
import FormMessage from '@/components/common/FormMessage.vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import ReferenceChipsBanner from '@/components/common/ReferenceChipsBanner.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import ExtractionSourceBar from '@/features/invoices/components/ExtractionSourceBar.vue'
import MatchBadge from '@/features/invoices/components/MatchBadge.vue'
import CustomerFormModal from '@/features/customers/components/CustomerFormModal.vue'
import ProductFormModal from '@/features/products/components/ProductFormModal.vue'
import UnitFormModal from '@/features/units/components/UnitFormModal.vue'
import { fetchCustomers } from '@/features/customers/services/customerService'
import { fetchProducts } from '@/features/products/services/productService'
import { extractSaleInvoice } from '@/features/invoices/services/extractionService'
import { recordScanEntity } from '@/features/invoices/services/scanHistoryService'
import { setInvoiceDraft } from '@/features/invoices/services/invoiceDraft'
import { formatMoney, formatQuantity, todayInputDate } from '@/features/invoices/constants'
import { normalizeText } from '@/utils/textNormalizer'
import { ErrorCode } from '@/utils/errorCodes'

const ITEM_COLUMNS = [
  { key: 'idx', label: '#' },
  { key: 'product', label: 'Product' },
  { key: 'unit', label: 'Unit' },
  { key: 'quantity', label: 'Qty' },
  { key: 'price', label: 'Unit price' },
  { key: 'tax', label: 'VAT' },
  { key: 'total', label: 'Line total' },
]
const ITEM_COL_WIDTHS = [44, 300, 130, 80, 120, 70, 120]

const router = useRouter()
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const storeId = computed(() => currentStore.value?.id ?? null)
const businessId = computed(() => currentBusiness.value?.id ?? null)

const phase = ref('upload')
const file = ref(null)
const extracting = ref(false)
const error = ref('')
const source = ref('')
const aiSuggested = ref(false)
const needsAi = ref(false)

const review = ref(null)
const scanId = ref(null)
const customerPartyId = ref('')
const invoiceDate = ref('')
const lines = ref([])

const customers = ref([])
const products = ref([])

const showCustomerForm = ref(false)
const showProductForm = ref(false)
const pendingProductName = ref('')
const pendingProductUnitId = ref('')
const showUnitForm = ref(false)
const pendingUnitName = ref('')

const inCurrentStore = (c) => {
  const sid = String(storeId.value ?? '')
  return String(c.store_id) === sid || (c.stores || []).some((st) => String(st.id) === sid)
}

const customerOptions = computed(() =>
  customers.value
    .filter((c) => c.party?.id)
    .map((c) => ({ customer: c, store: inCurrentStore(c) }))
    .sort((a, b) => (a.store === b.store ? 0 : a.store ? -1 : 1))
    .map(({ customer, store }) => ({
      value: String(customer.party.id),
      label: customer.name,
      sublabel: [store ? 'This store' : 'Business', customer.phone || customer.tax_code].filter(Boolean).join(' · '),
    })),
)

const productOptions = computed(() =>
  products.value.map((p) => ({
    value: String(p.id),
    label: `${p.code} — ${p.name}`,
    sublabel: p.unit?.name || '',
  })),
)

const extractedCustomerLabel = computed(() => {
  const ex = review.value?.party?.extracted
  if (!ex?.name) return ''
  return [ex.name, ex.tax_code].filter(Boolean).join(' · ')
})

// Unmatched customer + products drive the "create new" banner (same UX as imports).
const bannerGroups = computed(() => {
  const groups = []
  const ex = review.value?.party?.extracted
  if (ex?.name && !customerPartyId.value) {
    groups.push({ type: 'customer', label: 'Customers', chips: [{ id: 'customer', label: ex.name }] })
  }
  const seen = new Map()
  for (const line of lines.value) {
    if (line.productId) continue
    const name = (line.extracted?.name || '').trim()
    if (!name) continue
    const id = name.toLowerCase()
    if (!seen.has(id)) seen.set(id, { id, label: name })
  }
  if (seen.size) groups.push({ type: 'product', label: 'Products', chips: [...seen.values()] })
  const unitSeen = new Map()
  for (const line of lines.value) {
    if (line.unitId) continue
    const name = (line.extracted?.unit || '').trim()
    if (!name) continue
    const id = normalizeText(name)
    if (!unitSeen.has(id)) unitSeen.set(id, { id, label: name })
  }
  if (unitSeen.size) groups.push({ type: 'unit', label: 'Units', chips: [...unitSeen.values()] })
  return groups
})

const loadOptions = async () => {
  if (!storeId.value) return
  const [customerList, productList] = await Promise.all([
    fetchCustomers({ storeId: storeId.value, businessId: businessId.value }),
    fetchProducts({ storeId: storeId.value }),
  ])
  customers.value = customerList
  products.value = productList
}

onMounted(loadOptions)
watch(storeId, loadOptions)

const onFileChange = (e) => {
  file.value = e.target.files?.[0] || null
  error.value = ''
  needsAi.value = false
}

const isPdf = (f) => f.type === 'application/pdf' || /\.pdf$/i.test(f.name || '')

const applyReview = (data, provider) => {
  review.value = data
  scanId.value = data.scan_id ?? null
  source.value = data.source || provider
  aiSuggested.value = !!data.suggest_ai
  customerPartyId.value = data.party?.match?.party_id ? String(data.party.match.party_id) : ''
  invoiceDate.value = data.invoice_date || todayInputDate()
  lines.value = (data.items || []).map((it) => ({
    extracted: it.extracted,
    taxMatch: it.tax_match,
    productId: it.match?.product_id ? String(it.match.product_id) : '',
    unitId: it.unit_match?.unit_id ? String(it.unit_match.unit_id) : '',
    unitName: it.unit_match?.name || '',
  }))
  phase.value = 'review'
}

const runExtraction = async (provider) => {
  if (!file.value || extracting.value) return
  extracting.value = true
  error.value = ''
  needsAi.value = false
  try {
    const data = await extractSaleInvoice({ storeId: storeId.value, file: file.value, provider })
    applyReview(data, provider)
  } catch (err) {
    // The free parser doesn't recognize this PDF — offer the AI scan instead of failing.
    if (err.code === ErrorCode.EXTRACTION_NEEDS_AI && provider !== 'gemini') {
      needsAi.value = true
    } else {
      error.value = err.message
    }
  } finally {
    extracting.value = false
  }
}

// PDFs try the free template parser first; photos go straight to AI.
const extract = () => runExtraction(file.value && isPdf(file.value) ? 'template' : 'gemini')

const scanWithAi = () => runExtraction('gemini')

// The unit already matched/created on a line for this product name, so a new
// product can inherit it instead of making the user re-pick.
const unitIdForProductName = (name) => {
  const key = name.trim().toLowerCase()
  const line = lines.value.find(
    (l) => (l.extracted?.name || '').trim().toLowerCase() === key && l.unitId,
  )
  return line ? line.unitId : ''
}

const onCreateRef = (type, chip) => {
  if (type === 'customer') {
    showCustomerForm.value = true
  } else if (type === 'product') {
    pendingProductName.value = chip.label
    pendingProductUnitId.value = unitIdForProductName(chip.label)
    showProductForm.value = true
  } else if (type === 'unit') {
    pendingUnitName.value = chip.label
    showUnitForm.value = true
  }
}

// Attribute an entity created during review back to this scan (best-effort — a
// logging failure must never interrupt the review flow).
const logScanEntity = (type, id, name) => {
  if (!scanId.value || id == null) return
  recordScanEntity({ storeId: storeId.value, scanId: scanId.value, type, id, name: name || '' }).catch(() => {})
}

const onCustomerCreated = async (customer) => {
  showCustomerForm.value = false
  await loadOptions()
  const partyId = customer?.party?.id
  if (partyId != null) customerPartyId.value = String(partyId)
  logScanEntity('customer', customer?.id ?? partyId, customer?.name)
}

const closeProductForm = () => {
  showProductForm.value = false
  pendingProductName.value = ''
  pendingProductUnitId.value = ''
}

const closeUnitForm = () => {
  showUnitForm.value = false
  pendingUnitName.value = ''
}

// Link a newly created (or picked) unit onto every still-unmatched line that read
// the same unit text, so those lines resolve and later product creation inherits it.
// `created` is false when an existing unit was picked, which is not a new record.
const onUnitCreated = (unit, created = true) => {
  const readLabel = pendingUnitName.value
  const normalized = normalizeText(readLabel)
  closeUnitForm()
  const id = unit?.id
  if (id == null) return
  if (created) logScanEntity('unit', id, unit.name || readLabel)
  for (const line of lines.value) {
    if (!line.unitId && normalizeText(line.extracted?.unit || '') === normalized) {
      line.unitId = String(id)
      line.unitName = unit.name || readLabel
    }
  }
}

const onProductCreated = async (product, created = true) => {
  const name = pendingProductName.value.trim().toLowerCase()
  closeProductForm()
  await loadOptions()
  const id = product?.id
  if (id == null || !name) return
  if (created) logScanEntity('product', id, product.name)
  for (const line of lines.value) {
    if (!line.productId && (line.extracted?.name || '').trim().toLowerCase() === name) {
      line.productId = String(id)
    }
  }
}

const continueToInvoice = () => {
  setInvoiceDraft({
    party_id: customerPartyId.value || '',
    invoice_date: invoiceDate.value || todayInputDate(),
    payment_method: 'CASH',
    payment_status: 'UNPAID',
    description: review.value?.invoice_no ? `Invoice ${review.value.invoice_no}` : '',
    items: lines.value.map((line) => ({
      product_id: line.productId || '',
      quantity: line.extracted.quantity != null ? String(line.extracted.quantity) : '',
      unit_price: line.extracted.unit_price != null ? String(line.extracted.unit_price) : '',
      taxes: line.taxMatch ? { [String(line.taxMatch.tax_id)]: String(line.taxMatch.rate) } : {},
    })),
  })
  leaveConfirmed.value = true
  router.push('/sale-invoices/new')
}

const startOver = () => {
  phase.value = 'upload'
  file.value = null
  review.value = null
  scanId.value = null
  lines.value = []
  customerPartyId.value = ''
  error.value = ''
  source.value = ''
  aiSuggested.value = false
  needsAi.value = false
}

const goToList = () => router.push('/sale-invoices')

// Once the invoice has been scanned and parsed, warn before leaving (Cancel,
// sidebar, browser back) so the reviewed data isn't lost. Continuing to the
// create page is exempt — it sets leaveConfirmed first.
const showLeaveWarning = ref(false)
const leaveConfirmed = ref(false)
const pendingTo = ref(null)

onBeforeRouteLeave((to) => {
  if (leaveConfirmed.value || phase.value !== 'review') return true
  pendingTo.value = to.fullPath
  showLeaveWarning.value = true
  return false
})

const discardAndLeave = () => {
  leaveConfirmed.value = true
  showLeaveWarning.value = false
  router.push(pendingTo.value || '/sale-invoices')
}

const keepReviewing = () => {
  showLeaveWarning.value = false
  pendingTo.value = null
}
</script>

<style scoped>
.card { background: #fff; border: 1px solid #eef0f2; border-radius: 14px; padding: 20px 24px; margin-bottom: 16px; }
.card-title { margin: 0 0 16px; font-size: 15px; font-weight: 700; color: #111; }

.upload-card { text-align: center; }
.dropzone { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 40px 20px; border: 2px dashed #d1d5db; border-radius: 12px; color: #6b7280; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
.dropzone:hover { border-color: #111; background: #fafafa; }
.dropzone.filled { border-color: #111; color: #111; }
.file-input { display: none; }
.dz-name { font-weight: 600; color: #111; }
.dz-hint { font-size: 13px; }
.upload-actions { margin-top: 16px; }
.upload-note { margin: 10px 0 0; font-size: 12px; color: #9ca3af; }

.warnings { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; }
.warn-row { font-size: 12.5px; color: #9a3412; }
.warn-row + .warn-row { margin-top: 4px; }

.details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 13px; font-weight: 500; color: #374151; }
.required { color: #dc2626; }
.hint { font-size: 12px; color: #6b7280; }
.picker-row { display: flex; align-items: center; gap: 8px; }
.picker-row > :first-child { flex: 1; min-width: 0; }

.text-input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; outline: none; box-sizing: border-box; }
.text-input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.text-input:disabled { background: #f9fafb; color: #6b7280; }

.item-row :deep(td) { padding: 8px 12px; vertical-align: top; }
.c-idx { color: #9ca3af; font-size: 13px; }
.c-unit { font-size: 13.5px; color: #111; }
.unit-cell { display: flex; align-items: center; gap: 6px; }
.unit-name { font-weight: 500; }
.c-num { text-align: right; font-variant-numeric: tabular-nums; font-size: 14px; color: #111; }
.c-num.strong { font-weight: 700; }
.empty-note { margin: 12px 0 0; font-size: 13px; color: #9ca3af; }

.footer-bar { display: flex; flex-direction: column; gap: 14px; align-items: flex-end; }
.totals { width: 280px; display: flex; flex-direction: column; gap: 8px; }
.total-row { display: flex; justify-content: space-between; font-size: 14px; color: #374151; font-variant-numeric: tabular-nums; }
.total-row.grand { padding-top: 8px; border-top: 1px solid #eef0f2; font-size: 16px; font-weight: 700; color: #111; }

.actions { display: flex; gap: 10px; }
.btn-secondary { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-secondary:hover:not(:disabled) { background: #e9eaec; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 22px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #333; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 720px) {
  .details-grid { grid-template-columns: 1fr; }
}
</style>
