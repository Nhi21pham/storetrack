<template>
  <div class="filter-bar">
    <div class="filter-row">
      <DateRangeFilter
        :start-date="startDate"
        :end-date="endDate"
        @update:startDate="$emit('update:startDate', $event)"
        @update:endDate="$emit('update:endDate', $event)"
        @change="$emit('apply')"
      />
      <div class="filter-group">
        <label>{{ $t('audit.type') }}</label>
        <SearchableSelect
          :modelValue="objectFilter"
          :options="objectOptions()"
          :all-label="$t('common.all')"
          :search-placeholder="$t('audit.searchType')"
          @update:modelValue="$emit('update:objectFilter', $event)"
          @change="$emit('apply')"
        />
      </div>
      <div class="filter-group">
        <label>{{ $t('audit.action') }}</label>
        <SearchableSelect
          :modelValue="actionFilter"
          :options="actionOptions()"
          :all-label="$t('common.all')"
          :search-placeholder="$t('audit.searchAction')"
          @update:modelValue="$emit('update:actionFilter', $event)"
          @change="$emit('apply')"
        />
      </div>
      <button v-if="hasActiveFilter" class="btn-clear" @click="$emit('clear')">{{ $t('common.clear') }}</button>
      <ExportButton class="export-push" size="md" :exporting="exporting" :disabled="total === 0" @click="$emit('export')" />
    </div>
  </div>
</template>

<script setup>
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import DateRangeFilter from '@/components/common/DateRangeFilter.vue'
import ExportButton from '@/components/common/ExportButton.vue'
import { objectOptions, actionOptions } from '@/features/audit/constants'

defineProps({
  startDate:       { type: String, required: true },
  endDate:         { type: String, required: true },
  objectFilter:    { type: String, required: true },
  actionFilter:    { type: String, required: true },
  hasActiveFilter: { type: Boolean, required: true },
  exporting:       { type: Boolean, required: true },
  total:           { type: Number, required: true },
})

defineEmits([
  'update:startDate',
  'update:endDate',
  'update:objectFilter',
  'update:actionFilter',
  'apply',
  'clear',
  'export',
])
</script>

<style scoped>
.filter-bar { margin-bottom: 16px; }
.filter-row { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-size: 11.5px; font-weight: 500; color: #9ca3af; letter-spacing: 0.02em; text-transform: uppercase; }
.filter-group input,
.filter-group select { padding: 7px 11px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 13.5px; font-family: inherit; color: #374151; background: #fafafa; cursor: pointer; outline: none; transition: border-color 0.2s, background 0.2s, box-shadow 0.2s; min-height: 36px; }
.filter-group input:hover,
.filter-group select:hover { background: #fff; border-color: #d1d5db; }
.filter-group input:focus,
.filter-group select:focus { background: #fff; border-color: #9ca3af; box-shadow: 0 0 0 3px rgba(156,163,175,0.12); }

.btn-clear { padding: 7px 13px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 12.5px; font-weight: 500; font-family: inherit; color: #374151; background: #fff; cursor: pointer; transition: background 0.2s, color 0.2s, border-color 0.2s; height: 36px; align-self: flex-end; }
.btn-clear:hover { background: #f3f4f6; border-color: #9ca3af; color: #111; }

.export-push { align-self: flex-end; margin-left: auto; }
</style>
