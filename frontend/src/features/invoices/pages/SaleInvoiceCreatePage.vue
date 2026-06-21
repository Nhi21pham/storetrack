<template>
  <PageContainer :maxWidth="1100">
    <PageHeader :title="isEdit ? 'Edit Sale Invoice' : 'New Sale Invoice'" subtitle="Record a sale to a customer and draw it from stock.">
      <template #actions>
        <button class="btn-secondary" @click="goBack">Cancel</button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentStore"
      title="No store selected"
      description="Select a store to create a sale invoice."
    />

    <template v-else>
      <InactiveBanner v-if="!currentStore.is_active">
        This store is deactivated. You cannot create invoices until it is reactivated.
      </InactiveBanner>

      <LoadingState v-if="optionsLoading">Loading customers, products and taxes…</LoadingState>

      <template v-else>
        <!-- Details -->
        <section class="card">
          <h3 class="card-title">Details</h3>
          <div class="details-grid">
            <div ref="partyFieldRef" class="form-group">
              <label>Customer <span class="required">*</span></label>
              <div class="picker-row">
                <SearchableSelect
                  v-model="form.party_id"
                  :options="customerOptions"
                  :allow-all="false"
                  placeholder="Select a customer"
                  search-placeholder="Search customers…"
                />
                <AddItemButton title="Create a new customer" @click="showCustomerForm = true" />
              </div>
              <FormMessage v-if="errors.party_id" :text="errors.party_id" />
            </div>

            <div class="form-group">
              <label>Invoice date <span class="required">*</span></label>
              <input ref="dateInputRef" v-model="form.invoice_date" type="date" class="text-input" :class="{ error: errors.invoice_date }" />
              <FormMessage v-if="errors.invoice_date" :text="errors.invoice_date" />
            </div>

            <div class="form-group">
              <label>Payment method</label>
              <SelectField v-model="form.payment_method">
                <option v-for="m in PAYMENT_METHODS" :key="m.value" :value="m.value">{{ m.label }}</option>
              </SelectField>
            </div>

            <div v-if="!isEdit" class="form-group">
              <label>Payment status</label>
              <SelectField v-model="form.payment_status">
                <option v-for="s in PAYMENT_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
              </SelectField>
            </div>

            <div v-if="!isEdit && form.payment_status === 'PARTIAL'" class="form-group">
              <label>Amount paid <span class="required">*</span></label>
              <NumberInput v-model="form.paid_amount" :decimals="2" placeholder="0" class="text-input" />
              <FormMessage v-if="errors.paid_amount" :text="errors.paid_amount" />
              <span v-else class="pay-hint">{{ remainingLabel }}</span>
            </div>

            <div v-if="isEdit" class="form-group">
              <label>Payment status</label>
              <div class="pay-readonly">
                <PaymentStatusBadge :status="derivedStatus" />
                <span class="pay-hint">Paid {{ formatMoney(loadedPayment.paid) }} · Balance {{ formatMoney(editBalance) }}</span>
                <InvoicePaymentLink
                  party-type="customer"
                  :party-id="form.party_id"
                  :invoice-id="invoiceId"
                  :status="derivedStatus"
                />
              </div>
            </div>

            <div class="form-group full">
              <label>Description</label>
              <input v-model="form.description" type="text" class="text-input" placeholder="Optional note for this invoice" />
            </div>
          </div>
        </section>

        <!-- Items -->
        <section ref="itemsSectionRef" class="card">
          <h3 class="card-title">Items</h3>

          <FormMessage v-if="apiError" block :text="apiError" style="margin-bottom: 14px" />

          <ResizableTable :columns="ITEM_COLUMNS" :initial-widths="ITEM_COL_WIDTHS">
            <template v-for="(item, i) in items" :key="i">
              <tr class="item-row">
                <td class="c-idx">{{ i + 1 }}</td>
                <td class="c-product">
                  <div class="picker-row">
                    <SearchableSelect
                      v-model="item.product_id"
                      :options="productOptions"
                      :allow-all="false"
                      :teleport="true"
                      placeholder="Select a product"
                      search-placeholder="Search products…"
                    />
                    <AddItemButton size="small" title="Create a new product" @click="openProductForm(i)" />
                  </div>
                  <span v-if="item.product_id" class="stock-hint" :class="{ warn: exceedsStock(item) }">
                    {{ stockHint(item) }}
                  </span>
                </td>
                <td class="c-qty">
                  <NumberInput v-model="item.quantity" :decimals="3" class="num-input" :class="{ error: exceedsStock(item) }" placeholder="0" />
                </td>
                <td class="c-price">
                  <NumberInput v-model="item.unit_price" :decimals="2" class="num-input" placeholder="0" />
                </td>
                <td class="c-tax">
                  <button type="button" class="tax-toggle" :class="{ active: item.expanded }" @click="item.expanded = !item.expanded">
                    <span class="tax-summary">{{ taxSummary(item) }}</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                  </button>
                </td>
                <td class="c-cost">
                  <template v-if="item.product_id">
                    <div v-for="(b, bi) in costLinesFor(item.product_id)" :key="bi" class="cost-line">
                      <span class="cl-amount">{{ formatQuantity(b.remaining) }} × {{ formatMoney(b.unit_cost) }}</span>
                      <span class="cl-date">{{ formatInvoiceDate(b.received_at) }}</span>
                    </div>
                    <span v-if="costLinesFor(item.product_id).length === 0" class="muted">No stock</span>
                  </template>
                  <span v-else class="muted">—</span>
                </td>
                <td class="c-num">{{ formatMoney(lineSubtotal(item)) }}</td>
                <td class="c-num strong">{{ formatMoney(lineTotal(item)) }}</td>
                <td class="c-rm">
                  <button type="button" class="remove-btn" title="Remove item" @click="removeItem(i)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  </button>
                </td>
              </tr>
              <tr v-if="item.expanded" class="tax-expand-row">
                <td :colspan="ITEM_COLUMNS.length">
                  <InvoiceLineTaxEditor
                    v-model="item.taxes"
                    :store-taxes="activeTaxes"
                    :line-subtotal="lineSubtotal(item)"
                  />
                </td>
              </tr>
            </template>
          </ResizableTable>

          <div class="items-footer">
            <button type="button" class="add-item" @click="addItem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add item
            </button>
            <FormMessage v-if="errors.items" :text="errors.items" />
          </div>
          <FormMessage
            v-if="hasStockWarning"
            block
            type="warning"
            style="margin-top: 10px"
            text="Some lines exceed available stock. You can still try to save, but the sale will be rejected if stock is insufficient."
          />
        </section>

        <!-- Totals + submit -->
        <section class="footer-bar">
          <div class="totals">
            <div class="total-row"><span>Subtotal</span><span>{{ formatMoney(totals.subtotal) }}</span></div>
            <div class="total-row"><span>Tax</span><span>{{ formatMoney(totals.tax) }}</span></div>
            <div class="total-row grand"><span>Grand total</span><span>{{ formatMoney(totals.grand) }}</span></div>
          </div>
          <div class="actions">
            <button class="btn-secondary" :disabled="submitting" @click="goBack">Cancel</button>
            <button class="btn-primary" :disabled="submitting || !currentStore.is_active" @click="submit">
              <span v-if="submitting" class="spinner"></span>
              {{ isEdit ? 'Save changes' : 'Create invoice' }}
            </button>
          </div>
        </section>

        <CustomerFormModal
          v-if="showCustomerForm"
          :customer="null"
          @close="showCustomerForm = false"
          @saved="onCustomerCreated"
        />

        <ProductFormModal
          v-if="showProductForm"
          :product="null"
          :store-id="storeId"
          @close="closeProductForm"
          @saved="onProductCreated"
          @pick-existing="onProductCreated"
        />
      </template>
    </template>

    <ConfirmDialog
      v-if="showUnsavedWarning"
      title="Discard changes?"
      message="You have unsaved changes. If you leave now, they'll be lost."
      confirm-text="Yes, discard"
      cancel-text="Keep editing"
      type="danger"
      @confirm="discardAndLeave"
      @cancel="keepEditing"
    />
  </PageContainer>
