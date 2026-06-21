<template>
  <PageContainer :maxWidth="1000">
    <PageHeader title="Payments" subtitle="Record customer and supplier payments against their open invoices." />

    <EmptyState
      v-if="!currentStore && !isBusinessOwner"
      title="Pick a store"
      description="Payments are recorded per store — switch to a specific store from the store switcher."
    >
      <template #icon>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
        </svg>
      </template>
    </EmptyState>

    <template v-else>
      <InactiveBanner v-if="!currentStore">
        Business level — balances and payments across every store. Switch to a specific store to record a payment.
      </InactiveBanner>
      <InactiveBanner v-else-if="!currentStore.is_active">
        This store is deactivated. Payments are read-only until the store is reactivated.
      </InactiveBanner>

      <div class="picker-bar">
        <SegmentedToggle
          :modelValue="partyType"
          :options="[{ value: 'customer', label: 'Customers' }, { value: 'supplier', label: 'Suppliers' }]"
          @update:modelValue="setPartyType"
        />
        <div class="picker-select">
          <SearchableSelect
            v-model="selectedPartyId"
            :options="partyOptions"
            :allow-all="false"
            :placeholder="partyType === 'customer' ? 'Select a customer…' : 'Select a supplier…'"
            search-placeholder="Search…"
            size="large"
            @change="onPartyChange"
          />
        </div>
      </div>

      <LoadingState v-if="loadingParties">Loading {{ partyType }}s...</LoadingState>

      <EmptyState
        v-else-if="!selectedPartyId"
        title="Pick a party"
        :description="`Select a ${partyType} to see their open invoices and record a payment.`"
      />

      <LoadingState v-else-if="loadingDetail">Loading...</LoadingState>

      <template v-else>
        <div class="summary">
          <div class="summary-item">
            <span class="summary-label">Outstanding</span>
            <span class="summary-value" :class="{ owed: outstanding > 0 }">{{ formatMoney(outstanding) }}</span>
          </div>
          <button
            v-if="currentStore && currentStore.is_active"
            class="btn-record"
            :disabled="openInvoices.length === 0"
            @click="openRecord()"
          >
            + Record Payment
          </button>
        </div>

        <section class="block">
          <h3>Open invoices</h3>
          <table v-if="openInvoices.length" class="data-table">
            <thead>
              <tr><th>Code</th><th>Date</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th></tr>
            </thead>
            <tbody>
              <tr v-for="inv in openInvoices" :key="inv.id">
                <td class="mono">
                  <button class="code-link" @click="openInvoiceDetail(inv.id)">{{ inv.code }}</button>
                  <span v-if="!currentStore && inv.store" class="store-tag">{{ inv.store.name }}</span>
                </td>
                <td>{{ formatDate(inv.invoice_date) }}</td>
                <td class="num">{{ formatMoney(inv.grand_total) }}</td>
                <td class="num">{{ formatMoney(inv.paid_amount) }}</td>
                <td class="num strong">{{ formatMoney(inv.balance) }}</td>
                <td><PaymentStatusBadge :status="inv.payment_status" /></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="empty">No open invoices — nothing outstanding.</p>
        </section>

        <section class="block">
          <h3>Payment history</h3>
          <table v-if="payments.length" class="data-table">
            <thead>
              <tr><th>Date</th><th>Method</th><th>Applied to</th><th class="num">Amount</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="p in pagedPayments" :key="p.id">
                <td>{{ formatDateTime(p.paid_at) }}</td>
                <td>{{ paymentMethodLabel(p.method) }}</td>
                <td class="applied">
                  <span v-for="a in p.allocations" :key="a.id" class="alloc">
                    <button class="code-link" @click="openInvoiceDetail(a.invoice_id)">{{ a.invoice?.code }}</button>
                    <span v-if="!currentStore && a.invoice?.store" class="store-tag">{{ a.invoice.store.name }}</span>
                  </span>
                </td>
                <td class="num strong">{{ formatMoney(p.amount) }}</td>
                <td class="num">
                  <button v-if="canDeletePayment && (!currentStore || currentStore.is_active)" class="link-danger" @click="confirmDelete(p)">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
          <Pagination
            v-if="paymentsTotal > 0"
            :current-page="paymentsPage"
            :total-pages="paymentsTotalPages"
            :total="paymentsTotal"
            :per-page="paymentsPerPage"
            @update:current-page="paymentsPage = $event"
            @update:per-page="setPaymentsPerPage"
          />
          <p v-else class="empty">No payments recorded yet.</p>
        </section>
      </template>
    </template>

    <RecordPaymentModal
      v-if="showRecord"
      :store-id="currentStore.id"
      :party-id="selectedPartyId"
      :party-name="selectedPartyName"
      :open-invoices="openInvoices"
      :preselect-invoice-id="recordPreselectId"
      @close="closeRecord"
      @saved="onRecorded"
    />

    <ConfirmDialog
      v-if="deletingPayment"
      title="Delete Payment"
      :message="`Delete this ${formatMoney(deletingPayment.amount)} payment? Its invoices will return to their previous balance.`"
      confirm-text="Yes, delete"
      cancel-text="Cancel"
      type="danger"
      @confirm="handleDelete"
      @cancel="deletingPayment = null"
    />

    <InvoiceDetailModal
      v-if="detailInvoice"
      :invoice="detailInvoice"
      :can-manage="false"
      :can-edit="canManage"
      @close="detailInvoice = null"
      @edit="onInvoiceEdit"
    />
  </PageContainer>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import InactiveBanner from '@/components/common/InactiveBanner.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import SegmentedToggle from '@/components/common/SegmentedToggle.vue'
