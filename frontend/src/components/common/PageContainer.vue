<template>
  <div class="page-container" :style="{ maxWidth: typeof maxWidth === 'number' ? `${maxWidth}px` : maxWidth }">
    <Breadcrumbs />
    <slot />
  </div>
</template>

<script setup>
import Breadcrumbs from '@/components/common/Breadcrumbs.vue'

defineProps({
  maxWidth: { type: [Number, String], default: 900 },
})
</script>

<style scoped>
.page-container { padding: 32px; margin: 0 auto; }
</style>