</template>

<script setup>
import { ref, computed, inject, onMounted, watch, nextTick } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import InactiveBanner from '@/components/common/InactiveBanner.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import SelectField from '@/components/common/SelectField.vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import AddItemButton from '@/components/common/AddItemButton.vue'
import NumberInput from '@/components/common/NumberInput.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import FormMessage from '@/components/common/FormMessage.vue'
import PaymentStatusBadge from '@/features/invoices/components/PaymentStatusBadge.vue'
import InvoicePaymentLink from '@/features/invoices/components/InvoicePaymentLink.vue'
import InvoiceLineTaxEditor from '@/features/invoices/components/InvoiceLineTaxEditor.vue'
import CustomerFormModal from '@/features/customers/components/CustomerFormModal.vue'
import ProductFormModal from '@/features/products/components/ProductFormModal.vue'
import { fetchCustomers } from '@/features/customers/services/customerService'
import { fetchProducts } from '@/features/products/services/productService'
import { fetchTaxes } from '@/features/taxes/services/taxService'
import { createSaleInvoice, updateSaleInvoice, fetchInvoice, fetchProductStocks, fetchInventoryBatches } from '@/features/invoices/services/invoiceService'
import { takeInvoiceDraft } from '@/features/invoices/services/invoiceDraft'
import {
  PAYMENT_METHODS,
  PAYMENT_STATUSES,
  formatMoney,
  formatQuantity,
  formatInvoiceDate,
  todayInputDate,
} from '@/features/invoices/constants'

