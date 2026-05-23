<template>
  <div class="page-container" :style="{ maxWidth: typeof maxWidth === 'number' ? `${maxWidth}px` : maxWidth }">
    <slot />
  </div>
</template>

<script setup>
defineProps({
  maxWidth: { type: [Number, String], default: 900 },
})
</script>

<style scoped>
.page-container { padding: 32px; margin: 0 auto; }
</style>
