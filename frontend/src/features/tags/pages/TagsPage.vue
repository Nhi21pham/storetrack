<template>
  <PageContainer :maxWidth="1100">
    <PageHeader title="Tags" subtitle="Label customers, suppliers and products. A tag is a key (e.g. Color) with optional values (e.g. Red).">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Tag
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      title="No business found"
      description="You need to create or select a business before managing tags."
    />

    <EmptyState
      v-else-if="!currentStore"
      title="No store selected"
      description="Select a store to manage tags."
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" placeholder="Search by key or value..." />
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
      </div>

      <BulkStatusBar
        v-if="selectedIds.size > 0"
        :count="selectedIds.size"
        :busy="bulkBusy"
        :can-delete="canDeleteKey"
        :show-status="false"
        @clear="clearSelection"
        @delete="requestBulk('delete')"
      />

      <LoadingState v-if="loading">Loading tags...</LoadingState>

      <EmptyState
        v-else-if="tags.length === 0"
        title="No tags yet"
        description="Create your first tag to get started."
      />

      <div v-else class="table-wrap">
        <ResizableTable :key="tableKey" :columns="columnVisibility.visibleColumns.value" :initial-widths="visibleWidths">
          <template v-for="col in columnVisibility.visibleColumns.value" :key="col.key" #[`header-${col.key}`]="{ col: c }">
            <SelectCheckbox
              v-if="c.key === 'select'"
              :checked="allVisibleSelected"
              :indeterminate="someVisibleSelected"
              title="Select all"
              @change="toggleSelectAll"
            />
            <SortableHeader
              v-else-if="c.sortable"
              :label="c.label"
              :sort-info="sort.getSortInfo(c.key)"
              :rank="sort.sortCriteria.length > 1 && sort.getSortInfo(c.key) ? sort.sortRank(c.key) : null"
              @sort="(dir) => sort.toggleSort(c.key, dir)"
            />
            <template v-else>{{ c.label }}</template>
          </template>

          <tr v-if="filteredTags.length === 0">
            <td :colspan="columnVisibility.visibleColumns.value.length" class="empty-row">
              No tags match the current filters.
            </td>
          </tr>
          <tr v-for="tag in paginatedTags" :key="tag.id">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(tag.id)" @change="toggleRow(tag.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('id')" class="id-col">{{ tag.id }}</td>
            <td v-if="columnVisibility.isVisible('name')">
              <button class="name-link" @click="detailTag = tag">{{ tag.name }}</button>
            </td>
            <td v-if="columnVisibility.isVisible('values')">
              <div class="values-cell">
                <TagChip v-for="val in tag.values" :key="val.id" :tag-name="tag.name" :value="val.value" />
                <span v-if="tag.values.length === 0" class="keyonly-hint">Key-only</span>
              </div>
            </td>
            <td v-if="columnVisibility.isVisible('description')">
              <span v-if="tag.description" class="truncate" :title="tag.description">{{ tag.description }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('created_at')">{{ formatDateTime(tag.created_at) }}</td>
            <td class="actions-col">
              <button class="action-btn" @click="openEdit(tag)" title="Edit tag">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDeleteKey" class="action-btn danger" @click="confirmDeleteKey(tag)" title="Delete tag">
                <Icon name="delete" :size="14" />
              </button>
            </td>
          </tr>
        </ResizableTable>
        <Pagination
          v-if="total > 0"
          :current-page="currentPage"
          :total-pages="totalPages"
          :total="total"
          :per-page="perPage"
          @update:current-page="currentPage = $event"
          @update:per-page="setPerPage"
        />
      </div>
    </template>

    <TagFormModal
      v-if="showForm"
      :tag="editingTag"
      :store-id="currentStore?.id"
      @close="closeForm"
      @saved="onSaved"
    />

    <TagDetailModal
      v-if="detailTag"
      :tag="detailTag"
      @close="detailTag = null"
      @edit="onDetailEdit"
    />

    <TagDeleteDialog
      v-if="deleteKeyTarget"
      :title="`Delete tag '${deleteKeyTarget.name}'?`"
      :message="`This permanently deletes the tag and all ${deleteKeyTarget.values.length} of its value(s). This cannot be undone.`"
      confirm-text="Delete tag"
      :store-id="currentStore?.id"
      :tag-id="deleteKeyTarget.id"
      :tag-value-id="null"
      :deleting="deleting"
      @confirm="performDeleteKey"
      @cancel="deleteKeyTarget = null"
    />

    <ConfirmDialog
      v-if="pendingAction"
      :title="confirmConfig.title"
      :message="confirmConfig.message"
      :confirm-text="confirmConfig.confirmText"
      cancel-text="Cancel"
      :type="confirmConfig.type"
      @confirm="confirmBulk"
      @cancel="cancelBulk"
    />
  </PageContainer>
</template>

<script setup>
import { ref, computed, watch, inject, onMounted } from 'vue'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import Icon from '@/components/common/Icon.vue'
import Pagination from '@/components/common/Pagination.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import TagChip from '@/components/common/TagChip.vue'
import SelectCheckbox from '@/components/common/SelectCheckbox.vue'
import BulkStatusBar from '@/components/common/BulkStatusBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import TagFormModal from '@/features/tags/components/TagFormModal.vue'
import TagDetailModal from '@/features/tags/components/TagDetailModal.vue'
import TagDeleteDialog from '@/features/tags/components/TagDeleteDialog.vue'
import { useClientPagination } from '@/composables/useClientPagination'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { useRowSelection } from '@/composables/useRowSelection'
import { useBulkActions } from '@/composables/useBulkActions'
import { fetchTags, deleteTag } from '@/features/tags/services/tagService'
import { TAG_COLUMNS, TAG_INITIAL_COL_WIDTHS } from '@/features/tags/constants'
import { normalizeText } from '@/utils/textNormalizer'
import { formatDateTime } from '@/utils/datetime'

const columnVisibility = useColumnVisibility({
  storageKey: 'tags',
  columns: TAG_COLUMNS,
  lockedKeys: ['select', 'actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(TAG_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')

const tags = ref([])
const loading = ref(false)
const searchQuery = ref('')

const showForm = ref(false)
const editingTag = ref(null)
const detailTag = ref(null)
const deleteKeyTarget = ref(null)
const deleting = ref(false)

const role = computed(() => String(currentStore?.value?.my_role || '').toLowerCase())
const canDeleteKey = computed(() => ['owner', 'accountant'].includes(role.value))

const filteredTags = computed(() => {
  const needle = normalizeText(searchQuery.value)
  if (!needle) return tags.value
  return tags.value.filter(t =>
    normalizeText(t.name).includes(needle) ||
    normalizeText(t.description || '').includes(needle) ||
    (t.values || []).some(v => normalizeText(v.value).includes(needle))
  )
})

const sort = useSortCriteria()
const sortedTags = computed(() =>
  sort.sortItems(filteredTags.value, (tag, key) => {
    if (key === 'id')   return Number(tag.id) || 0
    if (key === 'name') return normalizeText(tag.name)
    const v = tag[key]
    return typeof v === 'string' ? normalizeText(v) : (v ?? '')
  })
)

const {
  currentPage,
  perPage,
  total,
  totalPages,
  paginated: paginatedTags,
  setPerPage,
  resetPage,
} = useClientPagination(sortedTags)

const selectableIds = computed(() => sortedTags.value.map(t => String(t.id)))
const {
  selectedIds, isSelected, toggleRow, toggleSelectAll, clearSelection,
  allVisibleSelected, someVisibleSelected,
} = useRowSelection({ eligibleIds: selectableIds, scopeToEligible: true })

const { bulkBusy, pendingAction, request: requestBulk, confirm: confirmBulk, cancel: cancelBulk, confirmConfig } = useBulkActions({
  selectedIds, clearSelection, reload: () => load(), noun: 'tag',
  remove: (id) => deleteTag({ id }),
  deleteMessage: 'This permanently deletes the selected tags (and all their values) and detaches them from any products using them. This cannot be undone.',
})

watch([searchQuery, () => sort.sortCriteria.value], resetPage, { deep: true })

const load = async () => {
  if (!currentStore?.value?.id) {
    tags.value = []
    return
  }
  loading.value = true
  try {
    tags.value = await fetchTags({ storeId: currentStore.value.id })
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => currentStore?.value?.id, () => { clearSelection(); load() })

const openCreate = () => {
  editingTag.value = null
  showForm.value = true
}

const openEdit = (tag) => {
  editingTag.value = tag
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingTag.value = null
}

const onSaved = async () => {
  closeForm()
  await load()
}

const onDetailEdit = (tag) => {
  detailTag.value = null
  openEdit(tag)
}

const confirmDeleteKey = (tag) => { deleteKeyTarget.value = tag }

const performDeleteKey = async () => {
  const tag = deleteKeyTarget.value
  deleting.value = true
  try {
    await deleteTag({ id: tag.id })
    deleteKeyTarget.value = null
    await load()
  } catch (err) {
    alert(err.message)
  } finally {
    deleting.value = false
  }
}

</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; }

.empty-row { padding: 24px 16px; text-align: center; color: #9ca3af; font-size: 13px; }

.btn-create { display: flex; align-items: center; gap: 6px; padding: 9px 16px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-create:hover { background: #333; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }

.id-col { color: #6b7280; font-variant-numeric: tabular-nums; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 700; color: #111; cursor: pointer; text-align: left; }
.name-link:hover { color: #2563eb; text-decoration: underline; }

.values-cell { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.keyonly-hint { font-size: 12px; color: #9ca3af; font-style: italic; }

.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.empty-val { color: #d1d5db; }

.actions-col { text-align: right; white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; margin-left: 4px; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
