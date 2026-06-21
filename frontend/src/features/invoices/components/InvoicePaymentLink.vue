<template>
  <button type="button" class="pay-link" @click="goToPayments">
    {{ isPaid ? 'View payments' : 'Record payment' }}
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  partyType: { type: String, required: true },
  partyId: { type: [String, Number], required: true },
  invoiceId: { type: [String, Number], required: true },
  status: { type: String, default: '' },
})

const router = useRouter()
const isPaid = computed(() => props.status === 'PAID')

// Deep-link to the Payments page (in a new tab, leaving the edit form untouched)
// with the party selected. When the invoice still has a balance, also pass its id
// so Payments auto-opens the record modal on it.
const goToPayments = () => {
  const query = { party_type: props.partyType, party_id: String(props.partyId) }
  if (!isPaid.value) query.invoice_id = String(props.invoiceId)
  window.open(router.resolve({ path: '/payments', query }).href, '_blank')
}
</script>

<style scoped>
.pay-link { margin-left: auto; padding: 6px 12px; background: #f3f4f6; color: #111; border: none; border-radius: 8px; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: background 0.15s; }
.pay-link:hover { background: #e9eaec; }
</style>
