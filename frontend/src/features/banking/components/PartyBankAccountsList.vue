<template>
  <section class="party-bank-list">
    <h3 class="section-title">{{ $t('banking.sectionTitle') }}</h3>

    <p v-if="loading" class="state-text">{{ $t('common.loading') }}</p>
    <p v-else-if="loadError" class="state-error">{{ loadError }}</p>
    <p v-else-if="accounts.length === 0" class="state-text">{{ $t('banking.noAccountsShort') }}</p>

    <ul v-else class="account-list">
      <li v-for="account in accounts" :key="account.id" class="account-row">
        <div class="row-header">
          <span class="bank-name">{{ account.bank?.short_name || '—' }}</span>
          <span class="account-number">{{ account.account_number }}</span>
        </div>
        <dl class="row-details">
          <div v-if="account.account_holder_name" class="detail-item">
            <dt>{{ $t('banking.holder') }}</dt>
            <dd>{{ account.account_holder_name }}</dd>
          </div>
          <div v-if="account.branch" class="detail-item">
            <dt>{{ $t('banking.branch') }}</dt>
            <dd>{{ account.branch }}</dd>
          </div>
          <div v-if="account.province?.name_vi" class="detail-item">
            <dt>{{ $t('banking.province') }}</dt>
            <dd>{{ account.province.name_vi }}</dd>
          </div>
        </dl>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { fetchBankAccountsForParty } from '@/features/banking/services/bankAccountService'
import { translateError } from '@/utils/translateError'

const props = defineProps({
  partyId: { type: [String, Number], required: true },
})

const accounts = ref([])
const loading = ref(false)
const loadError = ref('')

const load = async () => {
  if (!props.partyId) {
    accounts.value = []
    return
  }
  loading.value = true
  loadError.value = ''
  try {
    accounts.value = await fetchBankAccountsForParty({ partyId: props.partyId })
  } catch (err) {
    loadError.value = translateError(err)
    accounts.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => props.partyId, load)
</script>

<style scoped>
.party-bank-list { padding-top: 14px; margin-top: 14px; border-top: 1px solid #f3f4f6; }
.section-title { font-size: 12px; font-weight: 600; color: #6b7280; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 0.04em; }

.state-text { font-size: 13px; color: #9ca3af; margin: 0; }
.state-error { font-size: 13px; color: #dc2626; margin: 0; }

.account-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.account-row { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; }

.row-header { display: flex; flex-wrap: wrap; align-items: baseline; gap: 10px; padding-bottom: 6px; margin-bottom: 6px; border-bottom: 1px dashed #e5e7eb; }
.bank-name { font-weight: 700; color: #111; font-size: 14px; }
.account-number { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; color: #374151; background: #fff; border: 1px solid #e5e7eb; border-radius: 5px; padding: 1px 7px; }

.row-details { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 3px 10px; font-size: 12.5px; }
.detail-item { display: contents; }
.detail-item dt { color: #9ca3af; font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; align-self: center; }
.detail-item dd { margin: 0; color: #374151; word-break: break-word; }
</style>
