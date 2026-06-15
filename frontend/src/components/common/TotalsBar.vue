<template>
  <div class="totals-bar">
    <span
      v-for="(item, index) in items"
      :key="index"
      class="total-item"
      :class="[{ strong: item.strong }, item.variant]"
    >
      <span class="total-label">{{ item.label }}</span> {{ item.value }}
    </span>
  </div>
</template>

<script setup>
defineProps({
  items: { type: Array, required: true },
})
</script>

<style scoped>
.totals-bar { display: flex; flex-wrap: wrap; gap: 24px; padding: 14px 18px; margin-top: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13.5px; color: #374151; }
.total-item { display: inline-flex; align-items: baseline; gap: 8px; font-variant-numeric: tabular-nums; }
.total-item.strong { font-weight: 800; font-size: 19px; color: #111; margin-left: auto; }
.total-item.strong.loss { color: #dc2626; }
.total-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
</style>
