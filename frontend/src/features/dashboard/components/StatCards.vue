<template>
  <div class="stats-grid">
    <div class="stat-card" v-for="stat in cards" :key="stat.label">
      <div class="stat-header">
        <span class="stat-label">{{ stat.label }}</span>
        <span class="stat-icon" v-html="stat.icon"></span>
      </div>
      <div class="stat-value" :class="{ owed: stat.owed }">{{ stat.value }}</div>
      <div class="stat-change">
        <span v-if="stat.delta.badge" class="change-badge" :class="stat.delta.kind">{{ stat.delta.badge }}</span>
        <span class="vs">{{ stat.delta.note }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { formatMoney, formatQuantity } from '@/features/invoices/constants'

const props = defineProps({
  summary: { type: Object, required: true },
})

const ICONS = {
  sales: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
  profit: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="18" y1="20" x2="18" y2="10"/></svg>',
  stock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
  outstanding: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
}

/**
 * Builds the change indicator: a colored % pill vs last month when there's a
 * prior-month baseline; a green "New" pill when this is the first month with a
 * value (prior month was 0); a plain neutral note otherwise.
 */
const delta = (change, amount) => {
  if (change !== null && change !== undefined) {
    const positive = change >= 0
    return { kind: positive ? 'pos' : 'neg', badge: `${positive ? '↗' : '↘'} ${Math.abs(change).toFixed(1)}%`, note: 'vs last month' }
  }
  if (Number(amount) > 0) {
    return { kind: 'new', badge: '↗ New', note: 'this month' }
  }
  return { kind: 'none', badge: null, note: 'no activity' }
}

const cards = computed(() => [
  { label: 'Total Sales',       value: formatMoney(props.summary.total_sales),          delta: delta(props.summary.total_sales_change, props.summary.total_sales),   icon: ICONS.sales },
  { label: 'Total Profit',      value: formatMoney(props.summary.total_profit),         delta: delta(props.summary.total_profit_change, props.summary.total_profit), icon: ICONS.profit },
  { label: 'Products in Stock', value: formatQuantity(props.summary.products_in_stock), delta: delta(props.summary.products_in_stock_change, props.summary.products_in_stock), icon: ICONS.stock },
  { label: 'Outstanding',       value: formatMoney(props.summary.outstanding),          delta: delta(props.summary.outstanding_change, props.summary.outstanding), owed: props.summary.outstanding > 0, icon: ICONS.outstanding },
])
</script>

<style scoped>
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 22px; }
.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.stat-label { font-size: 13.5px; color: #6b7280; }
.stat-icon { display: inline-flex; }
.stat-value { font-size: 27px; font-weight: 700; color: #111; margin-bottom: 10px; line-height: 1.1; }
.stat-value.owed { color: #b45309; }
.stat-change { display: flex; align-items: center; gap: 8px; font-size: 12.5px; }
.change-badge { display: inline-flex; align-items: center; gap: 3px; font-weight: 700; padding: 3px 9px; border-radius: 999px; font-variant-numeric: tabular-nums; }
.change-badge.pos, .change-badge.new { color: #15803d; background: #dcfce7; }
.change-badge.neg { color: #b91c1c; background: #fee2e2; }
.vs { color: #9ca3af; }

@media (max-width: 980px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
