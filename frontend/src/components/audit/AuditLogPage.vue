<template>
  <div class="audit-log-page">
    <div class="page-header">
      <div>
        <h1>Audit Log</h1>
        <p class="subtitle">
          Activity history for
          <strong>{{ currentStore?.name ?? '—' }}</strong>
        </p>
      </div>
    </div>

    <div v-if="!currentStore" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3h18v4H3z"/><path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
        </svg>
      </div>
      <h3>No store selected</h3>
      <p>Select a store from the top bar to view its activity.</p>
    </div>

    <template v-else>
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>Loading activity...</span>
      </div>

      <div v-else-if="logs.length === 0" class="empty-state">
        <div class="empty-icon">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
        </div>
        <h3>No activity yet</h3>
        <p>Actions performed in this store will appear here.</p>
      </div>

      <div v-else class="log-feed">
        <div v-for="log in logs" :key="log.id" class="log-entry">
          <div class="log-left">
            <span class="badge" :class="badgeClass(log.object_type)">{{ log.object_type }}</span>
          </div>
          <div class="log-body">
            <p class="log-message">{{ log.message }}</p>
            <span class="log-time" :title="formatAbsolute(log.created_at)">{{ formatRelative(log.created_at) }}</span>
          </div>
        </div>

        <div v-if="lastPage > 1" class="pagination">
          <button class="page-btn" :disabled="currentPage === 1" @click="loadPage(currentPage - 1)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="15 18 9 12 15 6"/>
            </svg>
            Prev
          </button>
          <span class="page-info">Page {{ currentPage }} of {{ lastPage }}</span>
          <button class="page-btn" :disabled="currentPage === lastPage" @click="loadPage(currentPage + 1)">
            Next
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, watch, inject } from 'vue'
import { graphql } from '@/api'

const currentStore = inject('currentStore')
const showToast = inject('showToast')

const logs = ref([])
const loading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)

const QUERY = `
  query AuditLogs($store_id: ID!, $page: Int, $per_page: Int) {
    auditLogs(store_id: $store_id, page: $page, per_page: $per_page) {
      data {
        id
        actor_name
        actor_email
        object_type
        action
        message
        created_at
      }
      total
      current_page
      last_page
      per_page
    }
  }
`

const fetchLogs = async (page = 1) => {
  if (!currentStore.value?.id) return
  loading.value = true
  try {
    const data = await graphql(QUERY, {
      store_id: currentStore.value.id,
      page,
      per_page: 20,
    })
    logs.value = data.auditLogs.data
    currentPage.value = data.auditLogs.current_page
    lastPage.value = data.auditLogs.last_page
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loading.value = false
  }
}

const loadPage = (page) => fetchLogs(page)

watch(
  () => currentStore.value?.id,
  (id) => { if (id) { currentPage.value = 1; fetchLogs(1) } },
  { immediate: true }
)

const badgeClass = (objectType) => {
  const map = { Store: 'badge-store', User: 'badge-user', Invitation: 'badge-invitation' }
  return map[objectType] ?? 'badge-default'
}

const formatRelative = (isoString) => {
  const diff = Date.now() - new Date(isoString).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  if (days < 30) return `${days}d ago`
  return new Date(isoString).toLocaleDateString()
}

const formatAbsolute = (isoString) =>
  new Date(isoString).toLocaleString()
</script>

<style scoped>
.audit-log-page { padding: 32px; max-width: 860px; margin: 0 auto; }

.page-header { margin-bottom: 28px; }
.page-header h1 { font-size: 22px; font-weight: 700; color: #111; }
.subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }

.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px 0; color: #6b7280; font-size: 14px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { width: 72px; height: 72px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #9ca3af; }
.empty-state h3 { font-size: 16px; font-weight: 600; color: #111; margin-bottom: 6px; }
.empty-state p { font-size: 14px; color: #6b7280; }

.log-feed { display: flex; flex-direction: column; gap: 0; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #fff; }

.log-entry { display: flex; align-items: flex-start; gap: 14px; padding: 14px 20px; border-bottom: 1px solid #f3f4f6; transition: background 0.12s; }
.log-entry:last-of-type { border-bottom: none; }
.log-entry:hover { background: #fafafa; }

.log-left { padding-top: 1px; flex-shrink: 0; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.03em; text-transform: uppercase; }
.badge-store      { background: #dbeafe; color: #1d4ed8; }
.badge-user       { background: #d1fae5; color: #065f46; }
.badge-invitation { background: #ede9fe; color: #6d28d9; }
.badge-default    { background: #f3f4f6; color: #6b7280; }

.log-body { flex: 1; min-width: 0; }
.log-message { font-size: 13.5px; color: #111; line-height: 1.5; word-break: break-word; margin: 0 0 4px; }
.log-time { font-size: 12px; color: #9ca3af; }

.pagination { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 16px 20px; border-top: 1px solid #f3f4f6; }
.page-btn { display: flex; align-items: center; gap: 4px; padding: 7px 14px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; color: #374151; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.15s; }
.page-btn:hover:not(:disabled) { border-color: #d1d5db; background: #f9fafb; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 13px; color: #6b7280; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
