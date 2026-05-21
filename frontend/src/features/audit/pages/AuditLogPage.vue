<template>
  <div class="audit-log-page">
    <div class="page-header">
      <div>
        <h1>Audit Log</h1>
        <p class="subtitle">
          Activity history for
          <strong>{{ viewMode === 'store' ? (currentStore?.name ?? '—') : (currentBusiness?.name ?? '—') }}</strong>
        </p>
      </div>
      <div v-if="isBusinessOwner" class="view-toggle">
        <button :class="{ active: viewMode === 'store' }" @click="switchMode('store')">Store</button>
        <button :class="{ active: viewMode === 'business' }" @click="switchMode('business')">Business</button>
      </div>
    </div>

    <div v-if="viewMode === 'store' && !currentStore" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
        </svg>
      </div>
      <h3>No store selected</h3>
      <p>Select a store from the top bar to view its activity.</p>
    </div>

    <div v-else-if="viewMode === 'business' && !currentBusiness" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        </svg>
      </div>
      <h3>No business selected</h3>
      <p>Select a business to view its activity.</p>
    </div>

    <template v-else>
      <div v-if="viewMode === 'store' && currentStore && !currentStore.is_active" class="inactive-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        This store is deactivated. Showing historical activity only.
      </div>

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

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>Loading activity...</span>
      </div>

      <div v-else-if="entries.length === 0 && !searchQuery.trim()" class="empty-state">
        <div class="empty-icon">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
        </div>
        <h3>No activity yet</h3>
        <p>Actions performed will appear here.</p>
      </div>

      <div v-else-if="entries.length === 0" class="empty-state">
        <h3>No results matching "{{ searchQuery }}"</h3>
        <p>Try a different keyword.</p>
      </div>

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
  </div>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue'
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
.audit-log-page { padding: 32px; max-width: 860px; margin: 0 auto; }

.inactive-banner { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; font-size: 13px; color: #92400e; margin-bottom: 16px; }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; }
.page-header h1 { font-size: 22px; font-weight: 700; color: #111; }
.subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }

.view-toggle { display: flex; background: #f3f4f6; border-radius: 10px; padding: 3px; gap: 2px; }
.view-toggle button { padding: 7px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; background: none; color: #6b7280; transition: all 0.15s; }
.view-toggle button.active { background: #fff; color: #111; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px 0; color: #6b7280; font-size: 14px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { width: 72px; height: 72px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #9ca3af; }
.empty-state h3 { font-size: 16px; font-weight: 600; color: #111; margin-bottom: 6px; }
.empty-state p { font-size: 14px; color: #6b7280; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
