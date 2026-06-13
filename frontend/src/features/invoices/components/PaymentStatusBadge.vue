<template>
  <span class="status-badge" :class="(status || '').toLowerCase()">{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue'
import { paymentStatusLabel } from '@/features/invoices/constants'

const props = defineProps({
  status: { type: String, default: '' },
})

const LABELS = { UNPAID: 'Unpaid', PARTIAL: 'Partial', PAID: 'Paid' }
const label = computed(() => LABELS[props.status] || paymentStatusLabel(props.status))
</script>

<style scoped>
.status-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.status-badge.unpaid { background: #fef3c7; color: #92400e; }
.status-badge.partial { background: #dbeafe; color: #1e40af; }
.status-badge.paid { background: #dcfce7; color: #166534; }
</style>