const ITEM_COLUMNS = [
  { key: 'idx',      label: '#' },
  { key: 'product',  label: 'Product' },
  { key: 'quantity', label: 'Qty' },
  { key: 'price',    label: 'Sale price' },
  { key: 'taxes',    label: 'Taxes' },
  { key: 'cost',     label: 'Cost' },
  { key: 'subtotal', label: 'Subtotal' },
  { key: 'total',    label: 'Total' },
  { key: 'remove',   label: '' },
]
const ITEM_COL_WIDTHS = [44, 270, 90, 120, 150, 168, 110, 110, 48]

const router = useRouter()
const route = useRoute()
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')
const showToast = inject('showToast', () => {})

const storeId = computed(() => currentStore.value?.id ?? null)
const businessId = computed(() => currentBusiness.value?.id ?? null)
const invoiceId = computed(() => route.params.id ?? null)
const isEdit = computed(() => !!invoiceId.value)

const optionsLoading = ref(false)
const customers = ref([])
const products = ref([])
const taxes = ref([])
const stockByProduct = ref({})
const originalByProduct = ref({})
const originalBatchInfo = ref({})
const batchesByProduct = ref({})

const submitting = ref(false)
const loadedPayment = ref({ paid: 0, balance: 0 })
const apiError = ref('')
const errors = ref({ party_id: '', invoice_date: '', items: '', paid_amount: '' })

const showCustomerForm = ref(false)
const showProductForm = ref(false)
const productFormLine = ref(null)

const showUnsavedWarning = ref(false)
const leaveConfirmed = ref(false)
const pendingTo = ref(null)

const partyFieldRef = ref(null)
const dateInputRef = ref(null)
const itemsSectionRef = ref(null)

const newItem = () => ({ product_id: '', quantity: '', unit_price: '', taxes: {}, expanded: false })

// Drop trailing zeros from a stored decimal ("8.000" -> "8") for a clean edit field.
const trimNumber = (v) => {
  const n = Number(v)
  return Number.isFinite(n) ? String(n) : String(v ?? '')
}

