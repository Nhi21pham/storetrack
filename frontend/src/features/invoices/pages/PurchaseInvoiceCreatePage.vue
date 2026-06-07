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
            <div class="form-group">
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
              <span v-if="errors.party_id" class="error-text">{{ errors.party_id }}</span>
            </div>

            <div class="form-group">
              <label>Invoice date <span class="required">*</span></label>
              <input v-model="form.invoice_date" type="date" class="text-input" :class="{ error: errors.invoice_date }" />
              <span v-if="errors.invoice_date" class="error-text">{{ errors.invoice_date }}</span>
            </div>

            <div class="form-group">
              <label>Payment method</label>
              <SelectField v-model="form.payment_method">
                <option v-for="m in PAYMENT_METHODS" :key="m.value" :value="m.value">{{ m.label }}</option>
              </SelectField>
            </div>

            <div class="form-group">
              <label>Payment status</label>
              <SelectField v-model="form.payment_status">
                <option v-for="s in PAYMENT_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
              </SelectField>
            </div>

            <div class="form-group full">
              <label>Description</label>
              <input v-model="form.description" type="text" class="text-input" placeholder="Optional note for this invoice" />
            </div>
          </div>
        </section>

        <!-- Items -->
        <section class="card">
          <h3 class="card-title">Items</h3>
          <div class="items-wrap">
            <table class="items-table">
              <thead>
                <tr>
                  <th class="col-idx">#</th>
                  <th>Product</th>
                  <th class="col-qty">Qty</th>
                  <th class="col-price">Unit price</th>
                  <th class="col-tax">Taxes</th>
                  <th class="col-num">Subtotal</th>
                  <th class="col-num">Total</th>
                  <th class="col-rm"></th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(item, i) in items" :key="i">
                  <tr class="item-row">
                    <td class="col-idx">{{ i + 1 }}</td>
                    <td>
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
                    <td class="col-qty">
                      <input v-model="item.quantity" type="number" min="0" step="0.001" class="num-input" placeholder="0" />
                    </td>
                    <td class="col-price">
                      <input v-model="item.unit_price" type="number" min="0" step="0.01" class="num-input" placeholder="0.00" />
                    </td>
                    <td class="col-tax">
                      <button type="button" class="tax-toggle" :class="{ active: item.expanded }" @click="item.expanded = !item.expanded">
                        <span class="tax-summary">{{ taxSummary(item) }}</span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                      </button>
                    </td>
                    <td class="col-num">{{ formatMoney(lineSubtotal(item)) }}</td>
                    <td class="col-num strong">{{ formatMoney(lineTotal(item)) }}</td>
                    <td class="col-rm">
                      <button type="button" class="remove-btn" title="Remove item" @click="removeItem(i)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="item.expanded" class="tax-expand-row">
                    <td></td>
                    <td colspan="7">
                      <InvoiceLineTaxEditor
                        v-model="item.taxes"
                        :store-taxes="activeTaxes"
                        :line-subtotal="lineSubtotal(item)"
                      />
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <button type="button" class="add-item" @click="addItem">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add item
          </button>
          <span v-if="errors.items" class="error-text">{{ errors.items }}</span>
        </section>

        <!-- Totals + submit -->
        <section class="footer-bar">
          <div class="totals">
            <div class="total-row"><span>Subtotal</span><span>{{ formatMoney(totals.subtotal) }}</span></div>
            <div class="total-row"><span>Tax</span><span>{{ formatMoney(totals.tax) }}</span></div>
            <div class="total-row grand"><span>Grand total</span><span>{{ formatMoney(totals.grand) }}</span></div>
          </div>
          <div v-if="apiError" class="api-error">{{ apiError }}</div>
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
import { ref, computed, inject, onMounted, watch } from 'vue'
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
import InvoiceLineTaxEditor from '@/features/invoices/components/InvoiceLineTaxEditor.vue'
import SupplierFormModal from '@/features/suppliers/components/SupplierFormModal.vue'
import ProductFormModal from '@/features/products/components/ProductFormModal.vue'
import { fetchSuppliers } from '@/features/suppliers/services/supplierService'
import { fetchProducts } from '@/features/products/services/productService'
import { fetchTaxes } from '@/features/taxes/services/taxService'
import { createPurchaseInvoice, updatePurchaseInvoice, fetchInvoice } from '@/features/invoices/services/invoiceService'
import {
  PAYMENT_METHODS,
  PAYMENT_STATUSES,
  formatMoney,
  todayInputDate,
} from '@/features/invoices/constants'

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
const apiError = ref('')
const errors = ref({ party_id: '', invoice_date: '', items: '' })

