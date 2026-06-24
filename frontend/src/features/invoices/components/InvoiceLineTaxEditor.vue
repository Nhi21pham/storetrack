<template>
  <div class="tax-editor">
    <p v-if="storeTaxes.length === 0" class="tax-empty">
      {{ $t('invoices.noTaxesConfigured') }}
    </p>
    <div v-else class="tax-grid">
      <div v-for="tax in storeTaxes" :key="tax.id" class="tax-line">
        <span class="tax-name">{{ tax.name }}</span>
        <div class="rate-field">
          <input
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            placeholder="0"
            :value="modelValue[String(tax.id)] ?? ''"
            @input="onInput(tax.id, $event.target.value)"
          />
          <span class="pct">%</span>
        </div>
        <span class="tax-amount">{{ formatMoney(amountFor(tax.id)) }}</span>
      </div>
    </div>
    <p class="tax-hint">{{ $t('invoices.leaveRateBlank') }}</p>
  </div>
</template>

<script setup>
import { formatMoney } from '@/features/invoices/constants'

const props = defineProps({
  storeTaxes: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
  lineSubtotal: { type: Number, default: 0 },
})

const emit = defineEmits(['update:modelValue'])

const onInput = (taxId, value) => {
  const next = { ...props.modelValue }
  const key = String(taxId)
  if (value === '' || value == null) {
    delete next[key]
  } else {
    next[key] = value
  }
  emit('update:modelValue', next)
}

const amountFor = (taxId) => {
  const rate = Number(props.modelValue[String(taxId)])
  if (!rate || Number.isNaN(rate)) return 0
  return (props.lineSubtotal * rate) / 100
}
</script>

<style scoped>
.tax-editor { padding: 12px 14px; background: #f9fafb; border-radius: 8px; }
.tax-empty { margin: 0; font-size: 13px; color: #6b7280; }
.tax-grid { display: flex; flex-direction: column; gap: 8px; }
.tax-line { display: grid; grid-template-columns: 1fr 120px 110px; align-items: center; gap: 12px; }
.tax-name { font-size: 13px; font-weight: 500; color: #374151; }
.rate-field { display: flex; align-items: center; gap: 6px; }
.rate-field input {
  width: 80px; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px;
  font-size: 13px; color: #111; text-align: right; outline: none; box-sizing: border-box;
}
.rate-field input:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.pct { font-size: 13px; color: #6b7280; }
.tax-amount { font-size: 13px; color: #111; text-align: right; font-variant-numeric: tabular-nums; }
.tax-hint { margin: 10px 0 0; font-size: 12px; color: #9ca3af; }
</style>
