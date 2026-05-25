<template>
  <div class="table-wrapper" :class="{ resizing: isResizing }">
    <table>
      <colgroup>
        <col v-for="(w, i) in colWidths" :key="i" :style="{ width: w + 'px' }" />
      </colgroup>
      <thead>
        <tr>
          <th v-for="(col, i) in columns" :key="col.key" :class="col.headerClass">
            <slot :name="`header-${col.key}`" :col="col">{{ col.label }}</slot>
            <div
              v-if="i < columns.length - 1"
              class="resize-handle"
              @mousedown.prevent="startResize($event, i)"
            ></div>
          </th>
        </tr>
      </thead>
      <tbody>
        <slot />
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { useColumnResize } from '@/composables/useColumnResize'

const props = defineProps({
  columns: { type: Array, required: true },
  initialWidths: { type: Array, required: true },
})

const { colWidths, isResizing, startResize } = useColumnResize(props.initialWidths)

defineExpose({ colWidths, isResizing, startResize })
</script>

<style scoped>
.table-wrapper { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow-x: auto; }
.table-wrapper.resizing { cursor: col-resize; user-select: none; }

table { width: 100%; border-collapse: collapse; font-size: 13.5px; table-layout: fixed; }

thead { background: #f9fafb; }
th { position: relative; padding: 11px 16px; text-align: left; font-size: 11.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e5e7eb; white-space: nowrap; overflow: hidden; }

.resize-handle { position: absolute; top: 0; right: -6px; width: 12px; height: 100%; cursor: col-resize; z-index: 1; display: flex; align-items: stretch; justify-content: center; }
.resize-handle::after { content: ''; width: 2px; border-radius: 2px; background: #d1d5db; transition: background 0.15s; }
.resize-handle:hover::after { background: #6b7280; }
.resizing .resize-handle::after { background: #111; }

table :deep(td) { padding: 13px 16px; color: #374151; border-bottom: 1px solid #f3f4f6; vertical-align: middle; overflow: hidden; }
table :deep(tbody tr:last-child td) { border-bottom: none; }
table :deep(tbody tr:hover) { background: #fafafa; }
</style>
