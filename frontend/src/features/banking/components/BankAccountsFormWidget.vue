<template>
  <BankAccountsSection
    v-model="drafts"
    :party-id="partyId"
    :business-id="businessId"
    :default-holder-name="defaultHolderName"
  />
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import BankAccountsSection from '@/features/banking/components/BankAccountsSection.vue'
import { submitDraftBankAccounts } from '@/features/banking/services/draftBankAccounts'

const props = defineProps({
  partyId: { type: [String, Number], default: null },
  businessId: { type: [String, Number], default: null },
  defaultHolderName: { type: String, default: '' },
  entityLabel: { type: String, default: 'Record' },
})

const drafts = ref([])
const hasDrafts = computed(() => drafts.value.length > 0)

watch(() => props.partyId, (id) => {
  if (id) drafts.value = []
})

const submitDrafts = async (partyId) => {
  if (drafts.value.length === 0) return null
  const errors = await submitDraftBankAccounts({ partyId, drafts: drafts.value })
  if (errors.length === 0) {
    drafts.value = []
    return null
  }
  return `${props.entityLabel} was saved, but some bank accounts failed:\n` + errors.join('\n')
}

defineExpose({ hasDrafts, submitDrafts })
</script>
