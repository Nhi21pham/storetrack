<template>
  <div v-if="missingBanks.length" class="missing-banner">
    <div class="banner-head">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>{{ missingBanks.length }} bank(s) in this file don't exist yet. Create them here, then those rows will resolve.</span>
    </div>
    <div class="bank-chips">
      <button v-for="name in missingBanks" :key="name" class="bank-chip" @click="openCreate(name)">
        <span class="chip-name">{{ name }}</span>
        <span class="chip-add">+ Create</span>
      </button>
    </div>
  </div>

  <BankFormModal
    v-if="creatingName !== null"
    :business-id="businessId"
    :prefill-short-name="creatingName"
    @close="creatingName = null"
    @saved="onResolved"
    @pick-existing="onResolved"
  />
</template>

<script setup>
import { ref, computed } from 'vue'
import BankFormModal from '@/features/banking/components/BankFormModal.vue'

const props = defineProps({
  rows: { type: Array, default: () => [] },
  businessId: { type: [String, Number], default: null },
  // From ImportModal's review-banner slot: clears the Bank error on rows whose
  // Bank cell matches the now-created bank.
  resolveReference: { type: Function, required: true },
})

const creatingName = ref(null)

// Distinct, non-empty Bank values among rows still flagged with a Bank error —
// the ones the user can fix by creating the bank. First-seen casing is kept.
const missingBanks = computed(() => {
  const seen = new Set()
  const names = []
  for (const row of props.rows) {
    if (!row.errors || !row.errors.Bank) continue
    const value = String(row.values?.Bank ?? '').trim()
    if (!value) continue
    const key = value.toLowerCase()
    if (seen.has(key)) continue
    seen.add(key)
    names.push(value)
  }
  return names
})

const openCreate = (name) => {
  creatingName.value = name
}

const onResolved = () => {
  const name = creatingName.value
  creatingName.value = null
  if (name) props.resolveReference('Bank', [name])
}
</script>

<style scoped>
.missing-banner { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; }
.banner-head { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #92400e; font-weight: 500; }
.banner-head svg { flex-shrink: 0; }
.bank-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.bank-chip { display: inline-flex; align-items: center; gap: 8px; padding: 5px 10px; background: #fff; border: 1px solid #fcd34d; border-radius: 999px; font-size: 12.5px; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
.bank-chip:hover { background: #fef3c7; border-color: #f59e0b; }
.chip-name { font-weight: 600; color: #111; }
.chip-add { color: #b45309; font-weight: 600; }
</style>
