<template>
  <div class="searchable-select" ref="rootEl">
    <button type="button" class="ss-trigger" :class="{ open }" @click="toggleOpen">
      <span class="ss-value" :class="{ 'ss-placeholder': !selectedLabel }">
        {{ selectedLabel || placeholder }}
      </span>
      <svg class="ss-chevron" :class="{ open }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>
    <div v-if="open" class="ss-panel">
      <div class="ss-search">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input
          ref="searchEl"
          v-model="search"
          type="text"
          :placeholder="searchPlaceholder"
          @keydown.esc.prevent="close"
        />
      </div>
      <ul class="ss-list">
        <li
          v-if="allowAll"
          class="ss-option"
          :class="{ 'ss-option--active': modelValue === '' }"
          @click="select('')"
        >
          {{ allLabel }}
        </li>
        <li
          v-for="opt in filteredOptions"
          :key="opt.value"
          class="ss-option"
          :class="{ 'ss-option--active': modelValue === opt.value }"
          @click="select(opt.value)"
        >
          {{ opt.label }}
        </li>
        <li v-if="filteredOptions.length === 0" class="ss-empty">No matches</li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  options: { type: Array, required: true },
  placeholder: { type: String, default: 'Select...' },
  searchPlaceholder: { type: String, default: 'Search...' },
  allowAll: { type: Boolean, default: true },
  allLabel: { type: String, default: 'All' },
})

const emit = defineEmits(['update:modelValue', 'change'])

const rootEl   = ref(null)
const searchEl = ref(null)
const open     = ref(false)
const search   = ref('')

const selectedLabel = computed(() => {
  if (props.modelValue === '' || props.modelValue == null) {
    return props.allowAll ? props.allLabel : ''
  }
  const found = props.options.find(o => o.value === props.modelValue)
  return found ? found.label : ''
})

const filteredOptions = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.options
  return props.options.filter(o => o.label.toLowerCase().includes(q))
})

const toggleOpen = () => {
  open.value = !open.value
  if (open.value) {
    search.value = ''
    nextTick(() => searchEl.value?.focus())
  }
}

const close = () => {
  open.value = false
}

const select = (value) => {
  emit('update:modelValue', value)
  emit('change', value)
  close()
}

const onDocumentClick = (e) => {
  if (!rootEl.value) return
  if (!rootEl.value.contains(e.target)) close()
}

onMounted(() => document.addEventListener('mousedown', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocumentClick))
</script>

<style scoped>
.searchable-select { position: relative; }

.ss-trigger {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  min-width: 140px; min-height: 36px;
  padding: 7px 11px;
  border: 1px solid #e5e7eb; border-radius: 10px;
  background: #fafafa; color: #374151; font-size: 13.5px; font-family: inherit;
  cursor: pointer; outline: none;
  transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
}
.ss-trigger:hover { background: #fff; border-color: #d1d5db; }
.ss-trigger.open { background: #fff; border-color: #9ca3af; box-shadow: 0 0 0 3px rgba(156,163,175,0.12); }

.ss-value { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ss-placeholder { color: #9ca3af; }
.ss-chevron { color: #6b7280; flex-shrink: 0; transition: transform 0.15s; }
.ss-chevron.open { transform: rotate(180deg); }

.ss-panel {
  position: absolute; top: calc(100% + 4px); left: 0; z-index: 20;
  min-width: 100%;
  background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
  overflow: hidden;
}

.ss-search {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 10px; border-bottom: 1px solid #f3f4f6;
}
.ss-search svg { color: #9ca3af; flex-shrink: 0; }
.ss-search input {
  flex: 1; border: none; outline: none; background: none;
  font-size: 13px; font-family: inherit; color: #111;
}
.ss-search input::placeholder { color: #9ca3af; }

.ss-list {
  list-style: none; margin: 0; padding: 4px 0;
  max-height: 220px; overflow-y: auto;
}

.ss-option {
  padding: 7px 12px; font-size: 13.5px; color: #374151;
  cursor: pointer; transition: background 0.12s;
}
.ss-option:hover { background: #f9fafb; }
.ss-option--active { background: #eff6ff; color: #1d4ed8; font-weight: 500; }
.ss-option--active:hover { background: #dbeafe; }

.ss-empty { padding: 10px 12px; font-size: 13px; color: #9ca3af; text-align: center; }
</style>
