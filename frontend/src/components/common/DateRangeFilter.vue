<template>
  <div class="date-range">
    <div class="filter-group">
      <label>{{ displayFromLabel }}</label>
      <DatePicker
        :model-value="startDate"
        :max="endDate"
        @update:model-value="$emit('update:startDate', $event)"
        @change="$emit('change')"
      />
    </div>
    <div class="filter-group">
      <label>{{ displayToLabel }}</label>
      <DatePicker
        :model-value="endDate"
        :min="startDate"
        @update:model-value="$emit('update:endDate', $event)"
        @change="$emit('change')"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { t } from '@/i18n'
import DatePicker from '@/components/common/DatePicker.vue'

const props = defineProps({
  startDate: { type: String, default: '' },
  endDate:   { type: String, default: '' },
  // Empty defaults = fall back to localized text, computed reactively below.
  fromLabel: { type: String, default: '' },
  toLabel:   { type: String, default: '' },
})

const displayFromLabel = computed(() => props.fromLabel || t('shared.from'))
const displayToLabel = computed(() => props.toLabel || t('shared.to'))

// `change` fires when a date is committed — for callers that filter on apply
// (e.g. server-side). v-model callers can just react to startDate/endDate.
defineEmits(['update:startDate', 'update:endDate', 'change'])
</script>

<style scoped>
.date-range { display: flex; align-items: flex-end; gap: 10px; }
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-size: 11.5px; font-weight: 500; color: #9ca3af; letter-spacing: 0.02em; text-transform: uppercase; }
.filter-group input { padding: 7px 11px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13.5px; font-family: inherit; color: #374151; background: #fafafa; cursor: pointer; outline: none; transition: border-color 0.2s, background 0.2s, box-shadow 0.2s; min-height: 36px; }
.filter-group input:hover { background: #fff; border-color: #d1d5db; }
.filter-group input:focus { background: #fff; border-color: #9ca3af; box-shadow: 0 0 0 3px rgba(156,163,175,0.12); }
</style>
