<template>
  <PageContainer :maxWidth="1100">
    <PageHeader :title="$t('tags.title')" :subtitle="$t('tags.subtitle')">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          {{ $t('tags.newTag') }}
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      :title="$t('shared.noBusinessTitle')"
      :description="$t('tags.noBusinessDesc')"
    />

    <EmptyState
      v-else-if="!currentStore"
      :title="$t('shared.noStoreTitle')"
      :description="$t('tags.noStoreDesc')"
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" :placeholder="$t('tags.searchPlaceholder')" />
        <ClearFiltersButton v-if="hasActiveFilters" @click="clearFilters" />
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
        <HistoryButton @click="showHistory = true" />
        <ImportButton @click="showImport = true" />
        <ExportButton :exporting="exporting" :disabled="sortedTags.length === 0" @click="runExport" />
      </div>

      <DateRangeFilters
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        v-model:date-field="dateField"
      />

      <BulkStatusBar
        v-if="selectedIds.size > 0"
        :count="selectedIds.size"
        :busy="bulkBusy"
        :can-delete="canDeleteKey"
        :show-status="false"
        show-export
        :exporting="exporting"
        @clear="clearSelection"
        @export="runExport"
        @delete="requestBulk('delete')"
      />

      <LoadingState v-if="loading">{{ $t('tags.loadingTags') }}</LoadingState>

      <EmptyState
        v-else-if="tags.length === 0"
        :title="$t('tags.noTagsTitle')"
        :description="$t('tags.noTagsDesc')"
      />

      <div v-else class="table-wrap">
        <ResizableTable :key="tableKey" :columns="columnVisibility.visibleColumns.value" :initial-widths="visibleWidths" sticky-header>
          <template v-for="col in columnVisibility.visibleColumns.value" :key="col.key" #[`header-${col.key}`]="{ col: c }">
            <SelectCheckbox
              v-if="c.key === 'select'"
              :checked="allVisibleSelected"
              :indeterminate="someVisibleSelected"
              :title="$t('shared.selectAll')"
              @change="toggleSelectAll"
            />
            <SortableHeader
              v-else-if="c.sortable"
              :label="$t(c.labelKey)"
              :sort-info="sort.getSortInfo(c.key)"
              :rank="sort.sortCriteria.length > 1 && sort.getSortInfo(c.key) ? sort.sortRank(c.key) : null"
              @sort="(dir) => sort.toggleSort(c.key, dir)"
            />
            <template v-else>{{ c.labelKey ? $t(c.labelKey) : '' }}</template>
          </template>

          <tr v-if="filteredTags.length === 0">
            <td :colspan="columnVisibility.visibleColumns.value.length" class="empty-row">
              {{ $t('shared.noResults') }}
            </td>
          </tr>
          <tr v-for="(tag, idx) in paginatedTags" :key="tag.id">
            <td v-if="columnVisibility.isVisible('select')">
              <SelectCheckbox :checked="isSelected(tag.id)" @change="toggleRow(tag.id)" />
            </td>
            <td v-if="columnVisibility.isVisible('stt')" class="stt-col">{{ (currentPage - 1) * perPage + idx + 1 }}</td>
            <td v-if="columnVisibility.isVisible('name')">
              <button class="name-link" v-tooltip="tag.name" @click="detailTag = tag">{{ tag.name }}</button>
            </td>
            <td v-if="columnVisibility.isVisible('values')">
              <div class="values-cell">
                <TagChip v-for="val in tag.values" :key="val.id" :tag-name="tag.name" :value="val.value" />
                <span v-if="tag.values.length === 0" class="keyonly-hint">{{ $t('tags.keyOnly') }}</span>
              </div>
            </td>
            <td v-if="columnVisibility.isVisible('description')">
              <span v-if="tag.description" class="truncate" :title="tag.description">{{ tag.description }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('created_at')" class="date-col">{{ formatDateTime(tag.created_at) }}</td>
            <td v-if="columnVisibility.isVisible('updated_at')" class="date-col">{{ formatDateTime(tag.updated_at) }}</td>
            <td class="actions-col">
              <button class="action-btn" @click="openEdit(tag)" :title="$t('tags.editTagTitle')">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDeleteKey" class="action-btn danger" @click="confirmDeleteKey(tag)" :title="$t('tags.deleteTagTitle')">
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
      :title="$t('tags.deleteKeyTitle', { name: deleteKeyTarget.name })"
      :message="$t('tags.deleteKeyMessage', { count: deleteKeyTarget.values.length })"
      :confirm-text="$t('tags.deleteKeyConfirm')"
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
      :cancel-text="$t('common.cancel')"
      :type="confirmConfig.type"
      @confirm="confirmBulk"
      @cancel="cancelBulk"
    />

    <ImportModal
      v-if="showImport && currentStore"
      :title="$t('tags.importTitle')"
      template-filename="tags-import-template.xlsx"
      :required-headers="['Key']"
      :optional-headers="['Value', 'Description']"
      :instructions="tagImportInstructions"
      :download-template="() => downloadTagsImportTemplate({ storeId: currentStore.id })"
      :preview="(file) => previewTagsImport({ storeId: currentStore.id, file })"
      :revalidate="(rows) => revalidateTagsImport({ storeId: currentStore.id, rows })"
      :start="(rows, originalFilename) => startTagsImport({ storeId: currentStore.id, rows, originalFilename })"
      :status="(id) => fetchImportStatus({ importId: id })"
      @close="showImport = false"
      @imported="onImported"
    />

    <HistoryModal
      v-if="showHistory && currentStore"
      :title="$t('tags.historyTitle')"
      :tabs="historyTabs"
      @close="showHistory = false"
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
import ExportButton from '@/components/common/ExportButton.vue'
import ImportButton from '@/components/common/ImportButton.vue'
import HistoryButton from '@/components/common/HistoryButton.vue'
import ImportModal from '@/components/common/ImportModal.vue'
import HistoryModal from '@/components/common/HistoryModal.vue'
import ImportHistoryPanel from '@/components/common/ImportHistoryPanel.vue'
import ExportHistoryPanel from '@/components/common/ExportHistoryPanel.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import ClearFiltersButton from '@/components/common/ClearFiltersButton.vue'
import DateRangeFilters from '@/components/common/DateRangeFilters.vue'
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
import { useExport } from '@/composables/useExport'
import { useDateRangeFilter } from '@/composables/useDateRangeFilter'
import {
  fetchTags, deleteTag, startTagExport,
  downloadTagsImportTemplate, previewTagsImport, revalidateTagsImport, startTagsImport,
} from '@/features/tags/services/tagService'
import { fetchImportStatus } from '@/features/imports/services/importService'
import { TAG_COLUMNS, TAG_INITIAL_COL_WIDTHS } from '@/features/tags/constants'
import { normalizeText } from '@/utils/textNormalizer'
import { formatDateTime } from '@/utils/datetime'
import { translateError } from '@/utils/translateError'
import { t } from '@/i18n'

const columnVisibility = useColumnVisibility({
  storageKey: 'tags',
  columns: TAG_COLUMNS,
  lockedKeys: ['select', 'stt', 'actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(TAG_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')
const showToast = inject('showToast')

const tags = ref([])
const loading = ref(false)
const searchQuery = ref('')
const { startDate, endDate, dateField, isActive: dateRangeActive, inDateRange, clear: clearDateRange } = useDateRangeFilter()

const hasActiveFilters = computed(() => dateRangeActive.value)
const clearFilters = () => { clearDateRange() }

const showForm = ref(false)
const editingTag = ref(null)
const detailTag = ref(null)
const deleteKeyTarget = ref(null)
const deleting = ref(false)

const showImport = ref(false)
const showHistory = ref(false)

const historyTabs = computed(() => [
  { key: 'imports', label: t('shared.imports'), component: ImportHistoryPanel, props: { scope: 'store', scopeId: currentStore.value?.id, type: 'tags' } },
  { key: 'exports', label: t('shared.exports'), component: ExportHistoryPanel, props: { scope: 'store', scopeId: currentStore.value?.id, types: ['tags'] } },
])

// Explains the Value column grammar in the import dialog (kept in sync with
// TagImporter's parser on the backend).
const tagImportInstructions = computed(() => [
  t('tags.import.req'),
  { text: t('tags.import.commas'), example: 'Red, Blue, Green  →  Red · Blue · Green' },
  { text: t('tags.import.prefix'), example: 'Color: Red  →  Red' },
  { text: t('tags.import.quotes'), example: '"6"" hose, blue"  →  6" hose, blue' },
  { text: t('tags.import.afterQuote'), example: '"Red" blue  →  Red' },
  t('tags.import.existingKey'),
])

const role = computed(() => String(currentStore?.value?.my_role || '').toLowerCase())
const canDeleteKey = computed(() => ['owner', 'accountant'].includes(role.value))

const filteredTags = computed(() => {
  const needle = normalizeText(searchQuery.value)
  return tags.value.filter(t => {
    if (!inDateRange(t)) return false
    if (!needle) return true
    return (
      normalizeText(t.name).includes(needle) ||
      normalizeText(t.description || '').includes(needle) ||
      (t.values || []).some(v => normalizeText(v.value).includes(needle))
    )
  })
})

// Most recently updated first by default, so the No. column reads newest-to-oldest.
const orderedTags = computed(() =>
  [...filteredTags.value].sort(
    (a, b) => new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0),
  )
)

const sort = useSortCriteria()
const sortedTags = computed(() =>
  sort.sortItems(orderedTags.value, (tag, key) => {
    if (key === 'name') return normalizeText(tag.name)
    if (key === 'created_at' || key === 'updated_at') return new Date(tag[key] || 0).getTime()
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

const { exporting, run: runExport } = useExport({
  start: () => {
    const params = { search: searchQuery.value.trim() || undefined }
    if (selectedIds.value.size > 0) {
      params.ids = Array.from(selectedIds.value)
    }
    params.columns = columnVisibility.togglableColumns
      .filter((col) => columnVisibility.isVisible(col.key))
      .map((col) => col.key)
    return startTagExport({ storeId: currentStore.value.id, params })
  },
  defaultFilename: (id) => `tags-${id}.xlsx`,
  onSuccess: () => showToast(t('tags.exportReady'), 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

const { bulkBusy, pendingAction, request: requestBulk, confirm: confirmBulk, cancel: cancelBulk, confirmConfig } = useBulkActions({
  selectedIds, clearSelection, reload: () => load(), nounKey: 'tag',
  remove: (id) => deleteTag({ id }),
  deleteMessage: t('tags.bulkDeleteMessage'),
})

watch([searchQuery, startDate, endDate, dateField, () => sort.sortCriteria.value], resetPage, { deep: true })

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

const onImported = async () => {
  await load()
}

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
    alert(translateError(err))
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

.stt-col { color: #6b7280; font-variant-numeric: tabular-nums; }
.date-col { color: #6b7280; white-space: nowrap; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 700; color: #111; cursor: pointer; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
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
