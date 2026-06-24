<template>
  <div class="loading-state">
    <div class="spinner"></div>
    <span><slot>{{ $t('shared.loading') }}</slot></span>
  </div>
</template>

<style scoped>
.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px 0; color: #6b7280; font-size: 14px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