const form = ref({
  party_id: '',
  invoice_date: todayInputDate(),
  payment_method: 'CASH',
  payment_status: 'UNPAID',
  paid_amount: '',
  description: '',
})
const items = ref([newItem()])

// Dirty tracking — compare only user data, not the per-row `expanded` UI state.
const snapshot = () =>
  JSON.stringify({
    form: form.value,
    items: items.value.map((it) => ({
      product_id: it.product_id,
      quantity: it.quantity,
      unit_price: it.unit_price,
      taxes: it.taxes,
    })),
  })
const baseline = ref(snapshot())
const isDirty = computed(() => snapshot() !== baseline.value)

const activeTaxes = computed(() => taxes.value.filter((t) => t.is_active))

const inCurrentStore = (c) => {
  const sid = String(storeId.value ?? '')
  return String(c.store_id) === sid || (c.stores || []).some((st) => String(st.id) === sid)
}

// Customers are business-wide; surface the ones linked to this store first and
// tag each option so the scope (store vs business) is clear in the picker.
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

// The most a sale may draw on: current on-hand plus, when editing, this invoice's
// own consumption — the edit reverses it before re-applying the new lines.
const availableForProduct = (productId) => {
  const id = String(productId)
  return Number(stockByProduct.value[id] || 0) + Number(originalByProduct.value[id] || 0)
}

// The product's open FIFO batches (quantity remaining + unit cost), for the cost column.
const batchesFor = (productId) => batchesByProduct.value[String(productId)] || []

// Total quantity the form is requesting per product (a product may span lines).
const requestedByProduct = computed(() => {
  const map = {}
  for (const it of items.value) {
    if (!it.product_id) continue
    map[it.product_id] = (map[it.product_id] || 0) + Number(it.quantity || 0)
  }
  return map
})

const exceedsStock = (item) => {
  if (!item.product_id) return false
  return (requestedByProduct.value[item.product_id] || 0) > availableForProduct(item.product_id)
}

// On-hand the warehouse keeps once this sale's requested quantity is drawn — a live
// figure that counts down as the user types, so it matches the resulting stock.
const remainingAfterSale = (productId) =>
  availableForProduct(productId) - (requestedByProduct.value[productId] || 0)

// The FIFO batches for the cost column, each showing what it holds after this sale's
// requested quantity is drawn (oldest first). On edit the invoice's own draw is
// restored first — including any batch it had fully emptied — so the figures stay in
// step with the In-stock hint and update as lines change.
const costLinesFor = (productId) => {
  const open = batchesFor(productId)
  const openIds = new Set(open.map((b) => String(b.id)))
  const info = originalBatchInfo.value

  const released = Object.entries(info)
    .filter(([id]) => !openIds.has(id))
    .map(([id, b]) => ({ id: Number(id), remaining: b.quantity, unit_cost: b.unit_cost, received_at: null }))
    .sort((a, b) => a.id - b.id)

  const restored = open.map((b) => ({
    ...b,
    remaining: b.remaining + (info[String(b.id)]?.quantity || 0),
  }))

  let outstanding = Math.max(requestedByProduct.value[productId] || 0, 0)
  const lines = []
  for (const b of [...released, ...restored]) {
    const taken = Math.min(b.remaining, outstanding)
    outstanding -= taken
    const remaining = b.remaining - taken
    if (remaining > 0.0001) lines.push({ ...b, remaining })
  }
  return lines
}

const stockHint = (item) => {
  if (exceedsStock(item)) return `Only ${formatQuantity(availableForProduct(item.product_id))} in stock`
  return `In stock: ${formatQuantity(remainingAfterSale(item.product_id))}`
}

const hasStockWarning = computed(() => items.value.some((it) => exceedsStock(it)))

