<template>
  <button
    :type="type"
    class="base-btn"
    :class="[variant, { block }]"
    :disabled="disabled"
    @click="$emit('click', $event)"
  >
    <slot />
  </button>
</template>

<script setup>
defineProps({
  variant: {
    type: String,
    default: 'primary', // primary | secondary | danger
  },
  type: {
    type: String,
    default: 'button',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  block: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['click'])
</script>

<style scoped>
.base-btn { padding: 14px 18px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.base-btn.block { flex: 1; width: 100%; }
.base-btn:disabled { cursor: not-allowed; }
.base-btn.primary { background: #111; color: #fff; }
.base-btn.primary:hover:not(:disabled) { background: #333; }
.base-btn.primary:disabled { background: #555; }
.base-btn.secondary { background: #f3f4f6; color: #111; }
.base-btn.secondary:hover:not(:disabled) { background: #e9eaec; }
.base-btn.secondary:disabled { opacity: 0.6; }
.base-btn.danger { background: #dc2626; color: #fff; }
.base-btn.danger:hover:not(:disabled) { background: #b91c1c; }
.base-btn.danger:disabled { opacity: 0.6; }
</style>
