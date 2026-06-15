<template>
  <div class="chart-card">
    <div class="chart-header">
      <h3>Sales Trend</h3>
      <p>Monthly revenue — last 12 months</p>
    </div>
    <svg viewBox="0 0 500 230" class="line-chart">
      <defs>
        <linearGradient id="salesGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#e8450a" stop-opacity="0.15"/>
          <stop offset="100%" stop-color="#e8450a" stop-opacity="0"/>
        </linearGradient>
      </defs>
      <g v-for="(g, i) in gridLines" :key="i">
        <line x1="50" :y1="g.y" x2="490" :y2="g.y" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="4"/>
        <text x="42" :y="g.y + 3" font-size="10" fill="#9ca3af" text-anchor="end">{{ g.label }}</text>
      </g>
      <text v-for="(p, i) in coords" :key="`x${i}`" :x="p.x" y="220" font-size="10" fill="#9ca3af" text-anchor="middle">{{ p.label }}</text>
      <path v-if="areaPath" :d="areaPath" fill="url(#salesGrad)"/>
      <path v-if="linePath" :d="linePath" fill="none" stroke="#e8450a" stroke-width="2.5" stroke-linejoin="round"/>
      <circle v-for="(p, i) in coords" :key="`pt${i}`" :cx="p.x" :cy="p.y" r="3.5" fill="#e8450a"/>
    </svg>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  points: { type: Array, required: true }, // [{ label, revenue }]
})

const W = 440, H = 180, X0 = 50, Y0 = 195

const maxVal = computed(() => Math.max(...props.points.map((p) => Number(p.revenue) || 0), 1))
const n = computed(() => props.points.length)

const coords = computed(() => props.points.map((p, i) => ({
  x: X0 + (n.value > 1 ? (i * W) / (n.value - 1) : W / 2),
  y: Y0 - ((Number(p.revenue) || 0) / maxVal.value) * H,
  label: p.label,
})))

const linePath = computed(() => coords.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' '))

const areaPath = computed(() => {
  if (coords.value.length === 0) return ''
  const last = coords.value[coords.value.length - 1]
  const first = coords.value[0]
  return `${linePath.value} L ${last.x.toFixed(1)} ${Y0} L ${first.x.toFixed(1)} ${Y0} Z`
})

const gridLines = computed(() => [1, 0.75, 0.5, 0.25, 0].map((f) => ({
  y: Y0 - f * H,
  label: compact(f * maxVal.value),
})))

// Compact VND axis labels: 35M, 1.2B, 800k …
function compact(v) {
  if (v >= 1e9) return (v / 1e9).toFixed(v >= 1e10 ? 0 : 1) + 'B'
  if (v >= 1e6) return Math.round(v / 1e6) + 'M'
  if (v >= 1e3) return Math.round(v / 1e3) + 'k'
  return String(Math.round(v))
}
</script>

<style scoped>
.chart-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.chart-header { margin-bottom: 16px; }
.chart-header h3 { font-size: 18px; font-weight: 700; color: #111; }
.chart-header p { font-size: 14px; color: #6b7280; margin-top: 2px; }
.line-chart { width: 100%; height: 280px; }
</style>
