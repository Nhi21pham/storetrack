<template>
  <PageContainer :maxWidth="1100">
    <PageHeader :title="isEdit ? 'Edit Purchase Invoice' : 'New Purchase Invoice'" subtitle="Record a purchase from a supplier and add it to stock.">
      <template #actions>
        <button class="btn-secondary" @click="goBack">Cancel</button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentStore"
      title="No store selected"
      description="Select a store to create a purchase invoice."
    />

    <template v-else>
      <InactiveBanner v-if="!currentStore.is_active">
        This store is deactivated. You cannot create invoices until it is reactivated.
      </InactiveBanner>

      <LoadingState v-if="optionsLoading">Loading suppliers, products and taxes…</LoadingState>

      <template v-else>
        <!-- Details -->
        <section class="card">
          <h3 class="card-title">Details</h3>
          <div class="details-grid">
            <div ref="partyFieldRef" class="form-group">
              <label>Supplier <span class="required">*</span></label>
              <div class="picker-row">
                <SearchableSelect
                  v-model="form.party_id"
                  :options="supplierOptions"
                  :allow-all="false"
                  placeholder="Select a supplier"
                  search-placeholder="Search suppliers…"
                />
                <AddItemButton title="Create a new supplier" @click="showSupplierForm = true" />
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
                </td>
                <td class="c-qty">
                  <NumberInput v-model="item.quantity" :decimals="3" class="num-input" placeholder="0" />
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

        <SupplierFormModal
          v-if="showSupplierForm"
          :supplier="null"
          @close="showSupplierForm = false"
          @saved="onSupplierCreated"
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
import InvoiceLineTaxEditor from '@/features/invoices/components/InvoiceLineTaxEditor.vue'
import SupplierFormModal from '@/features/suppliers/components/SupplierFormModal.vue'
import ProductFormModal from '@/features/products/components/ProductFormModal.vue'
import { fetchSuppliers } from '@/features/suppliers/services/supplierService'
import { fetchProducts } from '@/features/products/services/productService'
import { fetchTaxes } from '@/features/taxes/services/taxService'
import { createPurchaseInvoice, updatePurchaseInvoice, fetchInvoice } from '@/features/invoices/services/invoiceService'
import { takeInvoiceDraft } from '@/features/invoices/services/invoiceDraft'
import {
  PAYMENT_METHODS,
  PAYMENT_STATUSES,
  formatMoney,
  todayInputDate,
} from '@/features/invoices/constants'

const ITEM_COLUMNS = [
  { key: 'idx',      label: '#' },
  { key: 'product',  label: 'Product' },
  { key: 'quantity', label: 'Qty' },
  { key: 'price',    label: 'Unit price' },
  { key: 'taxes',    label: 'Taxes' },
  { key: 'subtotal', label: 'Subtotal' },
  { key: 'total',    label: 'Total' },
  { key: 'remove',   label: '' },
]
const ITEM_COL_WIDTHS = [44, 300, 100, 130, 170, 120, 120, 48]

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
const suppliers = ref([])
const products = ref([])
const taxes = ref([])

const submitting = ref(false)
const loadedPayment = ref({ paid: 0, balance: 0 })
const apiError = ref('')
const errors = ref({ party_id: '', invoice_date: '', items: '', paid_amount: '' })

const showSupplierForm = ref(false)
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

const inCurrentStore = (s) => {
  const sid = String(storeId.value ?? '')
  return String(s.store_id) === sid || (s.stores || []).some((st) => String(st.id) === sid)
}

// Suppliers are business-wide; surface the ones linked to this store first and
// tag each option so the scope (store vs business) is clear in the picker.
const supplierOptions = computed(() =>
  suppliers.value
    .filter((s) => s.party?.id)
    .map((s) => ({ supplier: s, store: inCurrentStore(s) }))
    .sort((a, b) => (a.store === b.store ? 0 : a.store ? -1 : 1))
    .map(({ supplier, store }) => ({
      value: String(supplier.party.id),
      label: supplier.name,
      sublabel: [store ? 'This store' : 'Business', supplier.phone || supplier.tax_code].filter(Boolean).join(' · '),
    })),
)

const productOptions = computed(() =>
  products.value.map((p) => ({
    value: String(p.id),
    label: `${p.code} — ${p.name}`,
    sublabel: p.unit?.name || '',
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

const loadOptions = async () => {
  if (!storeId.value) return
  optionsLoading.value = true
  try {
    const [supplierList, productList, taxList] = await Promise.all([
      fetchSuppliers({ storeId: storeId.value, businessId: businessId.value }),
      fetchProducts({ storeId: storeId.value }),
      fetchTaxes({ storeId: storeId.value }),
    ])
    suppliers.value = supplierList
    products.value = productList
    taxes.value = taxList
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
  if (!form.value.party_id) errors.value.party_id = 'Supplier is required.'
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
      invoice = await updatePurchaseInvoice({ id: invoiceId.value, input })
      showToast(`Purchase invoice ${invoice.code} updated.`, 'success')
    } else {
      invoice = await createPurchaseInvoice({ storeId: storeId.value, input })
      showToast(`Purchase invoice ${invoice.code} created.`, 'success')
    }
    leaveConfirmed.value = true
    router.push('/purchase-invoices')
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

const onSupplierCreated = async (supplier) => {
  showSupplierForm.value = false
  if (storeId.value) {
    suppliers.value = await fetchSuppliers({ storeId: storeId.value, businessId: businessId.value })
  }
  const partyId = supplier?.party?.id
  if (partyId != null) form.value.party_id = String(partyId)
}

const onProductCreated = async (product) => {
  const index = productFormLine.value
  closeProductForm()
  if (storeId.value) {
    products.value = await fetchProducts({ storeId: storeId.value })
  }
  if (product?.id != null && index != null && items.value[index]) {
    items.value[index].product_id = String(product.id)
  }
}

const goBack = () => router.push('/purchase-invoices')

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
  router.push(pendingTo.value || '/purchase-invoices')
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

/* Editable item rows inside ResizableTable: tighten the cell padding and top-align. */
.item-row :deep(td) { padding: 8px 12px; vertical-align: top; }
.c-idx { color: #9ca3af; font-size: 13px; }
.c-num { text-align: right; font-variant-numeric: tabular-nums; font-size: 14px; color: #111; }
.c-num.strong { font-weight: 700; }
.c-rm { text-align: center; }

.num-input { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; text-align: right; outline: none; box-sizing: border-box; }
.num-input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }

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
