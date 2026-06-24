<template>
  <PageContainer :maxWidth="860">
    <PageHeader :title="$t('audit.title')">
      <template #subtitle>
        {{ $t('audit.subtitleBefore') }}
        <strong>{{ viewMode === 'store' ? (currentStore?.name ?? '—') : (currentBusiness?.name ?? '—') }}</strong>
      </template>
    </PageHeader>

    <EmptyState
      v-if="viewMode === 'store' && !currentStore"
      :title="$t('audit.noStoreTitle')"
      :description="$t('audit.noStoreDesc')"
    >
      <template #icon>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
        </svg>
      </template>
    </EmptyState>

    <EmptyState
      v-else-if="viewMode === 'business' && !currentBusiness"
      :title="$t('audit.noBusinessTitle')"
      :description="$t('audit.noBusinessDesc')"
    >
      <template #icon>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        </svg>
      </template>
    </EmptyState>

    <template v-else>
      <InactiveBanner v-if="viewMode === 'store' && currentStore && !currentStore.is_active">
        {{ $t('audit.inactiveStoreBanner') }}
      </InactiveBanner>

      <SearchBar v-model="searchQuery" :placeholder="$t('audit.searchPlaceholder')" />

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

      <LoadingState v-if="loading">{{ $t('audit.loadingActivity') }}</LoadingState>

      <EmptyState
        v-else-if="entries.length === 0 && !searchQuery.trim()"
        :title="$t('audit.noActivityTitle')"
        :description="$t('audit.noActivityDesc')"
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
        :title="$t('audit.noResultsTitle', { query: searchQuery })"
        :description="$t('audit.noResultsDesc')"
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
import { computed, watch, inject } from 'vue'
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
import { t } from '@/i18n'

const currentStore    = inject('currentStore')
const currentBusiness = inject('currentBusiness')
const showToast       = inject('showToast')

const isBusinessOwner = computed(() => currentBusiness.value?.role === 'owner')
// View follows the store switcher: a selected store → that store's log;
// "Business level" (owner, no store selected) → consolidated all-stores log.
const viewMode = computed(() => (!currentStore.value && isBusinessOwner.value) ? 'business' : 'store')

const {
  logs: entries, loading, fetching,
  currentPage, lastPage, total, perPage,
  startDate, endDate, searchQuery, objectFilter, actionFilter,
  hasActiveFilter,
  resetAndFetch, applyFilter, clearFilter, loadPage, changePerPage,
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
  onSuccess: () => showToast(t('audit.exportReady'), 'success'),
  onError:   (msg) => showToast(msg, 'error'),
})

// Reload whenever the switcher changes the active scope (store ↔ business level).
watch(
  [viewMode, () => currentStore.value?.id, () => currentBusiness.value?.id],
  () => {
    const ready = viewMode.value === 'business' ? currentBusiness.value?.id : currentStore.value?.id
    if (ready) resetAndFetch()
  },
  { immediate: true },
)
</script>
