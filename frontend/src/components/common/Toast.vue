<template>
  <div v-if="visible" class="toast" :class="type">
    <svg v-if="type === 'success'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <path d="M20 6L9 17l-5-5"/>
    </svg>
    <svg v-else-if="type === 'error'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
    </svg>
    <span>{{ message }}</span>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  message: { type: String, default: '' },
  type: { type: String, default: 'success' },
  duration: { type: Number, default: 3000 }
})

const emit = defineEmits(['done'])
const visible = ref(false)
let timer = null

watch(() => props.message, (val) => {
  if (val) {
    visible.value = true
    clearTimeout(timer)
    timer = setTimeout(() => {
      visible.value = false
      emit('done')
    }, props.duration)
  }
}, { immediate: true })
</script>

<style scoped>
.toast { position: fixed; top: 24px; left: 24px; display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-radius: 10px; font-size: 14px; font-weight: 500; box-shadow: 0 8px 30px rgba(0,0,0,0.12); z-index: 5000; animation: slideIn 0.3s ease; }
.toast.success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.toast.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

@keyframes slideIn {
  from { opacity: 0; transform: translateX(-20px); }
  to { opacity: 1; transform: translateX(0); }
}
</style>