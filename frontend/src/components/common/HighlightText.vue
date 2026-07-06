<template>
  <span><template v-for="(part, i) in parts" :key="i"><mark v-if="part.hit" class="hl">{{ part.text }}</mark><template v-else>{{ part.text }}</template></template></span>
</template>

<script setup>
import { computed } from 'vue'
import { normalizeChars } from '@/utils/textNormalizer'

const props = defineProps({
  text:  { type: [String, Number], default: '' },
  query: { type: String, default: '' },
})

// Split the text into hit / non-hit runs around every occurrence of the query.
// Matching runs over a length-preserving normalized copy, so slice indices line
// up with the original text and its accents/casing are kept in the output.
const parts = computed(() => {
  const text = String(props.text ?? '')
  const q = (props.query || '').trim()
  if (!q || !text) return [{ text, hit: false }]

  const nText = normalizeChars(text)
  const nQ = normalizeChars(q)
  if (!nQ) return [{ text, hit: false }]

  const out = []
  let from = 0
  let idx = nText.indexOf(nQ, from)
  while (idx !== -1) {
    if (idx > from) out.push({ text: text.slice(from, idx), hit: false })
    out.push({ text: text.slice(idx, idx + nQ.length), hit: true })
    from = idx + nQ.length
    idx = nText.indexOf(nQ, from)
  }
  if (from < text.length) out.push({ text: text.slice(from), hit: false })
  return out
})
</script>

<style scoped>
.hl {
  background: #fde68a;
  color: inherit;
  border-radius: 3px;
  padding: 0 1px;
  box-shadow: 0 0 0 1px rgba(217, 164, 6, 0.35);
}
</style>
