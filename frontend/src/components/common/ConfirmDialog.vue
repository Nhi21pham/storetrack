<template>
  <div class="confirm-overlay" @click.self="$emit('cancel')">
    <div class="confirm-box">
      <div class="confirm-icon" :class="type">
        <!-- Warning triangle -->
        <svg v-if="type === 'warning'" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <!-- Danger X -->
        <svg v-else-if="type === 'danger'" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <!-- Success checkmark -->
        <svg v-else width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
      </div>
      <h3>{{ title }}</h3>
      <p>{{ message }}</p>
      <div class="confirm-actions">
        <button class="btn-cancel" @click="$emit('cancel')">{{ cancelText }}</button>
        <button class="btn-confirm" :class="type" @click="$emit('confirm')">{{ confirmText }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: 'Unsaved Changes'
  },
  message: {
    type: String,
    default: 'You have unsaved changes. Are you sure you want to cancel?'
  },
  confirmText: {
    type: String,
    default: 'Yes, discard'
  },
  cancelText: {
    type: String,
    default: 'Keep editing'
  },
  type: {
    type: String,
    default: 'warning'  // warning, danger, success
  }
})

defineEmits(['confirm', 'cancel'])
</script>

<style scoped>
.confirm-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 3000; }
.confirm-box { background: #fff; border-radius: 16px; padding: 32px; width: 100%; max-width: 380px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); text-align: center; }
.confirm-icon { margin-bottom: 16px; }
.confirm-icon.warning { color: #f59e0b; }
.confirm-icon.danger { color: #dc2626; }
.confirm-icon.success { color: #16a34a; }
.confirm-box h3 { font-size: 18px; font-weight: 700; color: #111; margin-bottom: 8px; }
.confirm-box p { font-size: 14px; color: #6b7280; margin-bottom: 24px; line-height: 1.6; }
.confirm-actions { display: flex; gap: 12px; }
.btn-cancel { flex: 1; padding: 12px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-cancel:hover { background: #e9eaec; }
.btn-confirm { flex: 1; padding: 12px; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-confirm.warning { background: #f59e0b; }
.btn-confirm.warning:hover { background: #d97706; }
.btn-confirm.danger { background: #dc2626; }
.btn-confirm.danger:hover { background: #b91c1c; }
.btn-confirm.success { background: #16a34a; }
.btn-confirm.success:hover { background: #15803d; }
.btn-confirm:hover { background: #b91c1c; }
</style>