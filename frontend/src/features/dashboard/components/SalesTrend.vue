<template>
  <div class="chart-card">
    <div class="chart-header">
      <h3>Sales Trend</h3>
      <p>Monthly revenue performance</p>
    </div>
    <svg viewBox="0 0 500 260" class="line-chart">
      <defs>
        <linearGradient id="grad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#e8450a" stop-opacity="0.15"/>
          <stop offset="100%" stop-color="#e8450a" stop-opacity="0"/>
        </linearGradient>
      </defs>
      <line v-for="y in [30,70,110,150]" :key="y" x1="50" :y1="y" x2="490" :y2="y" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="4"/>
      <text x="40" y="34" font-size="10" fill="#9ca3af" text-anchor="end">$80k</text>
      <text x="40" y="74" font-size="10" fill="#9ca3af" text-anchor="end">$60k</text>
      <text x="40" y="114" font-size="10" fill="#9ca3af" text-anchor="end">$40k</text>
      <text x="40" y="154" font-size="10" fill="#9ca3af" text-anchor="end">$20k</text>
      <text x="40" y="180" font-size="10" fill="#9ca3af" text-anchor="end">$0k</text>
      <text v-for="(m, i) in months" :key="m" :x="50 + i * (440/7)" y="210" font-size="10" fill="#9ca3af" text-anchor="middle">{{ m }}</text>
      <path :d="areaPath" fill="url(#grad)"/>
      <path :d="linePath" fill="none" stroke="#e8450a" stroke-width="2.5" stroke-linejoin="round"/>
      <circle v-for="(pt, i) in points" :key="i" :cx="pt.x" :cy="pt.y" r="4" fill="#e8450a"/>
    </svg>
  </div>
</template>

<script setup>
const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul']
const rawData = [45, 52, 48, 61, 59, 65, 68, 72]
const minVal = 0, maxVal = 80
const chartWidth = 440, chartHeight = 190, startX = 50

const points = rawData.map((v, i) => ({
  x: startX + (i * chartWidth / (rawData.length - 1)),
  y: 200 - ((v - minVal) / (maxVal - minVal)) * chartHeight
}))

const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
const areaPath = linePath + ` L ${points[points.length-1].x} 210 L ${points[0].x} 210 Z`
</script>

<style scoped>
.chart-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.chart-header { margin-bottom: 20px; }
.chart-header h3 { font-size: 18px; font-weight: 700; color: #111; }
.chart-header p { font-size: 14px; color: #6b7280; margin-top: 2px; }
.line-chart { width: 100%; height: 280px; }
</style>