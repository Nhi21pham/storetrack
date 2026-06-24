<template>
  <button class="btn-scan" type="button" @click="onClick">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="7" y1="12" x2="17" y2="12"/></svg>
    {{ label }}
  </button>
</template>

<script setup>
// Entry point to AI invoice scanning. Reused across purchase and (later) sale
// invoices — pass the scan route via `to`, or omit it and handle `click`.
import { useRouter } from 'vue-router'
import { t } from '@/i18n'

const props = defineProps({
  to: { type: String, default: '' },
  label: { type: String, default: () => t('invoices.scanInvoice') },
})

const emit = defineEmits(['click'])
const router = useRouter()

const onClick = () => {
  if (props.to) {
    router.push(props.to)
  } else {
    emit('click')
  }
}
</script>

<style scoped>
.btn-scan { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #fff; color: #111; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s, border-color 0.2s; white-space: nowrap; }
.btn-scan:hover { background: #f9fafb; border-color: #111; }
</style>
