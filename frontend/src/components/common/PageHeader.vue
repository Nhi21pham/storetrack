<template>
  <div class="page-header">
    <div class="page-header__text">
      <h1>{{ title }}</h1>
      <p v-if="subtitle || $slots.subtitle" class="subtitle">
        <slot name="subtitle">{{ subtitle }}</slot>
      </p>
    </div>
    <div v-if="$slots.actions" class="page-header__actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title:    { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; gap: 16px; }
.page-header__text { min-width: 0; }
.page-header h1 { font-size: 22px; font-weight: 700; color: #111; }
.subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }
.subtitle :deep(strong) { font-weight: 600; color: #111; }
.page-header__actions { flex-shrink: 0; }
</style>
