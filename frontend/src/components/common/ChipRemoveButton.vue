<template>
  <button type="button" class="chip-del" :title="title" @click="$emit('click')">
    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  </button>
</template>

<script setup>
defineProps({
  title: { type: String, default: 'Remove' },
})
defineEmits(['click'])
</script>

<style scoped>
.chip-del { display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; margin-left: 2px; padding: 0; border: none; border-radius: 50%; background: transparent; color: #9ca3af; cursor: pointer; }
.chip-del:hover { background: #fef2f2; color: #dc2626; }
</style>
