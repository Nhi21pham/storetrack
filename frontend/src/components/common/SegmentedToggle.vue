<template>
  <div class="segmented-toggle">
    <button
      v-for="opt in options"
      :key="opt.value"
      type="button"
      :class="{ active: modelValue === opt.value }"
      @click="$emit('update:modelValue', opt.value)"
    >{{ opt.label }}</button>
  </div>
</template>

<script setup>
// A pill-style segmented control. `options` is [{ value, label }]; binds via v-model.
defineProps({
  modelValue: { type: [String, Number], required: true },
  options: { type: Array, required: true },
})
defineEmits(['update:modelValue'])
</script>

<style scoped>
.segmented-toggle { display: inline-flex; background: #f3f4f6; border-radius: 10px; padding: 3px; }
.segmented-toggle button { padding: 8px 16px; border: none; background: none; border-radius: 8px; font-size: 13.5px; font-weight: 600; color: #6b7280; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.segmented-toggle button.active { background: #fff; color: #111; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
</style>
