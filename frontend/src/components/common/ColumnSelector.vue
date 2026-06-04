<template>
  <div class="column-selector" ref="rootEl">
    <button type="button" class="cs-trigger" :class="{ open }" @click="open = !open" title="Choose columns">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
      <span class="cs-label">Columns</span>
      <span class="cs-count">{{ visibleCount }}/{{ togglableColumns.length }}</span>
    </button>

    <div v-if="open" class="cs-panel">
      <div class="cs-header">
        <span>Show columns</span>
        <button type="button" class="cs-reset" title="Reset to default" @click="resetColumns">
          <Icon name="undo" :size="13" :stroke-width="2.2" />
        </button>
      </div>
      <ul class="cs-list">
        <li class="cs-option cs-select-all" @click="toggleAll">
          <input
            type="checkbox"
            ref="selectAllEl"
            :checked="allVisible"
            @click.stop="toggleAll"
          />
          <span>Select all</span>
        </li>
        <li class="cs-divider" role="separator"></li>
        <li
          v-for="col in togglableColumns"
          :key="col.key"
          class="cs-option"
          @click="toggleColumn(col.key)"
        >
          <input
            type="checkbox"
            :checked="isVisible(col.key)"
            @click.stop="toggleColumn(col.key)"
          />
          <span>{{ col.label || col.key }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watchEffect, onMounted, onBeforeUnmount } from 'vue'
import Icon from '@/components/common/Icon.vue'

const props = defineProps({
  togglableColumns: { type: Array, required: true },
  isVisible: { type: Function, required: true },
  toggleColumn: { type: Function, required: true },
  resetColumns: { type: Function, required: true },
})

const rootEl = ref(null)
const selectAllEl = ref(null)
const open = ref(false)

const visibleCount = computed(() =>
  props.togglableColumns.filter(c => props.isVisible(c.key)).length
)

const allVisible = computed(() =>
  props.togglableColumns.length > 0 && visibleCount.value === props.togglableColumns.length
)

const someVisible = computed(() =>
  visibleCount.value > 0 && !allVisible.value
)

const toggleAll = () => {
  const target = !allVisible.value
  props.togglableColumns.forEach(col => {
    if (props.isVisible(col.key) !== target) props.toggleColumn(col.key)
  })
}

watchEffect(() => {
  if (selectAllEl.value) selectAllEl.value.indeterminate = someVisible.value
})

const onDocumentClick = (e) => {
  if (!rootEl.value) return
  if (!rootEl.value.contains(e.target)) open.value = false
}

onMounted(() => document.addEventListener('mousedown', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocumentClick))
</script>

<style scoped>
.column-selector { position: relative; }

.cs-trigger {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 14px;
  border: 1px solid #e5e7eb; border-radius: 10px;
  background: #fff; color: #374151; font-size: 13.5px; font-weight: 500;
  cursor: pointer; outline: none; font-family: inherit;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.cs-trigger:hover { border-color: #d1d5db; background: #fafafa; }
.cs-trigger.open { border-color: #9ca3af; box-shadow: 0 0 0 3px rgba(156,163,175,0.12); }
.cs-label { line-height: 1; }
.cs-count {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 2px 7px; border-radius: 999px;
  background: #f3f4f6; color: #6b7280;
  font-size: 11.5px; font-weight: 600;
}

.cs-panel {
  position: absolute; top: calc(100% + 6px); right: 0; z-index: 30;
  min-width: 220px;
  background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
  overflow: hidden;
}

.cs-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 12px; border-bottom: 1px solid #f3f4f6;
  font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em;
}
.cs-reset {
  display: inline-flex; align-items: center; justify-content: center;
  width: 24px; height: 24px;
  background: none; border: none; padding: 0; border-radius: 6px;
  color: #6b7280; cursor: pointer;
  transition: background 0.12s, color 0.12s;
}
.cs-reset:hover { background: #f3f4f6; color: #111; }

.cs-list { list-style: none; margin: 0; padding: 4px 0; max-height: 280px; overflow-y: auto; }

.cs-option {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 12px; font-size: 13.5px; color: #374151;
  cursor: pointer; transition: background 0.12s;
}
.cs-option:hover { background: #f9fafb; }
.cs-option input { accent-color: #111; cursor: pointer; }

.cs-select-all { font-weight: 600; color: #111; }

.cs-divider { height: 1px; margin: 4px 0; background: #f3f4f6; }
</style>
