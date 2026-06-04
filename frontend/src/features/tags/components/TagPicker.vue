<template>
  <div class="tag-picker">
    <div v-if="selected.length" class="selected-chips">
      <span v-for="(pair, idx) in selected" :key="idx" class="chip-wrap">
        <TagChip :tag-name="pair.tag_name" :value="pair.value" />
        <ChipRemoveButton title="Remove" @click="removeAt(idx)" />
      </span>
    </div>
    <p v-else class="no-tags">No tags attached.</p>

    <div class="add-row">
      <SelectField v-model="draftTagId" :disabled="loading" size="small" class="tag-select">
        <option value="">{{ loading ? 'Loading tags…' : 'Select a tag…' }}</option>
        <option v-for="t in tags" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
      </SelectField>
      <SelectField v-model="draftValueId" :disabled="!draftTagId || !draftTagValues.length" size="small" class="value-select">
        <option value="">{{ draftTagValues.length ? '(No value)' : 'No values' }}</option>
        <option v-for="v in draftTagValues" :key="v.id" :value="String(v.id)">{{ v.value }}</option>
      </SelectField>
      <button type="button" class="add-btn" :disabled="!draftTagId" @click="addDraft">Add</button>
      <AddItemButton v-if="canCreateTag" size="small" title="Create new tag" @click="showCreateTag = true" />
    </div>
    <p v-if="hint" class="picker-hint">{{ hint }}</p>

    <TagFormModal
      v-if="showCreateTag"
      :store-id="storeId"
      @close="showCreateTag = false"
      @saved="onTagCreated"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, inject } from 'vue'
import TagChip from '@/components/common/TagChip.vue'
import ChipRemoveButton from '@/components/common/ChipRemoveButton.vue'
import AddItemButton from '@/components/common/AddItemButton.vue'
import SelectField from '@/components/common/SelectField.vue'
import TagFormModal from '@/features/tags/components/TagFormModal.vue'
import { fetchTags } from '@/features/tags/services/tagService'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  storeId:    { type: [String, Number], default: null },
})

const emit = defineEmits(['update:modelValue'])

const currentStore = inject('currentStore', null)
const canCreateTag = computed(() => {
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return ['owner', 'accountant', 'staff'].includes(role)
})

const tags = ref([])
const loading = ref(false)
const selected = ref(normalizeIncoming(props.modelValue))
const draftTagId = ref('')
const draftValueId = ref('')
const hint = ref('')
const showCreateTag = ref(false)

function normalizeIncoming(list) {
  return (list || []).map(p => ({
    tag_id: String(p.tag_id),
    tag_value_id: p.tag_value_id != null ? String(p.tag_value_id) : null,
    tag_name: p.tag_name || '',
    value: p.value || '',
  }))
}

const draftTag = computed(() => tags.value.find(t => String(t.id) === draftTagId.value) || null)
const draftTagValues = computed(() => draftTag.value?.values || [])

watch(draftTagId, () => { draftValueId.value = '' })

const load = async () => {
  if (!props.storeId) return
  loading.value = true
  try {
    tags.value = await fetchTags({ storeId: props.storeId })
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => props.storeId, load)

const emitChange = () => {
  emit('update:modelValue', selected.value.map(p => ({
    tag_id: p.tag_id,
    tag_value_id: p.tag_value_id,
    tag_name: p.tag_name,
    value: p.value,
  })))
}

const addDraft = () => {
  hint.value = ''
  const tag = draftTag.value
  if (!tag) return
  const valueId = draftValueId.value || null
  const exists = selected.value.some(p => p.tag_id === String(tag.id) && (p.tag_value_id || null) === valueId)
  if (exists) {
    hint.value = 'That tag is already attached.'
    return
  }
  const valueObj = valueId ? draftTagValues.value.find(v => String(v.id) === valueId) : null
  selected.value.push({
    tag_id: String(tag.id),
    tag_value_id: valueId,
    tag_name: tag.name,
    value: valueObj?.value || '',
  })
  draftTagId.value = ''
  draftValueId.value = ''
  emitChange()
}

const removeAt = (idx) => {
  selected.value.splice(idx, 1)
  emitChange()
}

const onTagCreated = (tag) => {
  showCreateTag.value = false
  if (!tag) return
  const idx = tags.value.findIndex(t => String(t.id) === String(tag.id))
  if (idx === -1) tags.value.push(tag)
  else tags.value[idx] = tag
  draftTagId.value = String(tag.id)
}
</script>

<style scoped>
.tag-picker { display: flex; flex-direction: column; gap: 8px; }
.selected-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chip-wrap { display: inline-flex; align-items: center; }
.no-tags { font-size: 13px; color: #9ca3af; margin: 0; }

.add-row { display: flex; gap: 8px; align-items: stretch; }
.tag-select { flex: 1.2; min-width: 0; }
.value-select { flex: 1; min-width: 0; }
.add-btn { padding: 8px 16px; background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.add-btn:hover:not(:disabled) { background: #e0e7ff; }
.add-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.picker-hint { font-size: 12px; color: #b45309; margin: 0; }
</style>