import Pagination from '@/components/common/Pagination.vue'
import PaymentStatusBadge from '@/features/invoices/components/PaymentStatusBadge.vue'
import InvoiceDetailModal from '@/features/invoices/components/InvoiceDetailModal.vue'
import RecordPaymentModal from '@/features/payments/components/RecordPaymentModal.vue'
import { fetchCustomers } from '@/features/customers/services/customerService'
import { fetchSuppliers } from '@/features/suppliers/services/supplierService'
import { fetchPartyOpenInvoices, fetchPayments, deletePayment, fetchPaymentsByBusiness, fetchPartyOpenInvoicesByBusiness } from '@/features/payments/services/paymentService'
import { fetchInvoice } from '@/features/invoices/services/invoiceService'
import { INVOICE_TYPE, formatMoney, formatInvoiceDate as formatDate, paymentMethodLabel } from '@/features/invoices/constants'
import { useClientPagination } from '@/composables/useClientPagination'
import { formatDateTime } from '@/utils/datetime'

const route = useRoute()
const router = useRouter()
const showToast = inject('showToast')
const currentStore = inject('currentStore')
const currentBusiness = inject('currentBusiness')

const canManage = computed(() => !!currentStore.value?.is_active)

const partyType = ref('customer')
const parties = ref([])
const loadingParties = ref(false)
const selectedPartyId = ref('')
const openInvoices = ref([])
const payments = ref([])
const {
  currentPage: paymentsPage,
  perPage: paymentsPerPage,
  total: paymentsTotal,
  totalPages: paymentsTotalPages,
  paginated: pagedPayments,
  setPerPage: setPaymentsPerPage,
  resetPage: resetPaymentsPage,
} = useClientPagination(payments)
const loadingDetail = ref(false)
const showRecord = ref(false)
const recordPreselectId = ref(null)
const deletingPayment = ref(null)
const detailInvoice = ref(null)

const savedPartyType = localStorage.getItem('paymentsPartyType')
if (savedPartyType === 'customer' || savedPartyType === 'supplier') {
  partyType.value = savedPartyType
}
let pendingRestorePartyId = localStorage.getItem('paymentsPartyId') || ''

