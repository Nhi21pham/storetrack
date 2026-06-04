<template>
  <select
    class="select-field"
    :class="[size, { error, inline }]"
    :value="modelValue"
    :disabled="disabled"
    @change="$emit('update:modelValue', $event.target.value)"
  >
    <slot />
  </select>
</template>

<script setup>
defineProps({
  modelValue: { type: [String, Number], default: '' },
  disabled:   { type: Boolean, default: false },
  error:      { type: Boolean, default: false },
  size:       { type: String,  default: 'default' },
  inline:     { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])
</script>

<style scoped>
.select-field {
  width: 100%;
  border: 1px solid #d1d5db; border-radius: 8px;
  color: #111; outline: none; box-sizing: border-box;
  transition: border-color 0.15s;
  appearance: none; -webkit-appearance: none;
  cursor: pointer;
  background-color: #fff;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  background-size: 14px;
}
.select-field.default { padding: 10px 36px 10px 12px; font-size: 14px; }
.select-field.small   { padding: 8px 30px 8px 10px;   font-size: 13px; }
.select-field.inline  { width: auto; }

.select-field:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.select-field.error { border-color: #dc2626; }
.select-field:disabled { background-color: #f9fafb; color: #9ca3af; cursor: not-allowed; }
</style>
