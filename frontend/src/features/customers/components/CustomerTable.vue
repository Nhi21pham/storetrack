<template>
  <ResizableTable :columns="columns" :initial-widths="CUSTOMER_INITIAL_COL_WIDTHS">
    <template #header-select>
      <input
        type="checkbox"
        class="row-check"
        :checked="allVisibleSelected"
        :indeterminate.prop="someVisibleSelected"
        @change="$emit('toggleSelectAll')"
        title="Select all on this page"
      />
    </template>

    <template v-for="col in columns" :key="col.key" #[`header-${col.key}`]="{ col: c }">
      <SortableHeader
        v-if="c.sortable"
        :label="c.label"
        :sort-info="sort.getSortInfo(c.key)"
        :rank="sort.sortCriteria.length > 1 && sort.getSortInfo(c.key) ? sort.sortRank(c.key) : null"
        @sort="(dir) => sort.toggleSort(c.key, dir)"
      />
      <template v-else>{{ c.label }}</template>
    </template>

    <tr v-for="customer in customers" :key="customer.id" :class="{ 'row-selected': isSelected(customer.id) }">
      <td>
        <input
          v-if="canManageRow(customer)"
          type="checkbox"
          class="row-check"
          :checked="isSelected(customer.id)"
          @change="$emit('toggleRow', customer.id)"
        />
      </td>
      <td><span class="id-badge">#{{ customer.id }}</span></td>
      <td><button class="name-link" @click="$emit('openDetail', customer)">{{ customer.name }}</button></td>
      <td>
        <span v-if="customer.tax_code" class="mono">{{ customer.tax_code }}</span>
        <span v-else class="empty-val">—</span>
      </td>
      <td>
        <span v-if="customer.email">{{ customer.email }}</span>
        <span v-else class="empty-val">—</span>
      </td>
      <td>
        <span v-if="customer.phone" class="mono">{{ customer.phone }}</span>
        <span v-else class="empty-val">—</span>
      </td>
      <td>
        <span v-if="customer.address" :title="customer.address" class="truncate">{{ customer.address }}</span>
        <span v-else class="empty-val">—</span>
      </td>
      <td>
        <div v-if="rowActionsEnabled && canManageRow(customer)" class="row-actions">
          <button class="action-btn" @click="$emit('edit', customer)" title="Edit">
            <Icon name="edit" :size="14" />
          </button>
          <button v-if="canDelete" class="action-btn danger" @click="$emit('delete', customer)" title="Delete">
            <Icon name="delete" :size="14" />
          </button>
        </div>
      </td>
    </tr>
  </ResizableTable>
</template>

<script setup>
import ResizableTable from '@/components/common/ResizableTable.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import Icon from '@/components/common/Icon.vue'
import { CUSTOMER_INITIAL_COL_WIDTHS } from '@/features/customers/constants'

defineProps({
  customers:           { type: Array,   required: true },
  columns:             { type: Array,   required: true },
  sort:                { type: Object,  required: true },
  isSelected:          { type: Function, required: true },
  canManageRow:        { type: Function, required: true },
  canDelete:           { type: Boolean, required: true },
  rowActionsEnabled:   { type: Boolean, required: true },
  allVisibleSelected:  { type: Boolean, required: true },
  someVisibleSelected: { type: Boolean, required: true },
})

defineEmits([
  'toggleSelectAll', 'toggleRow',
  'openDetail', 'edit', 'delete',
])
</script>

<style scoped>
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
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
