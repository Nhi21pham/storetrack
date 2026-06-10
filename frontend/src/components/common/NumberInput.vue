<template>
  <input
    ref="el"
    type="text"
    inputmode="decimal"
    :value="display"
    @input="onInput"
    @blur="onBlur"
  />
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  decimals: { type: Number, default: 2 },
})
const emit = defineEmits(['update:modelValue'])

const el = ref(null)

const stripLeadingZeros = (intPart) => intPart.replace(/^0+(?=\d)/, '')
const groupThousands = (intPart) => intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',')

// Raw numeric string -> grouped display (e.g. "1000000.5" -> "1,000,000.5").
const format = (raw) => {
  if (raw === '' || raw == null) return ''
  const cleaned = String(raw).replace(/[^0-9.]/g, '')
  if (cleaned === '') return ''
  const dot = cleaned.indexOf('.')
  if (dot === -1) return groupThousands(stripLeadingZeros(cleaned))
  const intPart = stripLeadingZeros(cleaned.slice(0, dot))
  const decPart = cleaned.slice(dot + 1)
  return groupThousands(intPart || '0') + '.' + decPart
}

const display = computed(() => format(props.modelValue))

// Typed text -> raw numeric string: drop separators, keep one dot, cap decimals.
const sanitize = (value) => {
  let raw = String(value).replace(/[^0-9.]/g, '')
  const dot = raw.indexOf('.')
  if (dot !== -1) {
    const intPart = raw.slice(0, dot)
    let decPart = raw.slice(dot + 1).replace(/\./g, '')
    if (props.decimals >= 0) decPart = decPart.slice(0, props.decimals)
    raw = intPart + '.' + decPart
  }
  return raw
}

const significantLength = (str) => str.replace(/,/g, '').length

const onInput = (e) => {
  const input = e.target
  const caret = input.selectionStart ?? input.value.length
  const sigBefore = significantLength(input.value.slice(0, caret))

  const raw = sanitize(input.value)
  emit('update:modelValue', raw)

  // Re-apply the grouped value and restore the caret by digit count, since
  // inserted commas would otherwise drift the cursor.
  const formatted = format(raw)
  requestAnimationFrame(() => {
    if (!el.value) return
    el.value.value = formatted
    let count = 0
    let pos = 0
    while (pos < formatted.length && count < sigBefore) {
      if (formatted[pos] !== ',') count++
      pos++
    }
    el.value.setSelectionRange(pos, pos)
  })
}

const onBlur = (e) => {
  const raw = sanitize(e.target.value).replace(/\.$/, '')
  if (raw !== String(props.modelValue)) emit('update:modelValue', raw)
}
</script>
