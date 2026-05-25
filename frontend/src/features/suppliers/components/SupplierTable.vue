<template>
  <div class="table-wrapper" :class="{ resizing: isResizing }">
    <table>
      <colgroup>
        <col v-for="(w, i) in colWidths" :key="i" :style="{ width: w + 'px' }" />
      </colgroup>
      <thead>
        <tr>
          <th v-for="(col, i) in columns" :key="col.key">
            <template v-if="col.key === 'select'">
              <input
                type="checkbox"
                class="row-check"
                :checked="allVisibleSelected"
                :indeterminate.prop="someVisibleSelected"
                @change="$emit('toggleSelectAll')"
                title="Select all on this page"
              />
            </template>
            <SortableHeader
              v-else-if="col.sortable"
              :label="col.label"
              :sort-info="sort.getSortInfo(col.key)"
              :rank="sort.sortCriteria.length > 1 && sort.getSortInfo(col.key) ? sort.sortRank(col.key) : null"
              @sort="(dir) => sort.toggleSort(col.key, dir)"
            />
            <template v-else>{{ col.label }}</template>
            <div v-if="col.key !== 'select'" class="resize-handle" @mousedown.prevent="$emit('startResize', $event, i)"></div>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="supplier in suppliers" :key="supplier.id" :class="{ 'row-selected': isSelected(supplier.id) }">
          <td>
            <input
              v-if="canManageRow(supplier)"
              type="checkbox"
              class="row-check"
              :checked="isSelected(supplier.id)"
              @change="$emit('toggleRow', supplier.id)"
            />
          </td>
          <td><span class="id-badge">#{{ supplier.id }}</span></td>
          <td><button class="name-link" @click="$emit('openDetail', supplier)">{{ supplier.name }}</button></td>
          <td>
            <span v-if="supplier.tax_code" class="mono">{{ supplier.tax_code }}</span>
            <span v-else class="empty-val">—</span>
          </td>
          <td>
            <span v-if="supplier.email">{{ supplier.email }}</span>
            <span v-else class="empty-val">—</span>
          </td>
          <td>
            <span v-if="supplier.phone" class="mono">{{ supplier.phone }}</span>
            <span v-else class="empty-val">—</span>
          </td>
          <td>
            <span v-if="supplier.address" :title="supplier.address" class="truncate">{{ supplier.address }}</span>
            <span v-else class="empty-val">—</span>
          </td>
          <td>
            <div v-if="rowActionsEnabled && canManageRow(supplier)" class="row-actions">
              <button class="action-btn" @click="$emit('edit', supplier)" title="Edit">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDelete" class="action-btn danger" @click="$emit('delete', supplier)" title="Delete">
                <Icon name="delete" :size="14" />
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import SortableHeader from '@/components/common/SortableHeader.vue'
import Icon from '@/components/common/Icon.vue'

defineProps({
  suppliers:           { type: Array,   required: true },
  columns:             { type: Array,   required: true },
  colWidths:           { type: Array,   required: true },
  isResizing:          { type: Boolean, required: true },
  sort:                { type: Object,  required: true },
  isSelected:          { type: Function, required: true },
  canManageRow:        { type: Function, required: true },
  canDelete:           { type: Boolean, required: true },
  rowActionsEnabled:   { type: Boolean, required: true },
  allVisibleSelected:  { type: Boolean, required: true },
  someVisibleSelected: { type: Boolean, required: true },
})

defineEmits([
  'startResize', 'toggleSelectAll', 'toggleRow',
  'openDetail', 'edit', 'delete',
])
</script>

<style scoped>
.table-wrapper { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow-x: auto; }
.table-wrapper.resizing { cursor: col-resize; user-select: none; }

table { width: 100%; border-collapse: collapse; font-size: 13.5px; table-layout: fixed; }

thead { background: #f9fafb; }
th { position: relative; padding: 11px 16px; text-align: left; font-size: 11.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e5e7eb; white-space: nowrap; overflow: hidden; }

td { padding: 13px 16px; color: #374151; border-bottom: 1px solid #f3f4f6; vertical-align: middle; overflow: hidden; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #fafafa; }

.resize-handle { position: absolute; top: 0; right: -6px; width: 12px; height: 100%; cursor: col-resize; z-index: 1; display: flex; align-items: stretch; justify-content: center; }
.resize-handle::after { content: ''; width: 2px; border-radius: 2px; background: #d1d5db; transition: background 0.15s; }
.resize-handle:hover::after { background: #6b7280; }
.resizing .resize-handle::after { background: #111; }

.id-badge { font-size: 12px; font-weight: 600; color: #9ca3af; font-family: monospace; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; }
.name-link:hover { color: #2563eb; text-decoration: underline; }
.mono { font-family: monospace; font-size: 13px; }
.empty-val { color: #d1d5db; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.row-check { width: 15px; height: 15px; cursor: pointer; accent-color: #111; }
tbody tr.row-selected { background: #f0f7ff; }
tbody tr.row-selected:hover { background: #e6f0fb; }

.row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.action-btn { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
