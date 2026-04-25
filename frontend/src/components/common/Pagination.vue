<template>
  <div class="pagination-bar">
    <div class="pagination-left">
      <span class="page-info">{{ pageStart }}–{{ pageEnd }} of {{ total }}</span>
      <select :value="perPage" class="per-page-select" @change="$emit('update:perPage', +$event.target.value)">
        <option v-for="n in pageSizeOptions" :key="n" :value="n">{{ n }} / page</option>
      </select>
    </div>

    <div class="pagination-right">
      <button class="page-btn" :disabled="currentPage === 1" @click="$emit('update:currentPage', currentPage - 1)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </button>

      <button
        v-for="p in visiblePages"
        :key="p"
        class="page-btn num"
        :class="{ active: p === currentPage, ellipsis: p === '…' }"
        :disabled="p === '…'"
        @click="p !== '…' && $emit('update:currentPage', p)"
      >{{ p }}</button>

      <button class="page-btn" :disabled="currentPage === totalPages" @click="$emit('update:currentPage', currentPage + 1)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage:    { type: Number, required: true },
  totalPages:     { type: Number, required: true },
  total:          { type: Number, required: true },
  perPage:        { type: Number, default: 20 },
  pageSizeOptions:{ type: Array,  default: () => [10, 20, 50, 100] },
})

defineEmits(['update:currentPage', 'update:perPage'])

const pageStart = computed(() => Math.min((props.currentPage - 1) * props.perPage + 1, props.total))
const pageEnd   = computed(() => Math.min(props.currentPage * props.perPage, props.total))

const visiblePages = computed(() => {
  const { totalPages: total, currentPage: cur } = props
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const pages = [1]
  if (cur > 3) pages.push('…')
  for (let p = Math.max(2, cur - 1); p <= Math.min(total - 1, cur + 1); p++) pages.push(p)
  if (cur < total - 2) pages.push('…')
  pages.push(total)
  return pages
})
</script>

<style scoped>
.pagination-bar { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-top: 1px solid #f3f4f6; gap: 12px; flex-wrap: wrap; }

.pagination-left { display: flex; align-items: center; gap: 12px; }
.page-info { font-size: 13px; color: #6b7280; white-space: nowrap; }
.per-page-select { font-size: 13px; padding: 5px 8px; border: 1px solid #e5e7eb; border-radius: 7px; color: #374151; background: #fff; outline: none; cursor: pointer; }
.per-page-select:focus { border-color: #111; }

.pagination-right { display: flex; align-items: center; gap: 4px; }
.page-btn { display: flex; align-items: center; justify-content: center; min-width: 30px; height: 30px; padding: 0 6px; border: 1px solid #e5e7eb; background: #fff; color: #374151; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.15s; }
.page-btn:hover:not(:disabled):not(.ellipsis) { background: #f3f4f6; border-color: #d1d5db; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-btn.active { background: #111; color: #fff; border-color: #111; }
.page-btn.ellipsis { border: none; background: none; cursor: default; color: #9ca3af; }
</style>