const productOptions = computed(() =>
  products.value.map((p) => ({
    value: String(p.id),
    label: `${p.code} — ${p.name}`,
    sublabel: [p.unit?.name, `In stock: ${formatQuantity(Math.max(0, remainingAfterSale(p.id)))}`].filter(Boolean).join(' · '),
  })),
)

const taxName = (id) => activeTaxes.value.find((t) => String(t.id) === String(id))?.name || 'Tax'

const appliedTaxes = (item) =>
  Object.entries(item.taxes).filter(([, rate]) => rate !== '' && !Number.isNaN(Number(rate)))

const taxSummary = (item) => {
  const applied = appliedTaxes(item)
  if (applied.length === 0) return 'Add taxes'
  const [firstId, firstRate] = applied[0]
  const head = `${taxName(firstId)} ${firstRate}%`
  return applied.length > 1 ? `${head} +${applied.length - 1}` : head
}

const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100

const lineSubtotal = (item) => round2(Number(item.quantity || 0) * Number(item.unit_price || 0))

const lineTax = (item) => {
  const sub = lineSubtotal(item)
  return round2(appliedTaxes(item).reduce((sum, [, rate]) => sum + (sub * Number(rate)) / 100, 0))
}

const lineTotal = (item) => round2(lineSubtotal(item) + lineTax(item))

const totals = computed(() => {
  const subtotal = round2(items.value.reduce((s, it) => s + lineSubtotal(it), 0))
  const tax = round2(items.value.reduce((s, it) => s + lineTax(it), 0))
  return { subtotal, tax, grand: round2(subtotal + tax) }
})

const remaining = computed(() => round2(totals.value.grand - (Number(form.value.paid_amount) || 0)))
const remainingLabel = computed(() =>
  remaining.value >= 0
    ? `Remaining: ${formatMoney(remaining.value)}`
    : `Overpaid by ${formatMoney(-remaining.value)}`,
)

// On edit the paid amount is fixed (managed on the Payments page); the status/balance
// re-derive live from it vs the current total, matching what the backend will store.
const editBalance = computed(() => round2(totals.value.grand - loadedPayment.value.paid))
const derivedStatus = computed(() => {
  const paid = loadedPayment.value.paid
  if (paid <= 0) return 'UNPAID'
  return paid >= totals.value.grand ? 'PAID' : 'PARTIAL'
})

const addItem = () => items.value.push(newItem())

const removeItem = (i) => {
  items.value.splice(i, 1)
  if (items.value.length === 0) items.value.push(newItem())
}

const buildStockMap = (stocks) =>
  Object.fromEntries((stocks || []).map((s) => [String(s.product_id), Number(s.quantity)]))

// Group open batches by product, preserving the FIFO order they arrive in.
const buildBatchMap = (batches) => {
  const map = {}
  for (const b of batches || []) {
    const id = String(b.product_id)
    ;(map[id] ||= []).push({
      id: Number(b.id),
      remaining: Number(b.quantity_remaining),
      unit_cost: Number(b.unit_cost),
      received_at: b.received_at,
    })
  }
  return map
}


const loadOptions = async () => {
  if (!storeId.value) return
  optionsLoading.value = true
  try {
    const [customerList, productList, taxList, stockList, batchList] = await Promise.all([
      fetchCustomers({ storeId: storeId.value, businessId: businessId.value }),
      fetchProducts({ storeId: storeId.value }),
      fetchTaxes({ storeId: storeId.value }),
      fetchProductStocks({ storeId: storeId.value }),
      fetchInventoryBatches({ storeId: storeId.value }),
    ])
    customers.value = customerList
    products.value = productList
    taxes.value = taxList
    stockByProduct.value = buildStockMap(stockList)
    batchesByProduct.value = buildBatchMap(batchList)
  } catch (err) {
    apiError.value = err.message
  } finally {
    optionsLoading.value = false
  }
}

