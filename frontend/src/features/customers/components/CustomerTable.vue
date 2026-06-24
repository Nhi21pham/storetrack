<template>
  <ResizableTable :columns="columns" :initial-widths="initialWidths" sticky-header>
    <template #header-select>
      <SelectCheckbox
        :checked="allVisibleSelected"
        :indeterminate="someVisibleSelected"
        :title="$t('shared.selectAll')"
        @change="$emit('toggleSelectAll')"
      />
    </template>

    <template v-for="col in headerColumns" :key="col.key" #[`header-${col.key}`]="{ col: c }">
      <SortableHeader
        v-if="c.sortable"
        :label="$t(c.labelKey)"
        :sort-info="sort.getSortInfo(c.key)"
        :rank="sort.sortCriteria.length > 1 && sort.getSortInfo(c.key) ? sort.sortRank(c.key) : null"
        @sort="(dir) => sort.toggleSort(c.key, dir)"
      />
      <template v-else>{{ c.labelKey ? $t(c.labelKey) : '' }}</template>
    </template>

    <tr v-for="(customer, idx) in customers" :key="customer.id" :class="{ 'row-selected': isSelected(customer.id) }">
      <td v-if="isVisible('select')">
        <SelectCheckbox
          v-if="canManageRow(customer)"
          :checked="isSelected(customer.id)"
          @change="$emit('toggleRow', customer.id)"
        />
      </td>
      <td v-if="isVisible('stt')" class="stt-col">{{ rowOffset + idx + 1 }}</td>
      <td v-if="isVisible('name')"><button class="name-link" @click="$emit('openDetail', customer)">{{ customer.name }}</button></td>
      <td v-if="isVisible('tax_code')">
        <span v-if="customer.tax_code" class="mono">{{ customer.tax_code }}</span>
        <span v-else class="empty-val">—</span>
      </td>
      <td v-if="isVisible('email')">
        <span v-if="customer.email">{{ customer.email }}</span>
        <span v-else class="empty-val">—</span>
      </td>
      <td v-if="isVisible('phone')">
        <span v-if="customer.phone" class="mono">{{ customer.phone }}</span>
        <span v-else class="empty-val">—</span>
      </td>
      <td v-if="isVisible('address')">
        <span v-if="customer.address" :title="customer.address" class="truncate">{{ customer.address }}</span>
        <span v-else class="empty-val">—</span>
      </td>
      <td v-if="isVisible('outstanding')">
        <span v-if="Number(customer.outstanding) > 0" class="owed">{{ formatMoney(customer.outstanding) }}</span>
        <span v-else class="empty-val">—</span>
      </td>
      <td v-if="isVisible('created_at')" class="date-col">{{ formatDateTime(customer.created_at) }}</td>
      <td v-if="isVisible('updated_at')" class="date-col">{{ formatDateTime(customer.updated_at) }}</td>
      <td class="actions-col">
        <div v-if="rowActionsEnabled && canManageRow(customer)" class="row-actions">
          <button class="action-btn" @click="$emit('edit', customer)" :title="$t('common.edit')">
            <Icon name="edit" :size="14" />
          </button>
          <button v-if="canDelete" class="action-btn danger" @click="$emit('delete', customer)" :title="$t('common.delete')">
            <Icon name="delete" :size="14" />
          </button>
        </div>
      </td>
    </tr>
  </ResizableTable>
</template>

<script setup>
import { computed } from 'vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import Icon from '@/components/common/Icon.vue'
import { formatMoney } from '@/features/invoices/constants'
import { formatDateTime } from '@/utils/datetime'

const props = defineProps({
  customers:           { type: Array,   required: true },
  columns:             { type: Array,   required: true },
  initialWidths:       { type: Array,   required: true },
  rowOffset:           { type: Number,  default: 0 },
  isVisible:           { type: Function, required: true },
  sort:                { type: Object,  required: true },
  isSelected:          { type: Function, required: true },
  canManageRow:        { type: Function, required: true },
  canDelete:           { type: Boolean, required: true },
  rowActionsEnabled:   { type: Boolean, required: true },
  allVisibleSelected:  { type: Boolean, required: true },
  someVisibleSelected: { type: Boolean, required: true },
})

const headerColumns = computed(() => props.columns.filter((c) => c.key !== 'select'))

defineEmits([
  'toggleSelectAll', 'toggleRow',
  'openDetail', 'edit', 'delete',
])
</script>

<style scoped>
.stt-col { color: #6b7280; font-variant-numeric: tabular-nums; }
.date-col { color: #6b7280; white-space: nowrap; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; }
.name-link:hover { color: #2563eb; text-decoration: underline; }
.mono { font-family: monospace; font-size: 13px; }
.empty-val { color: #d1d5db; }
.owed { font-weight: 600; color: #b45309; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

tbody tr.row-selected { background: #f0f7ff; }
tbody tr.row-selected:hover { background: #e6f0fb; }
tbody tr.row-selected td.actions-col { background: #f0f7ff; }
tbody tr.row-selected:hover td.actions-col { background: #e6f0fb; }

.row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
