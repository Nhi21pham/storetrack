<template>
  <div class="store-perf-card">
    <div class="perf-header">
      <div>
        <h3>Store Performance</h3>
        <p>Compare stores by {{ metricLabel.toLowerCase() }} this month</p>
      </div>
      <SegmentedToggle v-model="metric" :options="METRICS" />
    </div>

    <div v-if="ranked.length" class="bars">
      <div class="bar-row" v-for="(s, i) in ranked" :key="s.store_id">
        <span class="bar-rank">#{{ i + 1 }}</span>
        <span class="bar-name" :title="s.store_name">{{ s.store_name }}</span>
        <div class="bar-track">
          <div class="bar-fill" :style="barStyle(s)"></div>
        </div>
        <span class="bar-value" :class="{ loss: value(s) < 0 }">{{ formatMoney(value(s)) }}</span>
      </div>
    </div>
    <p v-else class="empty">No stores to compare.</p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import SegmentedToggle from '@/components/common/SegmentedToggle.vue'
import { formatMoney } from '@/features/invoices/constants'

const props = defineProps({
  stores: { type: Array, required: true }, // [{ store_id, store_name, revenue, profit }]
})

const METRICS = [
  { value: 'revenue', label: 'Revenue' },
  { value: 'profit',  label: 'Profit' },
]
const metric = ref('revenue')
const metricLabel = computed(() => METRICS.find((m) => m.value === metric.value)?.label ?? 'Revenue')

const value = (s) => Number(s[metric.value]) || 0

const ranked = computed(() => [...props.stores].sort((a, b) => value(b) - value(a)))

// Bars scale to the largest magnitude so a single loss-making store still reads.
const maxAbs = computed(() => Math.max(...props.stores.map((s) => Math.abs(value(s))), 1))

const barStyle = (s) => {
  const v = value(s)
  const width = (Math.abs(v) / maxAbs.value) * 100
  const color = metric.value === 'profit' ? (v < 0 ? '#dc2626' : '#047857') : '#4f46e5'
  return { width: `${Math.max(width, 1).toFixed(1)}%`, background: color }
}
</script>

<style scoped>
.store-perf-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
.perf-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.perf-header h3 { font-size: 18px; font-weight: 700; color: #111; }
.perf-header p { font-size: 14px; color: #6b7280; margin-top: 2px; }
.bars { display: flex; flex-direction: column; gap: 14px; }
.bar-row { display: grid; grid-template-columns: 28px 160px 1fr auto; align-items: center; gap: 12px; }
.bar-rank { font-size: 13px; font-weight: 700; color: #9ca3af; }
.bar-name { font-size: 14px; font-weight: 600; color: #111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bar-track { background: #f3f4f6; border-radius: 6px; height: 22px; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 6px; transition: width 0.25s ease, background 0.2s; }
.bar-value { font-size: 14px; font-weight: 700; color: #111; font-variant-numeric: tabular-nums; white-space: nowrap; }
.bar-value.loss { color: #dc2626; }
.empty { padding: 24px 8px; text-align: center; color: #9ca3af; font-size: 13.5px; }

@media (max-width: 700px) { .bar-row { grid-template-columns: 24px 100px 1fr auto; } }
</style>
