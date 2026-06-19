<template>
  <ReferenceChipsBanner :groups="groups" @create="openChip" />

  <component
    :is="activeModal.component"
    v-if="active"
    v-bind="activeModal.props"
    @close="closeModal"
    @saved="onResolved"
    @pick-existing="onResolved"
  />
</template>

<script setup>
import { ref, computed } from 'vue'
import ReferenceChipsBanner from '@/components/common/ReferenceChipsBanner.vue'
import BankFormModal from '@/features/banking/components/BankFormModal.vue'
import ProductCategoryFormModal from '@/features/productCategories/components/ProductCategoryFormModal.vue'
import UnitFormModal from '@/features/units/components/UnitFormModal.vue'
import TagFormModal from '@/features/tags/components/TagFormModal.vue'

const props = defineProps({
  rows: { type: Array, default: () => [] },
  // Scope ids forwarded to whichever create modal a chip opens.
  storeId: { type: [String, Number], default: null },
  businessId: { type: [String, Number], default: null },
  // From ImportModal's review-banner slot: clears the column's error on rows
  // whose cell matches a now-created record, then re-validates.
  resolveReference: { type: Function, required: true },
})

// One entry per reference type the importers can emit. `column` is the review
// column whose error this clears; `multiValue` cells (e.g. Tags) pack several
// references, so we can't optimistically clear by cell value and just revalidate.
const REF_TYPES = {
  bank:      { label: 'Banks',      component: BankFormModal,            column: 'Bank' },
  category:  { label: 'Categories', component: ProductCategoryFormModal, column: 'Category' },
  unit:      { label: 'Units',      component: UnitFormModal,            column: 'Unit' },
  tag:       { label: 'Tags',       component: TagFormModal,             column: 'Tags', multiValue: true },
  tag_value: { label: 'Tag values', component: TagFormModal,             column: 'Tags', multiValue: true },
}
const TYPE_ORDER = ['bank', 'category', 'unit', 'tag', 'tag_value']

const active = ref(null) // { type, key, value }

// Walk every row's unresolved references once, bucketed by type. References are
// re-issued by the server on each (re)validate, so resolved ones drop off here.
const refsByType = computed(() => {
  const buckets = {}
  for (const type of TYPE_ORDER) buckets[type] = []
  for (const row of props.rows) {
    for (const ref of row.references || []) {
      if (buckets[ref.type]) buckets[ref.type].push(ref)
    }
  }
  return buckets
})

// Build the deduped chips for each present type. A chip carries the key/value
// the create form is prefilled with; tag/tag_value chips key off both.
const groups = computed(() =>
  TYPE_ORDER
    .map((type) => ({ type, label: REF_TYPES[type].label, chips: buildChips(type, refsByType.value[type]) }))
    .filter((group) => group.chips.length > 0),
)

const buildChips = (type, refs) => {
  const seen = new Map()
  for (const ref of refs) {
    const key = String(ref.key ?? '').trim()
    const value = String(ref.value ?? '').trim()
    const isTag = type === 'tag' || type === 'tag_value'
    const identity = isTag ? key : value
    if (!identity) continue
    const id = isTag ? `${key.toLowerCase()}::${value.toLowerCase()}` : value.toLowerCase()
    if (seen.has(id)) continue
    const label = type === 'tag_value' ? `${key}: ${value}` : (isTag ? key : value)
    seen.set(id, { id, label, key, value })
  }
  return [...seen.values()]
}

const openChip = (type, chip) => {
  active.value = { type, key: chip.key, value: chip.value }
}

const closeModal = () => { active.value = null }

const activeModal = computed(() => {
  if (!active.value) return { component: null, props: {} }
  const { type, key, value } = active.value
  const def = REF_TYPES[type]
  let modalProps
  if (type === 'bank') {
    modalProps = { businessId: props.businessId, prefillShortName: value }
  } else if (type === 'tag' || type === 'tag_value') {
    modalProps = { storeId: props.storeId, prefillName: key, prefillValue: value }
  } else {
    modalProps = { storeId: props.storeId, prefillName: value }
  }
  return { component: def.component, props: modalProps }
})

const onResolved = () => {
  const current = active.value
  closeModal()
  if (!current) return
  const def = REF_TYPES[current.type]
  // Tags pack several references per cell, so re-validate instead of clearing by value.
  props.resolveReference(def.column, def.multiValue ? [] : [current.value])
}
</script>
