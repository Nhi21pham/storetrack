<template>
  <div class="searchable-select" :class="{ 'ss-large': size === 'large' }" ref="rootEl">
    <button type="button" class="ss-trigger" :class="{ open }" @click="toggleOpen">
      <span class="ss-value" :class="{ 'ss-placeholder': !selectedLabel }" v-tooltip="selectedLabel">
        {{ selectedLabel || placeholder }}
      </span>
      <svg class="ss-chevron" :class="{ open }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>
    <Teleport to="body" :disabled="!teleport">
    <div
      v-if="open"
      class="ss-panel"
      :class="{ 'ss-panel--up': flipUp, 'ss-panel--floating': teleport }"
      :style="teleport ? floatingStyle : null"
      ref="panelEl"
    >
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
          :class="{ 'ss-option--active': allActive }"
          @click="select('')"
        >
          {{ displayAllLabel }}
        </li>
        <li
          v-for="opt in filteredOptions"
          :key="opt.value"
          class="ss-option"
          :class="{ 'ss-option--active': isSelected(opt.value), 'ss-option--multi': multiple }"
          @click="select(opt.value)"
          :title="opt.sublabel ? `${opt.label} — ${opt.sublabel}` : opt.label"
        >
          <span class="ss-option-label">{{ opt.label }}</span>
          <span v-if="opt.sublabel" class="ss-option-sublabel">{{ opt.sublabel }}</span>
          <svg v-if="multiple && isSelected(opt.value)" class="ss-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </li>
        <li v-if="filteredOptions.length === 0" class="ss-empty">{{ $t('shared.noMatches') }}</li>
      </ul>
    </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { t } from '@/i18n'

const props = defineProps({
  modelValue: { type: [String, Array], default: '' },
  options: { type: Array, required: true },
  placeholder: { type: String, default: 'Select...' },
  searchPlaceholder: { type: String, default: 'Search...' },
  allowAll: { type: Boolean, default: true },
  // Empty default = fall back to the localized "All", computed reactively below.
  allLabel: { type: String, default: '' },
  size: { type: String, default: 'default' },
  teleport: { type: Boolean, default: false },
  // When true, modelValue is an array; options toggle and the panel stays open.
  multiple: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const rootEl   = ref(null)
const panelEl  = ref(null)
const searchEl = ref(null)
const open     = ref(false)
const search   = ref('')
const flipUp   = ref(false)
const floatingStyle = ref(null)

const displayAllLabel = computed(() => props.allLabel || t('common.all'))

const selectedValues = computed(() =>
  Array.isArray(props.modelValue) ? props.modelValue : []
)

const isSelected = (value) =>
  props.multiple ? selectedValues.value.includes(value) : props.modelValue === value

const allActive = computed(() =>
  props.multiple
    ? selectedValues.value.length === 0
    : (props.modelValue === '' || props.modelValue == null)
)

const selectedLabel = computed(() => {
  if (props.multiple) {
    if (selectedValues.value.length === 0) {
      return props.allowAll ? displayAllLabel.value : ''
    }
    return props.options
      .filter(o => selectedValues.value.includes(o.value))
      .map(o => o.label)
      .join(', ')
  }
  if (props.modelValue === '' || props.modelValue == null) {
    return props.allowAll ? displayAllLabel.value : ''
  }
  const found = props.options.find(o => o.value === props.modelValue)
  return found ? found.label : ''
})

const filteredOptions = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.options
  return props.options.filter(o => {
    const label = (o.label || '').toLowerCase()
    const sublabel = (o.sublabel || '').toLowerCase()
    return label.includes(q) || sublabel.includes(q)
  })
})

const PANEL_ESTIMATED_HEIGHT = 300

const updateFlip = () => {
  if (!rootEl.value) return
  const rect = rootEl.value.getBoundingClientRect()
  const spaceBelow = window.innerHeight - rect.bottom
  const spaceAbove = rect.top
  flipUp.value = spaceBelow < PANEL_ESTIMATED_HEIGHT && spaceAbove > spaceBelow

  if (props.teleport) {
    const minWidth = Math.max(rect.width, 180)
    if (flipUp.value) {
      floatingStyle.value = {
        position: 'fixed',
        left:     `${rect.left}px`,
        bottom:   `${window.innerHeight - rect.top + 4}px`,
        minWidth: `${minWidth}px`,
      }
    } else {
      floatingStyle.value = {
        position: 'fixed',
        left:     `${rect.left}px`,
        top:      `${rect.bottom + 4}px`,
        minWidth: `${minWidth}px`,
      }
    }
  }
}

const toggleOpen = () => {
  open.value = !open.value
  if (open.value) {
    search.value = ''
    updateFlip()
    nextTick(() => {
      searchEl.value?.focus()
      if (!flipUp.value && panelEl.value) {
        panelEl.value.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
      }
    })
  }
}

const close = () => {
  open.value = false
}

const select = (value) => {
  if (props.multiple) {
    let next
    if (value === '') {
      next = []
    } else if (selectedValues.value.includes(value)) {
      next = selectedValues.value.filter(v => v !== value)
    } else {
      next = [...selectedValues.value, value]
    }
    emit('update:modelValue', next)
    emit('change', next)
    return
  }
  emit('update:modelValue', value)
  emit('change', value)
  close()
}

const onDocumentClick = (e) => {
  if (!rootEl.value) return
  if (rootEl.value.contains(e.target)) return
  if (panelEl.value && panelEl.value.contains(e.target)) return
  close()
}

onMounted(() => document.addEventListener('mousedown', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocumentClick))
</script>

<style scoped>
.searchable-select { position: relative; }

.ss-trigger {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  min-width: 140px; min-height: 36px;
  width: 100%;
  padding: 7px 11px;
  border: 1px solid #e5e7eb; border-radius: 10px;
  background: #fafafa; color: #374151; font-size: 13.5px; font-family: inherit;
  cursor: pointer; outline: none;
  transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
}
.ss-trigger:hover { background: #fff; border-color: #d1d5db; }
.ss-trigger.open { background: #fff; border-color: #9ca3af; box-shadow: 0 0 0 3px rgba(156,163,175,0.12); }

.ss-large .ss-trigger {
  min-height: 44px;
  padding: 11px 14px;
  font-size: 14.5px;
  background: #fff;
  border-color: #d1d5db;
}

.ss-value { flex: 1 1 auto; min-width: 0; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
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
.ss-panel--up {
  top: auto;
  bottom: calc(100% + 4px);
}
.ss-panel--floating { z-index: 2000; }

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
  max-height: 240px; overflow-y: auto;
}

.ss-option {
  display: flex; flex-direction: column; gap: 2px;
  padding: 8px 12px; font-size: 13.5px; color: #374151;
  cursor: pointer; transition: background 0.12s;
  position: relative;
}
.ss-option--multi { padding-right: 32px; }
.ss-check {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  color: #1d4ed8;
}
.ss-option:hover { background: #f9fafb; }
.ss-option--active { background: #eff6ff; color: #1d4ed8; font-weight: 500; }
.ss-option--active:hover { background: #dbeafe; }
.ss-option-label { line-height: 1.3; }
.ss-option-sublabel { font-size: 11.5px; color: #6b7280; line-height: 1.3; }
.ss-option--active .ss-option-sublabel { color: #3b82f6; }

.ss-empty { padding: 10px 12px; font-size: 13px; color: #9ca3af; text-align: center; }
</style>
