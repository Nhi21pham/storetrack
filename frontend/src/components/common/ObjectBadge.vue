<template>
  <span class="object-badge" :class="[`badge-${variant}`, { 'badge-block': block }]">
    {{ displayLabel }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const TYPE_LABELS = {
  business:     'Business',
  store:        'Store',
  user:         'User',
  invitation:   'Invitation',
  supplier:     'Supplier',
  customer:     'Customer',
  bank:         'Bank',
  bank_account: 'Bank Account',
  unit:         'Unit',
  product:      'Product',
  product_category: 'Category',
}

const KNOWN_VARIANTS = new Set(Object.keys(TYPE_LABELS))

const props = defineProps({
  type:  { type: String, default: '' },
  label: { type: String, default: '' },
  block: { type: Boolean, default: false },
})

const normalizedType = computed(() => String(props.type || '').toLowerCase())

const variant = computed(() =>
  KNOWN_VARIANTS.has(normalizedType.value) ? normalizedType.value : 'default'
)

const displayLabel = computed(() =>
  props.label || TYPE_LABELS[normalizedType.value] || props.type || '—'
)
</script>

<style scoped>
.object-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  line-height: 1.4;
  text-align: center;
}
.badge-block { min-width: 78px; align-self: flex-start; flex-shrink: 0; }

.badge-business     { background: #ffedd5; color: #c2410c; } /* orange  */
.badge-store        { background: #dbeafe; color: #1d4ed8; } /* blue    */
.badge-user         { background: #d1fae5; color: #065f46; } /* green   */
.badge-invitation   { background: #ede9fe; color: #6d28d9; } /* violet  */
.badge-supplier     { background: #fef9c3; color: #854d0e; } /* yellow  */
.badge-customer     { background: #fce7f3; color: #9d174d; } /* pink    */
.badge-bank         { background: #ccfbf1; color: #0f766e; } /* teal    */
.badge-bank_account { background: #fee2e2; color: #b91c1c; } /* red     */
.badge-unit         { background: #e0e7ff; color: #4338ca; } /* indigo  */
.badge-product      { background: #ecfccb; color: #4d7c0f; } /* lime    */
.badge-product_category { background: #fae8ff; color: #a21caf; } /* fuchsia */
.badge-default      { background: #f3f4f6; color: #6b7280; } /* gray    */
</style>