const loadInvoice = async () => {
  try {
    const inv = await fetchInvoice({ id: invoiceId.value })
    if (!inv) return
    form.value = {
      party_id: String(inv.party_id),
      invoice_date: String(inv.invoice_date).slice(0, 10),
      payment_method: inv.payment_method,
      payment_status: inv.payment_status,
      description: inv.description || '',
    }
    loadedPayment.value = { paid: Number(inv.paid_amount || 0), balance: Number(inv.balance || 0) }
    items.value = (inv.items || []).map((it) => ({
      product_id: String(it.product_id),
      quantity: trimNumber(it.quantity),
      unit_price: trimNumber(it.unit_price),
      taxes: Object.fromEntries((it.taxes || []).map((t) => [String(t.tax_id), String(parseFloat(t.tax_rate))])),
      expanded: false,
    }))
    // Stock this invoice already consumed — re-allocatable while editing it.
    originalByProduct.value = (inv.items || []).reduce((map, it) => {
      const id = String(it.product_id)
      map[id] = (map[id] || 0) + Number(it.quantity || 0)
      return map
    }, {})
    // The same consumption broken down per FIFO batch, so the cost column can show
    // each batch as it'll be after this invoice releases its draw on save.
    originalBatchInfo.value = (inv.items || []).reduce((map, it) => {
      for (const c of it.costs || []) {
        const id = String(c.inventory_batch_id)
        if (!map[id]) map[id] = { quantity: 0, unit_cost: Number(c.unit_cost) }
        map[id].quantity += Number(c.quantity || 0)
      }
      return map
    }, {})
    if (items.value.length === 0) items.value = [newItem()]
  } catch (err) {
    apiError.value = err.message
  }
}

// Prefill from a draft handed over by the Scan / review page (AI extraction).
// Identities are already resolved there; here we just seed the form + lines.
const applyDraft = () => {
  const draft = takeInvoiceDraft()
  if (!draft) return false
  form.value = {
    party_id: draft.party_id || '',
    invoice_date: draft.invoice_date || todayInputDate(),
    payment_method: draft.payment_method || 'CASH',
    payment_status: draft.payment_status || 'UNPAID',
    paid_amount: draft.paid_amount || '',
    description: draft.description || '',
  }
  const lines = (draft.items || []).map((it) => ({
    product_id: it.product_id || '',
    quantity: it.quantity ?? '',
    unit_price: it.unit_price ?? '',
    taxes: it.taxes || {},
    expanded: false,
  }))
  items.value = lines.length ? lines : [newItem()]
  return true
}

onMounted(async () => {
  await loadOptions()
  if (isEdit.value) {
    await loadInvoice()
    baseline.value = snapshot()
  } else if (!applyDraft()) {
    baseline.value = snapshot()
  }
  // When prefilled from a scan draft, the pristine baseline is kept on purpose so
  // the scanned data registers as unsaved — leaving (Cancel/back) then prompts to discard.
})
watch(storeId, loadOptions)

// Clear each field's error the moment it becomes valid, so the warning disappears
// as soon as the user fixes it.
watch(() => form.value.party_id, (v) => { if (v) errors.value.party_id = '' })
watch(() => form.value.invoice_date, (v) => { if (v) errors.value.invoice_date = '' })
watch(items, () => {
  if (!errors.value.items) return
  const hasValid = items.value.some((it) => it.product_id && Number(it.quantity) > 0 && Number(it.unit_price) >= 0)
  if (hasValid) errors.value.items = ''
}, { deep: true })

watch(() => [form.value.payment_status, form.value.paid_amount], () => {
  if (errors.value.paid_amount) errors.value.paid_amount = ''
})

