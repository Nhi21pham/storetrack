<template>
  <div class="filter-bar">
    <SearchBar
      :modelValue="searchQuery"
      placeholder="Search by name, email, tax code, phone or address..."
      @update:modelValue="$emit('update:searchQuery', $event)"
    />
    <div class="store-filter">
      <button
        :class="{ active: storeFilter === 'store' }"
        @click="$emit('update:storeFilter', 'store')"
      >This store</button>
      <button
        :class="{ active: storeFilter === 'all' }"
        @click="$emit('update:storeFilter', 'all')"
      >All stores</button>
    </div>
    <button v-if="hasSort" class="btn-clear-sort" @click="$emit('clearSort')">
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      Clear sort
    </button>
    <button
      class="btn-export"
      :disabled="exporting || !canExport"
      :title="exporting ? 'Preparing export...' : 'Export current view to Excel'"
      @click="$emit('export')"
    >
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      <span>{{ exporting ? 'Exporting...' : 'Export' }}</span>
    </button>
  </div>
</template>

<script setup>
import SearchBar from '@/components/common/SearchBar.vue'

defineProps({
  searchQuery: { type: String, required: true },
  storeFilter: { type: String, required: true },
  hasSort:     { type: Boolean, required: true },
  exporting:   { type: Boolean, required: true },
  canExport:   { type: Boolean, required: true },
})

defineEmits(['update:searchQuery', 'update:storeFilter', 'clearSort', 'export'])
</script>

<style scoped>
.filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.filter-bar > :first-child { flex: 1; margin-bottom: 0; }

.store-filter { display: flex; background: #f3f4f6; border-radius: 8px; padding: 3px; gap: 2px; flex-shrink: 0; }
.store-filter button { padding: 5px 14px; border: none; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; background: none; color: #6b7280; transition: all 0.15s; white-space: nowrap; }
.store-filter button.active { background: #fff; color: #111; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

.btn-clear-sort { display: flex; align-items: center; gap: 5px; padding: 5px 10px; border: 1px solid #e5e7eb; background: #fff; color: #6b7280; border-radius: 7px; font-size: 12px; font-weight: 500; cursor: pointer; white-space: nowrap; transition: all 0.15s; flex-shrink: 0; }
.btn-clear-sort:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }

.btn-export { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border: 1px solid #111; border-radius: 7px; font-size: 12.5px; font-weight: 600; color: #fff; background: #111; cursor: pointer; transition: background 0.2s, opacity 0.2s; white-space: nowrap; flex-shrink: 0; margin-left: auto; }
.btn-export:hover:not(:disabled) { background: #000; }
.btn-export:disabled { opacity: 0.55; cursor: not-allowed; }
</style>
