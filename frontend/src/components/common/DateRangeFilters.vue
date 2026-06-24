<template>
  <div class="filters-row">
    <div class="filter-group">
      <label>{{ $t('shared.dateField') }}</label>
      <select
        class="date-field-select"
        :value="dateField"
        @change="$emit('update:dateField', $event.target.value)"
      >
        <option v-for="field in fields" :key="field.value" :value="field.value">{{ field.label }}</option>
      </select>
    </div>
    <DateRangeFilter
      :start-date="startDate"
      :end-date="endDate"
      @update:start-date="$emit('update:startDate', $event)"
      @update:end-date="$emit('update:endDate', $event)"
    />
  </div>
</template>

<script setup>
import DateRangeFilter from '@/components/common/DateRangeFilter.vue'
import { t } from '@/i18n'

defineProps({
  startDate: { type: String, default: '' },
  endDate:   { type: String, default: '' },
  dateField: { type: String, default: 'created_at' },
  fields: {
    type: Array,
    default: () => [
      { value: 'created_at', label: t('common.createdAt') },
      { value: 'updated_at', label: t('common.updatedAt') },
    ],
  },
})

defineEmits(['update:startDate', 'update:endDate', 'update:dateField'])
</script>

<style scoped>
.filters-row { display: flex; align-items: flex-end; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-size: 11.5px; font-weight: 500; color: #9ca3af; letter-spacing: 0.02em; text-transform: uppercase; }
.date-field-select { padding: 7px 11px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13.5px; font-family: inherit; color: #374151; background: #fafafa; cursor: pointer; outline: none; min-height: 36px; }
.date-field-select:hover { background: #fff; border-color: #d1d5db; }
.date-field-select:focus { background: #fff; border-color: #9ca3af; box-shadow: 0 0 0 3px rgba(156,163,175,0.12); }
</style>