const showSupplierForm = ref(false)
const showProductForm = ref(false)
const productFormLine = ref(null)

const showUnsavedWarning = ref(false)
const leaveConfirmed = ref(false)
const pendingTo = ref(null)

const newItem = () => ({ product_id: '', quantity: '', unit_price: '', taxes: {}, expanded: false })

const form = ref({
  party_id: '',
  invoice_date: todayInputDate(),
  payment_method: 'CASH',
  payment_status: 'UNPAID',
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
    items.value = (inv.items || []).map((it) => ({
      product_id: String(it.product_id),
      quantity: String(it.quantity),
      unit_price: String(it.unit_price),
      taxes: Object.fromEntries((it.taxes || []).map((t) => [String(t.tax_id), String(parseFloat(t.tax_rate))])),
      expanded: false,
    }))
    if (items.value.length === 0) items.value = [newItem()]
  } catch (err) {
    apiError.value = err.message
  }
}

onMounted(async () => {
  await loadOptions()
  if (isEdit.value) await loadInvoice()
  baseline.value = snapshot()
})
watch(storeId, loadOptions)

const validate = () => {
  errors.value = { party_id: '', invoice_date: '', items: '' }
  if (!form.value.party_id) errors.value.party_id = 'Supplier is required.'
  if (!form.value.invoice_date) errors.value.invoice_date = 'Invoice date is required.'

  const valid = items.value.filter(
    (it) => it.product_id && Number(it.quantity) > 0 && Number(it.unit_price) >= 0,
  )
  if (valid.length === 0) {
    errors.value.items = 'Add at least one item with a product, quantity and price.'
  }
  return !errors.value.party_id && !errors.value.invoice_date && !errors.value.items
}

const buildInput = () => ({
  party_id: form.value.party_id,
  invoice_date: form.value.invoice_date,
  payment_method: form.value.payment_method,
  payment_status: form.value.payment_status,
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
  if (!validate()) return

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
.picker-row { display: flex; align-items: stretch; gap: 8px; }
.picker-row > :first-child { flex: 1; min-width: 0; }
.hint { margin: 2px 0 0; font-size: 12px; color: #6b7280; }
.error-text { font-size: 12px; color: #dc2626; }

.text-input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; outline: none; box-sizing: border-box; transition: border-color 0.15s; }
.text-input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.text-input.error { border-color: #dc2626; }

.items-wrap { overflow-x: auto; }
.items-table { width: 100%; border-collapse: collapse; }
.items-table th { text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; padding: 0 10px 10px; border-bottom: 1px solid #f0f1f3; }
.items-table th.col-num { text-align: right; }
.items-table td { padding: 8px 10px; vertical-align: middle; }
.item-row { border-bottom: 1px solid #f6f7f8; }
.col-idx { width: 32px; color: #9ca3af; font-size: 13px; }
.col-qty { width: 90px; }
.col-price { width: 120px; }
.col-tax { width: 170px; }
.col-num { width: 110px; text-align: right; font-variant-numeric: tabular-nums; font-size: 14px; color: #111; }
.col-num.strong { font-weight: 700; }
.col-rm { width: 36px; }

.num-input { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; text-align: right; outline: none; box-sizing: border-box; }
.num-input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }

.tax-toggle { display: flex; align-items: center; justify-content: space-between; gap: 6px; width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; font-size: 13px; color: #374151; cursor: pointer; }
.tax-toggle:hover { border-color: #9ca3af; }
.tax-toggle.active { border-color: #111; }
.tax-summary { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.remove-btn { display: inline-flex; padding: 6px; border: none; background: none; color: #9ca3af; border-radius: 6px; cursor: pointer; }
.remove-btn:hover { color: #dc2626; background: #fef2f2; }

.tax-expand-row td { padding: 0 10px 12px; }

.add-item { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; padding: 9px 14px; border: 1px dashed #d1d5db; border-radius: 10px; background: #fff; font-size: 13px; font-weight: 600; color: #374151; cursor: pointer; }
.add-item:hover { border-color: #111; color: #111; }

.footer-bar { display: flex; flex-direction: column; gap: 14px; align-items: flex-end; }
.totals { width: 280px; display: flex; flex-direction: column; gap: 8px; }
.total-row { display: flex; justify-content: space-between; font-size: 14px; color: #374151; font-variant-numeric: tabular-nums; }
.total-row.grand { padding-top: 8px; border-top: 1px solid #eef0f2; font-size: 16px; font-weight: 700; color: #111; }

.api-error { width: 100%; background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; }

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
