<template>
  <!-- Native date input kept for the calendar UX (and min/max), but rendered
       transparently over our own formatted display so the visible field always
       reads in the active locale (dd/mm/yyyy for vi) regardless of browser. -->
  <div class="date-field" :class="{ error }">
    <span class="date-display" :class="{ placeholder: !modelValue }">{{ displayText }}</span>
    <svg class="cal-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <input
      ref="nativeRef"
      type="date"
      class="native"
      :value="modelValue"
      :min="min || undefined"
      :max="max || undefined"
      @click="openPicker"
      @input="$emit('update:modelValue', $event.target.value)"
      @change="$emit('change')"
    />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { activeLocale } from '@/i18n'

const props = defineProps({
  modelValue: { type: String, default: '' },
  min:        { type: String, default: '' },
  max:        { type: String, default: '' },
  error:      { type: Boolean, default: false },
})

defineEmits(['update:modelValue', 'change'])

const nativeRef = ref(null)
defineExpose({ focus: () => nativeRef.value?.focus() })

const openPicker = () => {
  try {
    nativeRef.value?.showPicker?.()
  } catch {
    // showPicker throws outside a user gesture.
  }
}

// dd/mm/yyyy for Vietnamese, mm/dd/yyyy for English — derived from the
// YYYY-MM-DD model value so it's stable across browsers/timezones.
const displayText = computed(() => {
  if (!props.modelValue) return activeLocale() === 'vi' ? 'dd/mm/yyyy' : 'mm/dd/yyyy'
  const [y, m, d] = props.modelValue.split('-')
  if (!y || !m || !d) return props.modelValue
  return activeLocale() === 'vi' ? `${d}/${m}/${y}` : `${m}/${d}/${y}`
})
</script>

<style scoped>
.date-field {
  position: relative;
  display: inline-flex; align-items: center; gap: 8px;
  min-width: 140px; min-height: 36px;
  padding: 7px 11px;
  border: 1px solid #e5e7eb; border-radius: 10px;
  background: #fafafa; color: #374151; font-size: 13.5px;
  cursor: pointer; transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
}
.date-field:hover { background: #fff; border-color: #d1d5db; }
.date-field:focus-within { background: #fff; border-color: #9ca3af; box-shadow: 0 0 0 3px rgba(156,163,175,0.12); }
.date-field.error { border-color: #dc2626; }

.date-display { flex: 1; white-space: nowrap; }
.date-display.placeholder { color: #9ca3af; }
.cal-icon { color: #6b7280; flex-shrink: 0; }

/* The real input sits invisibly on top so a click opens the native calendar. */
.native {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  opacity: 0; cursor: pointer;
  border: none; padding: 0; margin: 0;
}
</style>