// Deep-link from an invoice's edit page (?party_type, ?party_id, ?invoice_id) takes
// precedence over the last-viewed party restored from localStorage.
let pendingRecordInvoiceId = ''
const linkPartyType = String(route.query.party_type || '')
if (linkPartyType === 'customer' || linkPartyType === 'supplier') {
  partyType.value = linkPartyType
}
if (route.query.party_id) {
  pendingRestorePartyId = String(route.query.party_id)
}
if (route.query.invoice_id) {
  pendingRecordInvoiceId = String(route.query.invoice_id)
}

const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')
const canDeletePayment = computed(() => {
  if (isBusinessOwner.value) return true
  const role = currentStore.value?.my_role
  return role === 'owner' || role === 'accountant'
})

// Store mode: only parties linked to the current store (so staff/accountants can't
// see parties from stores they have no access to). Business mode (owner): every
// party, shown with their business-wide balance.
const partyOptions = computed(() => {
  const list = currentStore.value
    ? parties.value.filter((p) => (p.stores || []).some((s) => String(s.id) === String(currentStore.value.id)))
    : parties.value
  return list.map((p) => {
    const balance = currentStore.value ? Number(p.outstanding) : Number(p.business_outstanding)
    return {
      value: String(p.party?.id ?? ''),
      label: p.name,
      sublabel: balance > 0 ? `Outstanding ${formatMoney(balance)}` : '',
    }
  })
})

const selectedPartyName = computed(() => {
  const p = parties.value.find((x) => String(x.party?.id) === String(selectedPartyId.value))
  return p?.name || ''
})

const outstanding = computed(() =>
  openInvoices.value.reduce((sum, i) => sum + Number(i.balance || 0), 0),
)

const openInvoiceDetail = async (id) => {
  try {
    detailInvoice.value = await fetchInvoice({ id })
  } catch (err) {
    showToast(err.message, 'error')
  }
}

const onInvoiceEdit = () => {
  const invoice = detailInvoice.value
  detailInvoice.value = null
  const base = invoice.type === INVOICE_TYPE.SALE ? 'sale-invoices' : 'purchase-invoices'
  window.open(router.resolve(`/${base}/${invoice.id}/edit`).href, '_blank')
}

const loadParties = async () => {
  if (!currentBusiness.value?.id) return
  loadingParties.value = true
  try {
    const args = { storeId: currentStore.value?.id ?? null, businessId: currentBusiness.value.id }
    parties.value = partyType.value === 'customer' ? await fetchCustomers(args) : await fetchSuppliers(args)
    if (pendingRestorePartyId) {
      const stillSelectable = partyOptions.value.some((o) => String(o.value) === String(pendingRestorePartyId))
      if (stillSelectable) {
        selectedPartyId.value = pendingRestorePartyId
        await loadDetail()
        maybeOpenRecordFromLink()
      }
      pendingRestorePartyId = ''
      clearLinkQuery()
    }
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loadingParties.value = false
  }
}

const loadDetail = async () => {
  if (!selectedPartyId.value) {
    openInvoices.value = []
    payments.value = []
    return
  }
  loadingDetail.value = true
  try {
    let open, history
    if (currentStore.value?.id) {
      const args = { storeId: currentStore.value.id, partyId: selectedPartyId.value }
      ;[open, history] = await Promise.all([fetchPartyOpenInvoices(args), fetchPayments(args)])
    } else {
      const args = { businessId: currentBusiness.value.id, partyId: selectedPartyId.value }
      ;[open, history] = await Promise.all([fetchPartyOpenInvoicesByBusiness(args), fetchPaymentsByBusiness(args)])
    }
    openInvoices.value = open
    payments.value = history
    resetPaymentsPage()
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loadingDetail.value = false
  }
}

const setPartyType = (type) => {
  if (partyType.value === type) return
  partyType.value = type
  selectedPartyId.value = ''
  openInvoices.value = []
  payments.value = []
  loadParties()
}

