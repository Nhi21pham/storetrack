<template>
  <button type="button" class="clear-filters-btn" @click="$emit('click')">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    Clear filters
  </button>
</template>

<script setup>
defineEmits(['click'])
</script>

<style scoped>
.clear-filters-btn { display: inline-flex; align-items: center; gap: 5px; padding: 8px 13px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; color: #6b7280; font-size: 13px; font-weight: 500; font-family: inherit; cursor: pointer; white-space: nowrap; transition: all 0.15s; }
.clear-filters-btn:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }
</style>
