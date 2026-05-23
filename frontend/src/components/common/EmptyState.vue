<template>
  <div class="empty-state">
    <div v-if="$slots.icon" class="empty-icon">
      <slot name="icon" />
    </div>
    <h3 v-if="title">{{ title }}</h3>
    <p v-if="description || $slots.default">
      <slot>{{ description }}</slot>
    </p>
    <div v-if="$slots.action" class="empty-action">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title:       { type: String, default: '' },
  description: { type: String, default: '' },
})
</script>

<style scoped>
.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { width: 72px; height: 72px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #9ca3af; }
.empty-state h3 { font-size: 16px; font-weight: 600; color: #111; margin-bottom: 6px; }
.empty-state p { font-size: 14px; color: #6b7280; margin-bottom: 0; }
.empty-action { margin-top: 20px; }
</style>