// On a failed submit, bring the first invalid field into view.
const scrollToError = async () => {
  await nextTick()
  if (errors.value.party_id && partyFieldRef.value) {
    partyFieldRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' })
  } else if (errors.value.invoice_date && dateInputRef.value) {
    dateInputRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' })
    dateInputRef.value.focus()
  } else if (errors.value.items && itemsSectionRef.value) {
    itemsSectionRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
}

const validate = () => {
  errors.value = { party_id: '', invoice_date: '', items: '', paid_amount: '' }
  if (!form.value.party_id) errors.value.party_id = 'Customer is required.'
  if (!form.value.invoice_date) errors.value.invoice_date = 'Invoice date is required.'

  const valid = items.value.filter(
    (it) => it.product_id && Number(it.quantity) > 0 && Number(it.unit_price) >= 0,
  )
  if (valid.length === 0) {
    errors.value.items = 'Add at least one item with a product, quantity and price.'
  }

  if (!isEdit.value && form.value.payment_status === 'PARTIAL') {
    const amt = Number(form.value.paid_amount)
    if (!(amt > 0)) {
      errors.value.paid_amount = 'Enter how much was paid.'
    } else if (amt >= totals.value.grand) {
      errors.value.paid_amount = 'Partial amount must be less than the grand total.'
    }
  }

  return !errors.value.party_id && !errors.value.invoice_date && !errors.value.items && !errors.value.paid_amount
}

const buildInput = () => ({
  party_id: form.value.party_id,
  invoice_date: form.value.invoice_date,
  payment_method: form.value.payment_method,
  payment_status: form.value.payment_status,
  paid_amount: Number(form.value.paid_amount) || 0,
  description: form.value.description?.trim() || null,
  items: items.value
    .filter((it) => it.product_id && Number(it.quantity) > 0)
    .map((it) => ({
      product_id: it.product_id,
      quantity: Number(it.quantity),
      unit_price: Number(it.unit_price),
      taxes: appliedTaxes(it).map(([tax_id, rate]) => ({ tax_id, rate: Number(rate) })),
    })),
})

const submit = async () => {
  apiError.value = ''
  if (!validate()) {
    scrollToError()
    return
  }

  submitting.value = true
  try {
    const input = buildInput()
    let invoice
    if (isEdit.value) {
      invoice = await updateSaleInvoice({ id: invoiceId.value, input })
      showToast(`Sale invoice ${invoice.code} updated.`, 'success')
    } else {
      invoice = await createSaleInvoice({ storeId: storeId.value, input })
      showToast(`Sale invoice ${invoice.code} created.`, 'success')
    }
    leaveConfirmed.value = true
    router.push('/sale-invoices')
  } catch (err) {
    apiError.value = err.message
    await nextTick()
    itemsSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  } finally {
    submitting.value = false
  }
}

const openProductForm = (index) => {
  productFormLine.value = index
  showProductForm.value = true
}

const closeProductForm = () => {
  showProductForm.value = false
  productFormLine.value = null
}

const onCustomerCreated = async (customer) => {
  showCustomerForm.value = false
  if (storeId.value) {
    customers.value = await fetchCustomers({ storeId: storeId.value, businessId: businessId.value })
  }
  const partyId = customer?.party?.id
  if (partyId != null) form.value.party_id = String(partyId)
}

const onProductCreated = async (product) => {
  const index = productFormLine.value
  closeProductForm()
  if (storeId.value) {
    const [productList, stockList, batchList] = await Promise.all([
      fetchProducts({ storeId: storeId.value }),
      fetchProductStocks({ storeId: storeId.value }),
      fetchInventoryBatches({ storeId: storeId.value }),
    ])
    products.value = productList
    stockByProduct.value = buildStockMap(stockList)
    batchesByProduct.value = buildBatchMap(batchList)
  }
  if (product?.id != null && index != null && items.value[index]) {
    items.value[index].product_id = String(product.id)
  }
}

const goBack = () => router.push('/sale-invoices')

// Prompt on any exit (Cancel, sidebar, browser back) while the form is dirty.
onBeforeRouteLeave((to) => {
  if (leaveConfirmed.value || !isDirty.value) return true
  pendingTo.value = to.fullPath
  showUnsavedWarning.value = true
  return false
})

const discardAndLeave = () => {
  leaveConfirmed.value = true
  showUnsavedWarning.value = false
  router.push(pendingTo.value || '/sale-invoices')
}

const keepEditing = () => {
  showUnsavedWarning.value = false
  pendingTo.value = null
}
</script>

<style scoped>
.card { background: #fff; border: 1px solid #eef0f2; border-radius: 14px; padding: 20px 24px; margin-bottom: 16px; }
.card-title { margin: 0 0 16px; font-size: 15px; font-weight: 700; color: #111; }

.details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 13px; font-weight: 500; color: #374151; }
.required { color: #dc2626; }
.pay-hint { font-size: 12px; color: #6b7280; }
.pay-readonly { display: flex; align-items: center; gap: 10px; min-height: 40px; }
.picker-row { display: flex; align-items: stretch; gap: 8px; }
.picker-row > :first-child { flex: 1; min-width: 0; }

.text-input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; outline: none; box-sizing: border-box; transition: border-color 0.15s; }
.text-input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.text-input.error { border-color: #dc2626; }

/* Editable item rows inside ResizableTable: tighten the cell padding and top-align
   so the per-row stock hint / batch costs extend downward without misaligning inputs. */
.item-row :deep(td) { padding: 8px 12px; vertical-align: top; }
.c-idx { color: #9ca3af; font-size: 13px; }
.c-num { text-align: right; font-variant-numeric: tabular-nums; font-size: 14px; color: #111; }
.c-num.strong { font-weight: 700; }
.c-cost { text-align: right; }
.cost-line { margin-bottom: 6px; }
.cost-line:last-child { margin-bottom: 0; }
.cl-amount { display: block; font-size: 12px; color: #374151; font-variant-numeric: tabular-nums; white-space: nowrap; }
.cl-date { display: block; font-size: 10.5px; color: #9ca3af; white-space: nowrap; }
.c-cost .muted, .c-product .muted { color: #9ca3af; }
.c-rm { text-align: center; }

.stock-hint { display: block; margin-top: 4px; font-size: 11.5px; color: #6b7280; }
.stock-hint.warn { color: #dc2626; font-weight: 600; }

.num-input { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; text-align: right; outline: none; box-sizing: border-box; }
.num-input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.num-input.error { border-color: #dc2626; }

.tax-toggle { display: flex; align-items: center; justify-content: space-between; gap: 6px; width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; font-size: 13px; color: #374151; cursor: pointer; }
.tax-toggle:hover { border-color: #9ca3af; }
.tax-toggle.active { border-color: #111; }
.tax-summary { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.remove-btn { display: inline-flex; padding: 6px; border: none; background: none; color: #9ca3af; border-radius: 6px; cursor: pointer; }
.remove-btn:hover { color: #dc2626; background: #fef2f2; }

.tax-expand-row :deep(td) { padding: 0 12px 14px; background: #fff; }

.items-footer { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-top: 14px; }
.add-item { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border: 1px dashed #d1d5db; border-radius: 10px; background: #fff; font-size: 13px; font-weight: 600; color: #374151; cursor: pointer; }
.add-item:hover { border-color: #111; color: #111; }

.footer-bar { display: flex; flex-direction: column; gap: 14px; align-items: flex-end; }
.totals { width: 280px; display: flex; flex-direction: column; gap: 8px; }
.total-row { display: flex; justify-content: space-between; font-size: 14px; color: #374151; font-variant-numeric: tabular-nums; }
.total-row.grand { padding-top: 8px; border-top: 1px solid #eef0f2; font-size: 16px; font-weight: 700; color: #111; }

.actions { display: flex; gap: 10px; }
.btn-secondary { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-secondary:hover:not(:disabled) { background: #e9eaec; }
.btn-secondary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 22px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: #333; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 720px) {
  .details-grid { grid-template-columns: 1fr; }
}
</style>
