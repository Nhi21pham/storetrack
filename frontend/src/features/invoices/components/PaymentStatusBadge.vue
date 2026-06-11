<template>
  <span class="status-badge" :class="(status || '').toLowerCase()">{{ paymentStatusLabel(status) }}</span>
</template>

<script setup>
import { paymentStatusLabel } from '@/features/invoices/constants'

defineProps({
  status: { type: String, default: '' },
})
</script>

<style scoped>
.status-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.status-badge.unpaid { background: #fef3c7; color: #92400e; }
.status-badge.paid { background: #dcfce7; color: #166534; }
</style>
