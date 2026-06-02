<template>
  <button
    type="button"
    class="add-item-btn"
    :class="{ small: size === 'small' }"
    :title="title"
    :disabled="disabled"
    @click="$emit('click')"
  >
    <svg :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title:    { type: String,  default: '' },
  disabled: { type: Boolean, default: false },
  size:     { type: String,  default: 'default' },
})

defineEmits(['click'])

const iconSize = computed(() => props.size === 'small' ? 14 : 16)
</script>

<style scoped>
.add-item-btn {
  display: inline-flex; align-items: center; justify-content: center;
  flex-shrink: 0; width: 44px; min-height: 44px;
  background: #fff; color: #374151;
  border: 1px solid #d1d5db; border-radius: 10px;
  cursor: pointer; transition: all 0.15s;
}
.add-item-btn:hover:not(:disabled) { background: #111; color: #fff; border-color: #111; }
.add-item-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.add-item-btn.small { width: 38px; min-height: 38px; border-radius: 8px; }
</style>
