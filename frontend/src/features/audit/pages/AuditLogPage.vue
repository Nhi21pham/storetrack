<template>
  <PageContainer :maxWidth="860">
    <PageHeader title="Audit Log">
      <template #subtitle>
        Activity history for
        <strong>{{ viewMode === 'store' ? (currentStore?.name ?? '—') : (currentBusiness?.name ?? '—') }}</strong>
      </template>
      <template v-if="isBusinessOwner" #actions>
        <div class="view-toggle">
          <button :class="{ active: viewMode === 'store' }" @click="switchMode('store')">Store</button>
          <button :class="{ active: viewMode === 'business' }" @click="switchMode('business')">Business</button>
        </div>
      </template>
    </PageHeader>

    <EmptyState
      v-if="viewMode === 'store' && !currentStore"
      title="No store selected"
      description="Select a store from the top bar to view its activity."
    >
      <template #icon>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
        </svg>
      </template>
    </EmptyState>

    <EmptyState
      v-else-if="viewMode === 'business' && !currentBusiness"
      title="No business selected"
      description="Select a business to view its activity."
    >
      <template #icon>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        </svg>
      </template>
    </EmptyState>

    <template v-else>
      <InactiveBanner v-if="viewMode === 'store' && currentStore && !currentStore.is_active">
        This store is deactivated. Showing historical activity only.
      </InactiveBanner>

      <SearchBar v-model="searchQuery" placeholder="Search activity..." />

      <AuditFilterBar
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        v-model:objectFilter="objectFilter"
        v-model:actionFilter="actionFilter"
        :hasActiveFilter="hasActiveFilter"
        :exporting="exporting"
        :total="total"
        @apply="applyFilter"
        @clear="clearFilter"
        @export="run"
      />

      <LoadingState v-if="loading">Loading activity...</LoadingState>

      <EmptyState
        v-else-if="entries.length === 0 && !searchQuery.trim()"
        title="No activity yet"
        description="Actions performed will appear here."
      >
        <template #icon>
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
        </template>
      </EmptyState>

      <EmptyState
        v-else-if="entries.length === 0"
        :title="`No results matching &quot;${searchQuery}&quot;`"
        description="Try a different keyword."
      />

      <AuditLogList
        v-else
        :logs="entries"
        :fetching="fetching"
        :showStoreColumn="viewMode === 'business'"
        :currentPage="currentPage"
        :totalPages="lastPage"
        :total="total"
        :perPage="perPage"
        @page="loadPage"
        @perPage="changePerPage"
      />
    </template>
  </PageContainer>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import InactiveBanner from '@/components/common/InactiveBanner.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import AuditFilterBar from '@/features/audit/components/AuditFilterBar.vue'
import AuditLogList from '@/features/audit/components/AuditLogList.vue'
import { useAuditLogs } from '@/features/audit/composables/useAuditLogs'
import { useExport } from '@/composables/useExport'
import {
  startStoreAuditExport,
  startBusinessAuditExport,
} from '@/features/audit/services/auditService'

const currentStore    = inject('currentStore')
const currentBusiness = inject('currentBusiness')
const showToast       = inject('showToast')

const viewMode = ref('store')
const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')

const {
  logs: entries, loading, fetching,
  currentPage, lastPage, total, perPage,
  startDate, endDate, searchQuery, objectFilter, actionFilter,
  hasActiveFilter,
  fetchLogs, resetAndFetch, applyFilter, clearFilter, loadPage, changePerPage,
  exportParams,
} = useAuditLogs({
  currentStore,
  currentBusiness,
  viewMode,
  onError: (msg) => showToast(msg, 'error'),
})

const { exporting, run } = useExport({
  start: () => {
    const params = exportParams()
    return viewMode.value === 'store'
      ? startStoreAuditExport(currentStore.value.id, params)
      : startBusinessAuditExport(currentBusiness.value.id, params)
  },
  defaultFilename: (id) => `audit-log-${id}.xlsx`,
  onSuccess: () => showToast('Audit log export ready.', 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

const switchMode = (mode) => {
  if (viewMode.value === mode) return
  viewMode.value = mode
  entries.value = []
  currentPage.value = 1
  fetchLogs(1)
}

// Owners landing here with no store (business-level selection or no stores yet)
// should jump straight to the business view instead of being stranded on the
// "No store selected" empty state.
let initialModeResolved = false
const applyInitialMode = () => {
  if (initialModeResolved) return
  if (!currentBusiness.value) return
  initialModeResolved = true
  if (!currentStore.value && currentBusiness.value.role === 'owner') {
    viewMode.value = 'business'
    resetAndFetch()
  }
}

watch(() => currentStore.value?.id, (id) => {
  applyInitialMode()
  if (viewMode.value === 'store' && id) resetAndFetch()
}, { immediate: true })

watch(() => currentBusiness.value?.id, (id) => {
  applyInitialMode()
  if (!isBusinessOwner.value && viewMode.value === 'business') {
    viewMode.value = 'store'
  }
  if (viewMode.value === 'business' && id) resetAndFetch()
})
</script>

<style scoped>
.view-toggle { display: flex; background: #f3f4f6; border-radius: 10px; padding: 3px; gap: 2px; }
.view-toggle button { padding: 7px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; background: none; color: #6b7280; transition: all 0.15s; }
.view-toggle button.active { background: #fff; color: #111; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
</style>
