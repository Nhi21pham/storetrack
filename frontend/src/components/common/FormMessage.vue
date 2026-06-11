<template>
  <p class="form-message" :class="[type, { block }]">
    <svg v-if="type === 'warning'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
      <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
    <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <span><slot>{{ text }}</slot></span>
  </p>
</template>

<script setup>
defineProps({
  // 'error' (red) | 'warning' (amber)
  type: { type: String, default: 'error' },
  // message text; alternatively pass content via the default slot
  text: { type: String, default: '' },
  // full-width block (e.g. a form-wide error) vs inline (beside a field/button)
  block: { type: Boolean, default: false },
})
</script>

<style scoped>
.form-message { display: inline-flex; align-items: center; gap: 7px; margin: 0; font-size: 13px; font-weight: 500; line-height: 1.45; }
.form-message.block { display: flex; width: 100%; align-items: flex-start; }
.form-message.block svg { margin-top: 1px; }
.form-message svg { flex-shrink: 0; }
.form-message.error { color: #dc2626; }
.form-message.warning { color: #b45309; }
</style>
