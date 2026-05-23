<template>
  <div class="inactive-banner">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="12" cy="12" r="10"/>
      <line x1="12" y1="8" x2="12" y2="12"/>
      <line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <slot />
  </div>
</template>

<style scoped>
.inactive-banner { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; font-size: 13px; color: #92400e; margin-bottom: 16px; }
</style>
