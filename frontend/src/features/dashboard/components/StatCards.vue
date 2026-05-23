<template>
  <div class="stats-grid">
    <div class="stat-card" v-for="stat in stats" :key="stat.label">
      <div class="stat-header">
        <span class="stat-label">{{ stat.label }}</span>
        <span v-html="stat.icon"></span>
      </div>
      <div class="stat-value">{{ stat.value }}</div>
      <div class="stat-change" :class="stat.positive ? 'positive' : 'negative'">
        <span>{{ stat.positive ? '↗' : '↘' }}</span>
        <span>{{ stat.change }}</span>
        <span class="vs">vs last month</span>
      </div>
    </div>
  </div>
</template>

<script setup>
const stats = [
  { label: 'Total Sales', value: '$72,340', change: '+14.2%', positive: true, icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>' },
  { label: 'Active Stores', value: '24', change: '+8.3%', positive: true, icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>' },
  { label: 'Products in Stock', value: '1,847', change: '-2.1%', positive: false, icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>' },
]
</script>

<style scoped>
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.stat-label { font-size: 14px; color: #6b7280; }
.stat-value { font-size: 32px; font-weight: 700; color: #111; margin-bottom: 8px; }
.stat-change { display: flex; align-items: center; gap: 4px; font-size: 14px; }
.stat-change.positive { color: #16a34a; }
.stat-change.negative { color: #dc2626; }
.vs { color: #6b7280; }
</style>