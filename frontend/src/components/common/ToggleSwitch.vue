<template>
  <label
    class="toggle-switch"
    :class="{ active: modelValue, disabled }"
    :title="title"
    @click.prevent="onClick"
  >
    <span class="toggle-slider"></span>
  </label>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Boolean, required: true },
  disabled: { type: Boolean, default: false },
  title: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'change'])

const onClick = () => {
  if (props.disabled) return
  const next = !props.modelValue
  emit('update:modelValue', next)
  emit('change', next)
}
</script>

<style scoped>
.toggle-switch { position: relative; display: inline-block; width: 36px; height: 20px; background: #d1d5db; border-radius: 10px; cursor: pointer; transition: background 0.2s; flex-shrink: 0; }
.toggle-switch.active { background: #16a34a; }
.toggle-switch.disabled { opacity: 0.5; cursor: not-allowed; }
.toggle-slider { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.15); }
.toggle-switch.active .toggle-slider { transform: translateX(16px); }
</style>