const onPartyChange = () => loadDetail()

const openRecord = (invoiceId = null) => {
  recordPreselectId.value = invoiceId
  showRecord.value = true
}

const closeRecord = () => {
  showRecord.value = false
  recordPreselectId.value = null
}

// After a deep-link selects a party and loads its open invoices, auto-open the
// record modal on the linked invoice (when it's still open and the store is active).
const maybeOpenRecordFromLink = () => {
  const invoiceId = pendingRecordInvoiceId
  pendingRecordInvoiceId = ''
  if (!invoiceId || !currentStore.value?.is_active) return
  if (openInvoices.value.some((inv) => String(inv.id) === String(invoiceId))) {
    openRecord(invoiceId)
  }
}

const clearLinkQuery = () => {
  if (Object.keys(route.query).length === 0) return
  router.replace({ path: '/payments', query: {} })
}

const onRecorded = () => {
  showRecord.value = false
  recordPreselectId.value = null
  loadDetail()
  loadParties()
}

const confirmDelete = (payment) => { deletingPayment.value = payment }

const handleDelete = async () => {
  try {
    await deletePayment({ id: deletingPayment.value.id })
    deletingPayment.value = null
    showToast('Payment deleted.')
    loadDetail()
    loadParties()
  } catch (err) {
    showToast(err.message, 'error')
  }
}

watch(() => [currentStore.value?.id, currentBusiness.value?.id], () => {
  selectedPartyId.value = ''
  openInvoices.value = []
  payments.value = []
  loadParties()
})

watch([partyType, selectedPartyId], () => {
  localStorage.setItem('paymentsPartyType', partyType.value)
  localStorage.setItem('paymentsPartyId', selectedPartyId.value)
})

loadParties()
</script>

<style scoped>
.picker-bar { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
.picker-select { min-width: 280px; flex: 1; max-width: 420px; }

.summary { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; background: #f9fafb; border: 1px solid #eef0f2; border-radius: 12px; margin-bottom: 20px; }
.summary-item { display: flex; flex-direction: column; gap: 4px; }
.summary-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
.summary-value { font-size: 24px; font-weight: 700; color: #111; }
.summary-value.owed { color: #b45309; }
.btn-record { padding: 10px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
.btn-record:hover:not(:disabled) { background: #333; }
.btn-record:disabled { opacity: 0.5; cursor: not-allowed; }

.block { margin-bottom: 24px; }
.block h3 { font-size: 14px; font-weight: 700; color: #111; margin-bottom: 10px; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13.5px; }
.data-table th { position: sticky; top: 64px; z-index: 1; background: #fff; text-align: left; padding: 9px 12px; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #eef0f2; }
.data-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; color: #374151; }
.data-table .num { text-align: right; }
.data-table .strong { font-weight: 600; color: #111; }
.data-table .mono { font-family: monospace; font-weight: 600; color: #111; }
.applied { font-family: monospace; font-size: 12.5px; color: #6b7280; }
.code-link { background: none; border: none; padding: 0; font: inherit; font-family: monospace; font-weight: 600; color: #2563eb; cursor: pointer; }
.code-link:hover { text-decoration: underline; }
.alloc { display: inline-flex; align-items: center; gap: 4px; margin-right: 12px; }
.store-tag { display: inline-block; margin-left: 6px; padding: 1px 7px; background: #eef2ff; color: #4338ca; border-radius: 6px; font-size: 11px; font-weight: 600; font-family: inherit; white-space: nowrap; }
.link-danger { background: none; border: none; padding: 0; font: inherit; font-size: 13px; color: #dc2626; cursor: pointer; }
.link-danger:hover { text-decoration: underline; }
.empty { padding: 16px; text-align: center; color: #9ca3af; font-size: 13.5px; background: #f9fafb; border-radius: 10px; }
</style>
